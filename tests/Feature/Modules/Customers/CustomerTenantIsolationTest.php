<?php

namespace Tests\Feature\Modules\Customers;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CustomerTenantIsolationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_customers_are_forced_rls_and_default_deny(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.customers'::regclass
            SQL);

        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);
        $this->assertSame(0, DB::connection('pgsql_schema')->table('customers')->count());

        $customerId = $this->tenant($companyA, function (): string {
            return Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Alpha Customer SRL',
            ])->id;
        });
        $this->tenant($companyB, fn () => $this->assertNull(Customer::query()->find($customerId)));
    }

    public function test_runtime_cannot_insert_a_customer_for_another_company(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $this->expectException(QueryException::class);

        $this->tenant($companyA, function () use ($companyB): void {
            DB::connection('pgsql')->table('customers')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $companyB->id,
                'type' => 'COMPANY',
                'legal_name' => 'Cross Company Customer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    #[DataProvider('invalidCustomers')]
    public function test_database_rejects_invalid_customer_envelopes(array $attributes): void
    {
        $company = $this->company('Alpha SRL');
        $this->expectException(QueryException::class);

        $this->tenant($company, fn () => DB::connection('pgsql')->table('customers')->insert([
            'id' => (string) Str::uuid7(),
            'company_id' => $company->id,
            'type' => 'COMPANY',
            'legal_name' => 'Customer SRL',
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidCustomers(): array
    {
        return [
            'company without legal name' => [[
                'legal_name' => null,
            ]],
            'company with individual name' => [[
                'first_name' => 'Ada',
            ]],
            'individual without last name' => [[
                'type' => 'INDIVIDUAL', 'legal_name' => null, 'first_name' => 'Ada',
            ]],
            'unpaired tax registration' => [[
                'tax_registration_label' => 'CUI',
            ]],
            'unsupported country shape' => [[
                'country_code' => 'rou',
            ]],
            'oversized internal notes' => [[
                'internal_notes' => str_repeat('x', 5001),
            ]],
        ];
    }

    private function company(string $name): Company
    {
        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create(['owner_user_id' => $owner->id, 'plan_id' => $plan->id]);

        return app(CreateCompany::class)->handle($account, $owner, $name);
    }

    private function tenant(Company $company, callable $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
