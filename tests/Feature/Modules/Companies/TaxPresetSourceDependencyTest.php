<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class TaxPresetSourceDependencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_customer_default_blocks_tax_preset_archiving(): void
    {
        [$owner, $company] = $this->company();
        [$preset, $customer] = $this->tenant($company, fn (): array => [
            TaxPreset::query()->create([
                'name' => 'Customer VAT', 'percentage' => '19', 'is_default' => false,
            ]),
            Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Customer SRL',
            ]),
        ]);
        $this->tenant($company, fn () => $customer->update(['tax_preset_id' => $preset->id]));

        $this->actingAs($owner)
            ->patch(route('company-tax-presets.archive', [$company, $preset]))
            ->assertSessionHasErrors(['tax_preset' => __('companies_ui.settings.taxes.errors.default_dependency')]);
        $this->assertDependencyStayedIntact($company, $preset, $customer->id, null);
    }

    public function test_product_default_blocks_tax_preset_archiving(): void
    {
        [$owner, $company] = $this->company();
        [$preset, $product] = $this->tenant($company, function (): array {
            $preset = TaxPreset::query()->create([
                'name' => 'Product VAT', 'percentage' => '19', 'is_default' => false,
            ]);

            return [$preset, ProductService::query()->create([
                'name' => 'Service', 'tax_preset_id' => $preset->id,
            ])];
        });

        $this->actingAs($owner)
            ->patch(route('company-tax-presets.archive', [$company, $preset]))
            ->assertSessionHasErrors(['tax_preset' => __('companies_ui.settings.taxes.errors.default_dependency')]);
        $this->assertDependencyStayedIntact($company, $preset, null, $product->id);
    }

    private function assertDependencyStayedIntact(
        Company $company,
        TaxPreset $preset,
        ?string $customerId,
        ?string $productId,
    ): void {
        $this->tenant($company, function () use ($preset, $customerId, $productId): void {
            $this->assertNull(TaxPreset::query()->findOrFail($preset->id)->archived_at);
            if ($customerId !== null) {
                $this->assertSame($preset->id, Customer::query()->findOrFail($customerId)->tax_preset_id);
            }
            if ($productId !== null) {
                $this->assertSame($preset->id, ProductService::query()->findOrFail($productId)->tax_preset_id);
            }
            $this->assertSame(0, AuditEvent::query()->where('action', 'company.tax_preset.archived')->count());
        });
    }

    /** @return array{User, Company} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return [$owner, app(CreateCompany::class)->handle($account, $owner, 'Sources SRL')];
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
