<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CompanyConfigurationTenantIsolationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_configuration_tables_are_forced_rls_and_default_deny(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $context = app(TenantContext::class);

        foreach (['company_settings', 'company_currencies'] as $table) {
            $rls = DB::connection('pgsql_schema')->selectOne(<<<SQL
                SELECT relrowsecurity, relforcerowsecurity
                FROM pg_class
                WHERE oid = 'public.{$table}'::regclass
                SQL);

            $this->assertTrue($rls->relrowsecurity);
            $this->assertTrue($rls->relforcerowsecurity);
            $this->assertSame(0, DB::connection('pgsql_schema')->table($table)->count());
        }

        $context->runAsSystem($companyA->id, function (): void {
            $this->assertSame(1, CompanySetting::query()->count());
            $this->assertSame('Alpha SRL', CompanySetting::query()->firstOrFail()->legal_name);
        });
        $context->runAsSystem($companyB->id, function (): void {
            $this->assertSame(1, CompanySetting::query()->count());
            $this->assertSame('Beta SRL', CompanySetting::query()->firstOrFail()->legal_name);
        });
    }

    public function test_runtime_cannot_insert_a_currency_for_another_company(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');

        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($companyA->id, function () use ($companyB): void {
            DB::connection('pgsql')->table('company_currencies')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $companyB->id,
                'currency_code' => 'EUR',
                'currency_precision' => 2,
                'is_default' => true,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_database_allows_only_one_active_default_currency_per_company(): void
    {
        $company = $this->company('Alpha SRL');

        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            CompanyCurrency::query()->create([
                'currency_code' => 'RON',
                'currency_precision' => 2,
                'is_default' => true,
                'active' => true,
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'EUR',
                'currency_precision' => 2,
                'is_default' => true,
                'active' => true,
            ]);
        });
    }

    private function company(string $name): Company
    {
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        return app(CreateCompany::class)->handle($account, $user, $name);
    }
}
