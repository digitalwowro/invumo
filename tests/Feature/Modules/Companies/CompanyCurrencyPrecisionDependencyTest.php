<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
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

    /** @return array<string, mixed> */
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
