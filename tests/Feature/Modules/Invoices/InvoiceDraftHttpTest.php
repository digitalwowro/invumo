<?php

namespace Tests\Feature\Modules\Invoices;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Queries\ResolveDocumentCustomer;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Documents\Models\DocumentTaxDefault;
use App\Modules\Documents\Models\NumberCounter;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Models\Invoice;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceDraftHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-26 12:00:00');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_member_opens_an_unsaved_editor_then_first_save_creates_one_numbered_invoice(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->company($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $key = (string) Str::uuid7();
        $this->actingAs($member);

        $this->get(route('invoices.index', $company))->assertInertia(fn (Assert $page) => $page
            ->component('invoices/index')
            ->where('createUrl', route('invoices.create', $company, false)));
        $this->get(route('invoices.create', $company))->assertInertia(fn (Assert $page) => $page
            ->component('invoices/create')
            ->where('creation.url', route('invoices.store', $company, false))
            ->where('invoice.number', '')
            ->where('invoice.lines', []));
        $this->tenant($company, function (): void {
            $this->assertSame(0, Document::query()->count());
            $this->assertSame(0, Invoice::query()->count());
            $this->assertSame(0, NumberCounter::query()->count());
        });

        $payload = [
            ...$this->defaults(),
            'creation_key' => $key,
            'edit_version' => 1,
            'customer_reference' => '50%',
            'lines' => [$this->line('Consulting', '100', '2', '10', 'TVA', '19')],
        ];
        $first = $this->post(route('invoices.store', $company), $payload);
        $first->assertRedirect();
        $this->post(route('invoices.store', $company), $payload)
            ->assertRedirect($first->headers->get('Location'));

        $invoice = $this->tenant($company, fn (): Document => Document::query()->sole());
        $this->assertSame('I-2026-0001', $invoice->rendered_number);
        $this->tenant($company, function (): void {
            $audit = AuditEvent::query()->where('action', 'company.invoice.created')->sole();
            $this->assertSame(1, $audit->after['line_count']);
            $this->assertSame(1, $audit->after['complete_line_count']);
            $this->assertFalse($audit->after['customer_selection_applied']);
            $this->assertContains('lines', $audit->after['changed_fields']);
            $encoded = json_encode($audit->after, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('Consulting', $encoded);
            $this->assertStringNotContainsString('214.2', $encoded);
            $this->assertSame(0, AuditEvent::query()
                ->where('action', 'company.invoice.draft_updated')
                ->count());
        });
        $this->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/edit')->where('initialTab', 'build')
                ->where('invoice.paymentTermDays', 30)
                ->where('invoice.dueDate', '2026-09-25')
                ->where('invoice.currencyCode', 'RON'));
        $this->get(route('invoices.edit', [$company, $invoice, 'tab' => 'money']))
            ->assertInertia(fn (Assert $page) => $page->where('initialTab', 'money'));

        $this->patch(route('invoices.update', [$company, $invoice]), [
            ...$this->defaults(),
            'edit_version' => 1,
            'customer_reference' => '50%',
            'lines' => [$this->line('Consulting', '100', '2', '10', 'TVA', '19')],
        ])->assertRedirect()->assertSessionHas('status');

        $this->tenant($company, function (): void {
            $document = Document::query()->sole();
            $this->assertSame('214.20000000', $document->total);
            $line = DocumentLine::query()->sole();
            $this->assertSame('214.20000000', $line->final_line_total);
            $this->assertTrue($line->is_customized);
            $this->assertFalse($document->defaults_customized);
            $this->assertSame(2, $document->edit_version);
            $audit = AuditEvent::query()->where('action', 'company.invoice.draft_updated')->sole();
            $encoded = json_encode([$audit->before, $audit->after], JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('Consulting', $encoded);
            $this->assertStringNotContainsString('50%', $encoded);
            $this->assertSame(1, $audit->after['complete_line_count']);
        });

        $this->get(route('invoices.index', [$company, 'q' => '%']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/index')
                ->has('invoices.items', 1)
                ->where('invoices.items.0.customerReference', '50%'));

        $this->patch(route('invoices.update', [$company, $invoice]), [
            ...$this->defaults(),
            'edit_version' => 2,
            'notes' => 'Only for this Invoice',
            'lines' => [[
                ...$this->line('Consulting', '100.00', '2.000000', '10', 'TVA', '19'),
                'id' => $this->tenant($company, fn (): string => DocumentLine::query()->sole()->id),
            ]],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, fn () => $this->assertTrue(
            Document::query()->sole()->defaults_customized,
        ));
    }

    public function test_confirmed_customer_applies_payment_terms_and_detached_snapshot(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $customer = $this->tenant($company, fn (): Customer => Customer::query()->create([
            'type' => CustomerType::Company,
            'legal_name' => 'Customer SRL',
            'email' => 'billing@customer.example',
            'address_line_1' => 'Strada Exemplu 10',
            'city' => 'Cluj-Napoca',
            'country_code' => 'RO',
            'tax_registration_label' => 'CUI',
            'tax_registration_identifier' => 'RO12345678',
            'payment_term_days' => 14,
        ]));
        $invoice = app(CreateInvoiceDraft::class)->handle(
            $company,
            $owner,
            (string) Str::uuid7(),
        );
        $selection = $this->tenant(
            $company,
            fn () => app(ResolveDocumentCustomer::class)->for($customer->id),
        );
        $this->actingAs($owner)->patch(route('invoices.update', [$company, $invoice]), [
            ...$this->defaults(),
            'edit_version' => 1,
            'customer_id' => $customer->id,
            'customer_confirmation_token' => $selection->confirmationToken,
            'payment_term_days' => 14,
            'due_date' => '2026-09-09',
            'lines' => [],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, function () use ($customer): void {
            $this->assertSame(14, Invoice::query()->sole()->payment_term_days);
            $this->assertSame('2026-09-09', Invoice::query()->sole()->due_date->toDateString());
            $snapshot = DocumentCustomerSnapshot::query()->sole();
            $this->assertSame($customer->id, Document::query()->sole()->customer_id);
            $this->assertSame('Customer SRL', $snapshot->legal_name);
        });
        $this->actingAs($owner)
            ->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.customer.snapshot.email', 'billing@customer.example')
                ->where('invoice.customer.snapshot.address_line_1', 'Strada Exemplu 10')
                ->where('invoice.customer.snapshot.tax_registration_identifier', 'RO12345678'));
    }

    public function test_document_tax_default_can_change_only_to_an_active_preset(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        [, $reduced, $archived] = $this->tenant($company, function (): array {
            return [
                TaxPreset::query()->create([
                    'name' => 'Standard VAT', 'percentage' => '21', 'is_default' => true,
                ]),
                TaxPreset::query()->create([
                    'name' => 'Reduced VAT', 'percentage' => '9', 'is_default' => false,
                ]),
                TaxPreset::query()->create([
                    'name' => 'Legacy VAT', 'percentage' => '5', 'is_default' => false,
                ]),
            ];
        });
        $invoice = app(CreateInvoiceDraft::class)->handle(
            $company,
            $owner,
            (string) Str::uuid7(),
        );

        $this->actingAs($owner)->patch(route('invoices.update', [$company, $invoice]), [
            ...$this->defaults(),
            'edit_version' => 1,
            'tax_default_preset_id' => $reduced->id,
            'lines' => [],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, function () use ($reduced): void {
            $snapshot = DocumentTaxDefault::query()->sole();
            $this->assertSame($reduced->id, $snapshot->tax_preset_id);
            $this->assertSame('Reduced VAT', $snapshot->name);
            $audit = AuditEvent::query()->where('action', 'company.invoice.draft_updated')->sole();
            $this->assertContains('tax_default', $audit->after['changed_fields']);
        });

        $this->tenant($company, fn () => $archived->update(['archived_at' => now()]));
        $this->actingAs($owner)->patch(route('invoices.update', [$company, $invoice]), [
            ...$this->defaults(),
            'edit_version' => 2,
            'tax_default_preset_id' => $archived->id,
            'lines' => [],
        ])->assertSessionHasErrors('tax_default_preset_id');

        $this->tenant($company, fn () => $this->assertSame(
            $reduced->id,
            DocumentTaxDefault::query()->sole()->tax_preset_id,
        ));
    }

    public function test_roles_localization_stale_writes_and_cross_company_access_fail_closed(): void
    {
        $owner = User::factory()->create(['language_code' => 'ro']);
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $company = $this->company($owner);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $invoice = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());

        foreach ([$owner, $admin, $member] as $actor) {
            $this->actingAs($actor)->get(route('invoices.edit', [$company, $invoice]))->assertOk();
        }
        $this->actingAs($outsider)->get(route('invoices.edit', [$company, $invoice]))->assertNotFound();

        $response = $this->actingAs($owner)->patch(route('invoices.update', [$company, $invoice]), [
            ...$this->defaults(),
            'edit_version' => 1,
            'due_date' => '2026-08-25',
            'lines' => [],
        ])->assertSessionHasErrors('due_date');
        $this->assertStringContainsString('scadenței', $response->getSession()->get('errors')->first('due_date'));

        $this->patch(route('invoices.update', [$company, $invoice]), [
            ...$this->defaults(),
            'edit_version' => 0,
            'lines' => [],
        ])->assertSessionHasErrors('edit_version');

        $other = $this->company($outsider);
        $this->actingAs($owner)->get(route('invoices.edit', [$other, $invoice]))->assertNotFound();
        $this->tenant($other, fn () => $this->assertNull(Document::query()->find($invoice->id)));
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'customer_id' => null,
            'customer_confirmation_token' => null,
            'tax_default_preset_id' => null,
            'currency_code' => 'RON',
            'document_language' => 'en',
            'issue_date' => '2026-08-26',
            'payment_term_days' => 30,
            'due_date' => '2026-09-25',
            'customer_reference' => null,
            'bank_account_id' => null,
            'terms_and_conditions' => null,
            'notes' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function line(
        string $description,
        string $price,
        string $quantity,
        string $discount,
        string $taxName,
        string $tax,
    ): array {
        return [
            'description' => $description,
            'item_price' => $price,
            'quantity' => $quantity,
            'unit' => 'hour',
            'period_unit' => 'NONE',
            'period_quantity' => null,
            'discount_percentage' => $discount,
            'tax_name' => $taxName,
            'tax_percentage' => $tax,
        ];
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Invoice Test SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest',
                'default_document_language' => 'en',
                'default_payment_term_days' => 30,
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return $company;
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
