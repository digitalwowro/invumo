<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CompanyCurrencyPrecisionDependencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_precision_change_fails_on_the_settings_field_when_a_product_price_is_incompatible(): void
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Precision SRL');

        $this->actingAs($owner)
            ->patch(route('company-settings.profile.update', $company), $this->configuration())
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $currency = CompanyCurrency::query()->where('currency_code', 'RON')->firstOrFail();
            ProductService::query()->create([
                'name' => 'Precise service',
                'unit_price' => '10.12300000',
                'currency_id' => $currency->id,
            ]);
        });

        $this->patch(
            route('company-settings.profile.update', $company),
            $this->configuration(['currency_precision' => '2']),
        )->assertSessionHasErrors('currency_precision');

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $currency = CompanyCurrency::query()->where('currency_code', 'RON')->firstOrFail();

            $this->assertSame(3, $currency->currency_precision);
            $this->assertSame(
                1,
                AuditEvent::query()->where('action', 'company.configuration.updated')->count(),
            );
        });
    }

    public function test_precision_change_succeeds_when_all_product_prices_remain_exact(): void
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Exact SRL');

        $this->actingAs($owner)
            ->patch(route('company-settings.profile.update', $company), $this->configuration())
            ->assertRedirect();

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $currency = CompanyCurrency::query()->where('currency_code', 'RON')->firstOrFail();
            ProductService::query()->create([
                'name' => 'Exact service',
                'unit_price' => '10.12000000',
                'currency_id' => $currency->id,
            ]);
        });

        $this->patch(
            route('company-settings.profile.update', $company),
            $this->configuration(['currency_precision' => '2']),
        )->assertRedirect()->assertSessionDoesntHaveErrors();

        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => $this->assertSame(
                2,
                CompanyCurrency::query()->where('currency_code', 'RON')->firstOrFail()->currency_precision,
            ),
        );
    }

    public function test_recurring_source_prices_and_explicit_currency_snapshots_remain_stable(): void
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Recurring Precision SRL');
        $this->actingAs($owner)->patch(
            route('company-settings.profile.update', $company),
            $this->configuration(['currency_precision' => '8']),
        )->assertRedirect();

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $currency = CompanyCurrency::query()->where('currency_code', 'RON')->firstOrFail();
            $customer = Customer::query()->create(['type' => 'COMPANY', 'legal_name' => 'Source SRL']);
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Fixed source inputs',
                'customer_id' => $customer->id,
            ]);
            RecurringTemplateLine::query()->create([
                'recurring_template_id' => $template->id,
                'position' => 1,
                'item_price' => '10.12345678',
                'quantity' => '1',
                'period_unit' => 'NONE',
                'discount_percentage' => '0',
                'tax_percentage' => '0',
            ]);
            RecurringTemplateCustomerValue::query()->create([
                'recurring_template_id' => $template->id,
                'explicit_fields' => ['currency'],
                'currency_id' => $currency->id,
                'currency_code' => 'RON',
                'currency_precision' => 8,
            ]);
        });

        $this->patch(
            route('company-settings.profile.update', $company),
            $this->configuration(['currency_precision' => '2']),
        )->assertRedirect()->assertSessionDoesntHaveErrors();

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $this->assertSame('10.12345678', RecurringTemplateLine::query()->sole()->item_price);
            $this->assertSame(8, RecurringTemplateCustomerValue::query()->sole()->currency_precision);
            $this->assertSame(2, CompanyCurrency::query()->where('currency_code', 'RON')->sole()->currency_precision);
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function configuration(array $overrides = []): array
    {
        return array_replace([
            'display_name' => 'Precision Workspace',
            'legal_name' => 'Precision SRL',
            'timezone' => 'Europe/Bucharest',
            'automation_local_time' => '09:00',
            'currency_code' => 'RON',
            'currency_precision' => '3',
            'currency_display_style' => 'CODE',
        ], $overrides);
    }
}
