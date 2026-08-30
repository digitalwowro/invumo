<?php

namespace Tests\Feature\Modules\Catalog;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Catalog\Queries\CatalogLineDefaults;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class CatalogLineDefaultsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_member_receives_detached_defaults_with_safe_currency_behavior(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->companyFor($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        [$product] = $this->configuredProduct($company);

        $matched = $this->tenant($company, fn (): array => app(CatalogLineDefaults::class)
            ->for($company, $member, $product->id, 'RON'));
        $this->assertSame('Consulting', $matched['name']);
        $this->assertSame('Detailed work', $matched['description']);
        $this->assertSame('120.50', $matched['unitPrice']);
        $this->assertSame('COPIED', $matched['priceStatus']);
        $this->assertSame('hour', $matched['unit']);
        $this->assertSame('MONTH', $matched['periodUnit']);
        $this->assertSame('19.000000', $matched['tax']['percentage']);

        $mismatch = $this->tenant($company, fn (): array => app(CatalogLineDefaults::class)
            ->for($company, $member, $product->id, 'EUR'));
        $this->assertNull($mismatch['unitPrice']);
        $this->assertSame('CURRENCY_MISMATCH', $mismatch['priceStatus']);
        $this->assertSame($matched['description'], $mismatch['description']);

        $this->tenant($company, fn () => $product->update([
            'name' => 'Changed source', 'description' => null, 'unit_price' => '99.00000000',
        ]));
        $this->assertSame('Detailed work', $matched['description']);
        $this->assertSame('120.50', $matched['unitPrice']);
    }

    public function test_missing_price_and_archived_or_cross_company_sources_fail_closed(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $company = $this->companyFor($owner);
        $other = $this->companyFor($otherOwner);
        $product = $this->tenant($company, fn (): ProductService => ProductService::query()->create([
            'name' => 'Manual price', 'period_unit' => 'NONE',
        ]));

        $defaults = $this->tenant($company, fn (): array => app(CatalogLineDefaults::class)
            ->for($company, $owner, $product->id, 'RON'));
        $this->assertNull($defaults['unitPrice']);
        $this->assertSame('ENTER_MANUALLY', $defaults['priceStatus']);

        $this->tenant($company, fn () => $product->update(['archived_at' => now()]));
        try {
            $this->tenant($company, fn () => app(CatalogLineDefaults::class)
                ->for($company, $owner, $product->id, 'RON'));
            $this->fail('An archived source was selectable.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $otherProduct = $this->tenant($other, fn (): ProductService => ProductService::query()->create([
            'name' => 'Other company', 'period_unit' => 'NONE',
        ]));
        $this->tenant($company, fn () => $this->assertNull(ProductService::query()->find($otherProduct->id)));
    }

    /** @return array{ProductService, CompanyCurrency, TaxPreset} */
    private function configuredProduct(Company $company): array
    {
        return $this->tenant($company, function (): array {
            $currency = CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
            $tax = TaxPreset::query()->create([
                'name' => 'TVA', 'percentage' => '19', 'is_default' => true,
            ]);
            $product = ProductService::query()->create([
                'name' => 'Consulting', 'description' => 'Detailed work',
                'internal_code' => 'CONSULT', 'unit_price' => '120.50000000',
                'currency_id' => $currency->id, 'unit' => 'hour',
                'period_unit' => 'MONTH', 'tax_preset_id' => $tax->id,
            ]);

            return [$product, $currency, $tax];
        });
    }

    private function companyFor(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, 'Catalog Defaults SRL');
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
