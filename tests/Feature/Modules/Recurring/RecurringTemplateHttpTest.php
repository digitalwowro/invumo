<?php

namespace Tests\Feature\Modules\Recurring;

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
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDeletionPreviews;
use Tests\TestCase;

final class RecurringTemplateHttpTest extends TestCase
{
    use DatabaseMigrations, InteractsWithDeletionPreviews;

    public function test_member_creates_idempotent_draft_and_saves_authoritative_lines(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->company($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        [$customer, $token] = $this->customer($company);
        $creationKey = (string) Str::uuid7();
        $payload = [
            'creation_key' => $creationKey,
            'internal_name' => 'Monthly support',
            'customer_id' => $customer->id,
            'customer_confirmation_token' => $token,
        ];
        $this->actingAs($member);

        $this->get(route('recurring.create', $company))->assertInertia(fn (Assert $page) => $page
            ->component('recurring/create')
            ->where('translations.create.title', 'New recurring template'));
        $first = $this->post(route('recurring.store', $company), $payload)->assertRedirect();
        $this->post(route('recurring.store', $company), $payload)
            ->assertRedirect($first->headers->get('Location'));
        $template = $this->tenant($company, fn (): RecurringTemplate => RecurringTemplate::query()->sole());

        $this->get(route('recurring.edit', [$company, $template]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('recurring/edit')
                ->where('template.internalName', 'Monthly support')
                ->where('template.currencyCode', 'RON')
                ->where('template.currencyPrecision', 2)
                ->where('template.editVersion', 1));

        $this->patch(route('recurring.update', [$company, $template]), [
            'edit_version' => 1,
            'internal_name' => 'Monthly support plan',
            'customer_id' => $customer->id,
            'customer_confirmation_token' => $token,
            'customer_reference' => 'PO-PRIVATE',
            'lines' => [$this->line('Private consulting', '100.12345678', '2', '10', 'TVA', '19')],
            'inheritance' => $this->inheritance(),
        ])->assertRedirect()->assertSessionHas('status');

        $this->tenant($company, function (): void {
            $template = RecurringTemplate::query()->sole();
            $line = RecurringTemplateLine::query()->sole();
            $this->assertSame(2, $template->edit_version);
            $this->assertSame('Monthly support plan', $template->internal_name);
            $this->assertSame('100.12345678', $line->item_price);
            $this->assertSame('2.000000', $line->quantity);

            $audit = AuditEvent::query()
                ->where('action', 'company.recurring_template.draft_updated')
                ->sole();
            $encoded = json_encode([$audit->before, $audit->after], JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('Monthly support', $encoded);
            $this->assertStringNotContainsString('Private consulting', $encoded);
            $this->assertStringNotContainsString('PO-PRIVATE', $encoded);
            $this->assertSame(1, $audit->after['complete_line_count']);
        });

        $this->delete(route('recurring.destroy', [$company, $template]), [
            'confirmed' => true,
            'deletion_state' => $this->recurringDeletionState($company, $template),
        ])
            ->assertForbidden();
    }

    public function test_list_literal_search_and_name_cursor_work_after_first_page(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        [$customer] = $this->customer($company);
        $this->tenant($company, function () use ($customer): void {
            foreach (range(1, 26) as $number) {
                RecurringTemplate::query()->create([
                    'client_creation_key' => (string) Str::uuid7(),
                    'internal_name' => sprintf('Plan %s %02d', $number === 26 ? '50%' : 'standard', $number),
                    'customer_id' => $customer->id,
                ]);
            }
        });
        $this->actingAs($owner);

        $page = $this->get(route('recurring.index', [
            $company, 'sort' => 'name_asc', 'per_page' => 25,
        ]))->assertOk();
        $page->assertInertia(fn (Assert $inertia) => $inertia
            ->where('summary.all.count', 26)
            ->where('summary.active.count', 0)
            ->where('filters.state', 'all'));
        $next = $page->viewData('page')['props']['templates']['nextUrl'];
        $this->assertIsString($next);
        $this->get($next)->assertOk()->assertInertia(fn (Assert $inertia) => $inertia
            ->has('templates.items', 1));

        $this->get(route('recurring.index', [$company, 'q' => '50%']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->has('templates.items', 1)
                ->where('templates.items.0.internalName', 'Plan 50% 26'));
    }

    public function test_admin_deletes_draft_and_cross_company_access_fails_closed(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $otherOwner = User::factory()->create();
        $company = $this->company($owner);
        $other = $this->company($otherOwner);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        [$customer] = $this->customer($company);
        $template = $this->tenant($company, fn (): RecurringTemplate => RecurringTemplate::query()->create([
            'client_creation_key' => (string) Str::uuid7(),
            'internal_name' => 'Delete me',
            'customer_id' => $customer->id,
        ]));
        $this->actingAs($admin);

        $this->get(route('recurring.edit', [$other, $template]))->assertNotFound();
        $this->delete(route('recurring.destroy', [$company, $template]), [
            'confirmed' => true,
            'deletion_state' => $this->recurringDeletionState($company, $template),
        ])
            ->assertRedirect(route('recurring.index', $company));
        $this->tenant($company, fn () => $this->assertSame(0, RecurringTemplate::query()->count()));
    }

    /** @return array{Customer, string} */
    private function customer(Company $company): array
    {
        return $this->tenant($company, function (): array {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company,
                'legal_name' => 'Customer SRL',
            ]);
            $resolved = app(ResolveDocumentCustomer::class)->for($customer->id);

            return [$customer, $resolved->confirmationToken];
        });
    }

    /** @return array<string, mixed> */
    private function line(
        string $description,
        string $price = '10',
        string $quantity = '1',
        string $discount = '0',
        string $taxName = '',
        string $tax = '0',
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
            'tax_mode' => 'EXPLICIT',
            'tax_preset_id' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function inheritance(): array
    {
        return [
            'identity_mode' => 'INHERIT',
            'identity' => [],
            'recipients_mode' => 'INHERIT',
            'recipients' => [],
            'currency_mode' => 'INHERIT',
            'currency_code' => 'RON',
            'language_mode' => 'INHERIT',
            'document_language' => 'en',
            'payment_term_mode' => 'INHERIT',
            'payment_term_days' => null,
            'tax_mode' => 'INHERIT',
            'tax_preset_id' => null,
            'delivery_mode' => 'INHERIT',
            'email_attachment_mode' => 'SECURE_LINK_ONLY',
            'terms_mode' => 'INHERIT',
            'terms_and_conditions' => null,
            'notes_mode' => 'INHERIT',
            'notes' => null,
            'bank_mode' => 'INHERIT',
            'bank_account_id' => null,
            'reminder_mode' => 'INHERIT_COMPANY',
            'reminder_rules' => [],
        ];
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Recurring Test SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest',
                'default_document_language' => 'en',
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON',
                'currency_precision' => 2,
                'is_default' => true,
                'active' => true,
            ]);
        });

        return $company;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
