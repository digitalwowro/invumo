<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyCurrencyHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_manages_currency_lifecycle_with_bounded_audit_history(): void
    {
        [$owner, $company] = $this->company();
        $this->actingAs($owner)
            ->get(route('company-currencies.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/currencies')
                ->where('companySettingsNavigation.1.key', 'currencies')
                ->where('translations.settings.layout.navigation.currencies', 'Currencies')
                ->where('currencies', [])
                ->where('currencyOptions', fn (mixed $options): bool => collect($options)
                    ->contains('value', 'EUR')));

        $this->post(route('company-currencies.store', $company), [
            'currency_code' => 'RON',
            'currency_precision' => 2,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->post(route('company-currencies.store', $company), [
            'currency_code' => 'EUR',
            'currency_precision' => 3,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        [$ron, $eur] = $this->tenant($company, fn (): array => [
            CompanyCurrency::query()->where('currency_code', 'RON')->sole(),
            CompanyCurrency::query()->where('currency_code', 'EUR')->sole(),
        ]);
        $this->assertTrue($ron->is_default);
        $this->assertFalse($eur->is_default);

        $this->patch(route('company-currencies.update', [$company, $eur]), [
            'currency_precision' => 4,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->patch(route('company-currencies.default', [$company, $eur]))
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->patch(route('company-currencies.deactivate', [$company, $ron]))
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->patch(route('company-currencies.restore', [$company, $ron]))
            ->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, function (): void {
            $ron = CompanyCurrency::query()->where('currency_code', 'RON')->sole();
            $eur = CompanyCurrency::query()->where('currency_code', 'EUR')->sole();
            $created = AuditEvent::query()->where('action', 'company.currency.created')->get();
            $updated = AuditEvent::query()->where('action', 'company.currency.updated')->sole();
            $defaulted = AuditEvent::query()->where('action', 'company.currency.default_changed')->sole();

            $this->assertTrue($ron->active);
            $this->assertFalse($ron->is_default);
            $this->assertTrue($eur->is_default);
            $this->assertSame(4, $eur->currency_precision);
            $this->assertCount(2, $created);
            $this->assertEqualsCanonicalizing(
                ['changed_fields', 'currency_code', 'currency_precision', 'is_default', 'active'],
                array_keys($created->firstOrFail()->after ?? []),
            );
            $this->assertEqualsCanonicalizing(
                ['changed_fields', 'currency_code', 'currency_precision'],
                array_keys($updated->after ?? []),
            );
            $this->assertSame('RON', $defaulted->before['currency_code']);
            $this->assertSame('EUR', $defaulted->after['currency_code']);
            $this->assertSame(1, AuditEvent::query()->where('action', 'company.currency.deactivated')->count());
            $this->assertSame(1, AuditEvent::query()->where('action', 'company.currency.restored')->count());
        });
    }

    public function test_default_source_and_precision_dependencies_fail_closed(): void
    {
        [$owner, $company] = $this->company();
        [$ron, $eur] = $this->tenant($company, fn (): array => [
            $this->currency('RON', 2, true),
            $this->currency('EUR', 3),
        ]);
        $this->actingAs($owner);

        $this->patch(route('company-currencies.deactivate', [$company, $ron]))
            ->assertSessionHasErrors('currency');

        $this->tenant($company, function () use ($eur): void {
            Customer::query()->create([
                'type' => 'COMPANY',
                'legal_name' => 'Currency Customer SRL',
                'currency_id' => $eur->id,
            ]);
            ProductService::query()->create([
                'name' => 'Precise service',
                'unit_price' => '10.12300000',
                'currency_id' => $eur->id,
            ]);
        });

        $this->get(route('company-currencies.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currencies.1.code', 'EUR')
                ->where('currencies.1.deactivateGuard.blocked', true)
                ->where('currencies.1.deactivateGuard.description', fn (mixed $value): bool => is_string($value)
                    && str_contains($value, 'Customers — 1')
                    && str_contains($value, 'Products/Services — 1')));
        $this->patch(route('company-currencies.deactivate', [$company, $eur]))
            ->assertSessionHasErrors('currency');
        $this->patch(route('company-currencies.update', [$company, $eur]), [
            'currency_precision' => 2,
        ])->assertSessionHasErrors('currency_precision');

        $this->tenant($company, function () use ($eur): void {
            Customer::query()->update(['currency_id' => null]);
            ProductService::query()->update(['unit_price' => null, 'currency_id' => null]);
            $customer = Customer::query()->sole();
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Retained currency snapshot',
                'customer_id' => $customer->id,
            ]);
            RecurringTemplateCustomerValue::query()->create([
                'recurring_template_id' => $template->id,
                'explicit_fields' => ['currency'],
                'currency_id' => $eur->id,
                'currency_code' => 'EUR',
                'currency_precision' => 3,
            ]);
        });

        $this->patch(route('company-currencies.deactivate', [$company, $eur]))
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->tenant($company, function () use ($eur): void {
            $this->assertFalse(CompanyCurrency::query()->findOrFail($eur->id)->active);
            $this->assertSame(3, RecurringTemplateCustomerValue::query()->sole()->currency_precision);
        });
    }

    public function test_validation_authorization_and_tenant_boundaries_are_enforced(): void
    {
        [$owner, $company] = $this->company();
        [$otherOwner, $otherCompany] = $this->company('other@example.com');
        $member = $this->user('member@example.com');
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $currency = $this->tenant($company, fn (): CompanyCurrency => $this->currency('RON', 2, true));
        $otherCurrency = $this->tenant(
            $otherCompany,
            fn (): CompanyCurrency => $this->currency('EUR', 2, true),
        );

        $this->actingAs($owner)->post(route('company-currencies.store', $company), [
            'currency_code' => 'RON',
            'currency_precision' => 2,
        ])->assertSessionHasErrors('currency_code');
        $this->post(route('company-currencies.store', $company), [
            'currency_code' => 'INVALID',
            'currency_precision' => 9,
        ])->assertSessionHasErrors(['currency_code', 'currency_precision']);

        $this->actingAs($member)
            ->get(route('company-currencies.index', $company))
            ->assertForbidden();
        $this->patch(route('company-currencies.update', [$company, $currency]), [
            'currency_precision' => 3,
        ])->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('company-currencies.update', [$company, $otherCurrency]), [
                'currency_precision' => 3,
            ])->assertNotFound();
        $this->actingAs($otherOwner)
            ->get(route('company-currencies.index', $company))
            ->assertNotFound();
    }

    private function currency(string $code, int $precision, bool $default = false): CompanyCurrency
    {
        return CompanyCurrency::query()->create([
            'currency_code' => $code,
            'currency_precision' => $precision,
            'is_default' => $default,
            'active' => true,
        ]);
    }

    /** @return array{User, Company} */
    private function company(string $email = 'owner@example.com'): array
    {
        $owner = $this->user($email);

        return [$owner, app(CreateCompany::class)->handle(
            $owner->account()->firstOrFail(), $owner, 'Currency SRL',
        )];
    }

    private function user(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return $user;
    }

    private function tenant(Company $company, callable $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
