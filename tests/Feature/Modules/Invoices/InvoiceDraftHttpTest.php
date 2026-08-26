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
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Queries\ResolveDocumentCustomer;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentLine;
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

    public function test_member_creates_an_idempotent_invoice_and_saves_authoritative_lines(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->company($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $key = (string) Str::uuid7();
        $this->actingAs($member);

        $this->get(route('invoices.create', $company))->assertInertia(fn (Assert $page) => $page
            ->component('invoices/create')
            ->where('translations.create.title', 'New invoice'));
        $first = $this->post(route('invoices.store', $company), ['creation_key' => $key]);
        $first->assertRedirect();
        $this->post(route('invoices.store', $company), ['creation_key' => $key])
            ->assertRedirect($first->headers->get('Location'));

        $invoice = $this->tenant($company, fn (): Document => Document::query()->sole());
        $this->assertSame('I-2026-0001', $invoice->rendered_number);
        $this->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/edit')
                ->where('invoice.paymentTermDays', 30)
                ->where('invoice.dueDate', '2026-09-25')
                ->where('invoice.currencyCode', 'RON'));

        $this->patch(route('invoices.update', [$company, $invoice]), [
            ...$this->defaults(),
            'edit_version' => 1,
            'customer_reference' => '50%',
            'lines' => [$this->line('Consulting', '100', '2', '10', 'TVA', '19')],
        ])->assertRedirect()->assertSessionHas('status');

        $this->tenant($company, function (): void {
            $document = Document::query()->sole();
            $this->assertSame('214.20000000', $document->total);
            $this->assertSame('214.20000000', DocumentLine::query()->sole()->final_line_total);
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
    }

    public function test_confirmed_customer_applies_payment_terms_and_detached_snapshot(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $customer = $this->tenant($company, fn (): Customer => Customer::query()->create([
            'type' => CustomerType::Company,
            'legal_name' => 'Customer SRL',
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
