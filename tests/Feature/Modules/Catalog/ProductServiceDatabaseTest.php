<?php

namespace Tests\Feature\Modules\Catalog;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Tests\TestCase;

final class ProductServiceDatabaseTest extends TestCase
{
    use DatabaseMigrations;

    public function test_products_services_are_forced_rls_and_cross_company_hidden(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.products_services'::regclass
            SQL);
        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);
        $this->assertSame(0, DB::connection('pgsql_schema')->table('products_services')->count());

        $product = $this->tenant($companyA, fn (): ProductService => ProductService::query()->create([
            'name' => 'Alpha service',
        ]));
        $this->tenant($companyB, fn () => $this->assertNull(ProductService::query()->find($product->id)));

        $this->expectException(QueryException::class);
        $this->tenant($companyA, fn () => DB::connection('pgsql')->table('products_services')->insert([
            'id' => (string) Str::uuid7(), 'company_id' => $companyB->id,
            'name' => 'Cross Company', 'period_unit' => 'NONE',
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function test_database_rejects_invalid_envelopes_and_unavailable_sources(): void
    {
        $company = $this->company('Checks SRL');
        [$currency, $tax] = $this->tenant($company, fn (): array => [
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]),
            TaxPreset::query()->create([
                'name' => 'TVA', 'percentage' => '19', 'is_default' => true,
            ]),
        ]);

        foreach ([
            ['name' => str_repeat('x', 161)],
            ['name' => 'Missing currency', 'unit_price' => '1'],
            ['name' => 'Negative', 'unit_price' => '-1', 'currency_id' => $currency->id],
        ] as $attributes) {
            try {
                $this->tenant($company, fn () => ProductService::query()->create($attributes));
                $this->fail('Invalid Product/Service data was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            $this->tenant($company, fn () => DB::connection('pgsql')->table('products_services')->insert([
                'id' => (string) Str::uuid7(), 'company_id' => $company->id,
                'name' => 'Bad period', 'period_unit' => 'WEEK',
                'created_at' => now(), 'updated_at' => now(),
            ]));
            $this->fail('An invalid period was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $product = $this->tenant($company, fn (): ProductService => ProductService::query()->create([
            'name' => 'Valid', 'unit_price' => '1.23000000',
            'currency_id' => $currency->id, 'tax_preset_id' => $tax->id,
        ]));
        $this->assertDeferredConstraintFails($company, fn () => $currency->update(['active' => false]));
        $this->assertDeferredConstraintFails($company, fn () => $tax->update(['archived_at' => now()]));
        $this->assertDeferredConstraintFails($company, fn () => $product->update(['unit_price' => '1.23400000']));
    }

    private function assertDeferredConstraintFails(Company $company, Closure $mutation): void
    {
        try {
            $this->tenant($company, $mutation);
            $this->fail('The deferred source-integrity constraint did not fail.');
        } catch (QueryException|PDOException $exception) {
            $this->assertSame('23514', (string) $exception->getCode());
        }
    }

    private function company(string $name): Company
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, $name);
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
