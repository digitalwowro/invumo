<?php

namespace Tests\Feature\Modules\Customers;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class CustomerDefaultsDatabaseTest extends TestCase
{
    use DatabaseMigrations;

    public function test_database_rejects_cross_company_defaults_and_invalid_typed_values(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstCompany = $this->companyFor($firstOwner);
        $secondCompany = $this->companyFor($secondOwner);
        $customer = $this->tenant($firstCompany, fn (): Customer => Customer::query()->create([
            'type' => 'COMPANY', 'legal_name' => 'First Customer SRL',
        ]));
        [$foreignCurrency, $foreignTax] = $this->tenant($secondCompany, fn (): array => [
            CompanyCurrency::query()->create([
                'currency_code' => 'EUR', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]),
            TaxPreset::query()->create([
                'name' => 'VAT', 'percentage' => '19', 'is_default' => true,
            ]),
        ]);

        $this->assertDatabaseCheckFailure($firstCompany, fn () => Customer::query()
            ->whereKey($customer->id)->firstOrFail()
            ->update(['currency_id' => $foreignCurrency->id]), '23503');
        $this->assertDatabaseCheckFailure($firstCompany, fn () => Customer::query()
            ->whereKey($customer->id)->firstOrFail()
            ->update(['tax_preset_id' => $foreignTax->id]), '23503');
        $this->assertDatabaseCheckFailure($firstCompany, fn () => Customer::query()
            ->whereKey($customer->id)->firstOrFail()
            ->update(['document_language' => 'invalid locale']), '23514');
        $this->assertDatabaseCheckFailure($firstCompany, fn () => Customer::query()
            ->whereKey($customer->id)->firstOrFail()
            ->update(['payment_term_days' => 3_652_059]), '23514');
    }

    public function test_defaults_route_and_rls_hide_another_company_customer(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstCompany = $this->companyFor($firstOwner);
        $secondCompany = $this->companyFor($secondOwner);
        $foreignCustomer = $this->tenant(
            $secondCompany,
            fn (): Customer => Customer::query()->create([
                'type' => 'INDIVIDUAL', 'first_name' => 'Other', 'last_name' => 'Customer',
            ]),
        );

        $this->actingAs($firstOwner)
            ->get(route('customer-defaults.index', [$firstCompany, $foreignCustomer]))
            ->assertNotFound();
        $this->assertNull($this->tenant(
            $firstCompany,
            fn () => Customer::query()->find($foreignCustomer->id),
        ));
    }

    public function test_database_rejects_making_referenced_default_sources_unavailable(): void
    {
        $owner = User::factory()->create();
        $company = $this->companyFor($owner);
        [$customer, $currency, $taxPreset] = $this->tenant($company, function (): array {
            $currency = CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
            $taxPreset = TaxPreset::query()->create([
                'name' => 'VAT', 'percentage' => '19', 'is_default' => true,
            ]);
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Protected Defaults SRL',
                'currency_id' => $currency->id, 'tax_preset_id' => $taxPreset->id,
            ]);

            return [$customer, $currency, $taxPreset];
        });

        $this->assertDatabaseCheckFailure(
            $company,
            fn () => TaxPreset::query()->findOrFail($taxPreset->id)->update(['archived_at' => now()]),
            '23514',
        );
        $this->assertDatabaseCheckFailure(
            $company,
            fn () => CompanyCurrency::query()->findOrFail($currency->id)->update(['active' => false]),
            '23514',
        );
        $this->tenant($company, function () use ($customer, $currency, $taxPreset): void {
            $this->assertSame($currency->id, Customer::query()->findOrFail($customer->id)->currency_id);
            $this->assertSame($taxPreset->id, Customer::query()->findOrFail($customer->id)->tax_preset_id);
        });
    }

    /** @param Closure(): mixed $operation */
    private function assertDatabaseCheckFailure(
        Company $company,
        Closure $operation,
        string $sqlState,
    ): void {
        try {
            $this->tenant($company, $operation);
            $this->fail('The database accepted an invalid Customer default.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->getCode());
        }
    }

    private function companyFor(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, 'Database Test SRL');
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
