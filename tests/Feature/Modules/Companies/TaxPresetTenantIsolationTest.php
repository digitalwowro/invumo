<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TaxPresetTenantIsolationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_tax_presets_are_forced_rls_and_default_deny(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.tax_presets'::regclass
            SQL);

        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);
        $this->assertSame(0, DB::connection('pgsql_schema')->table('tax_presets')->count());

        app(TenantContext::class)->runAsSystem($companyA->id, function (): void {
            TaxPreset::query()->create([
                'name' => 'Alpha tax', 'percentage' => '19', 'is_default' => false,
            ]);
            $this->assertSame(1, TaxPreset::query()->count());
        });
        app(TenantContext::class)->runAsSystem(
            $companyB->id,
            fn () => $this->assertSame(0, TaxPreset::query()->count()),
        );
    }

    public function test_runtime_cannot_write_a_tax_preset_for_another_company(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($companyA->id, function () use ($companyB): void {
            DB::connection('pgsql')->table('tax_presets')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $companyB->id,
                'name' => 'Cross-company tax',
                'percentage' => '19.000000',
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_database_allows_only_one_non_archived_default(): void
    {
        $company = $this->company('Alpha SRL');
        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            TaxPreset::query()->create([
                'name' => 'Standard', 'percentage' => '19', 'is_default' => true,
            ]);
            TaxPreset::query()->create([
                'name' => 'Reduced', 'percentage' => '9', 'is_default' => true,
            ]);
        });
    }

    public function test_database_rejects_an_archived_default(): void
    {
        $company = $this->company('Alpha SRL');
        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            TaxPreset::query()->create([
                'name' => 'Invalid archived default',
                'percentage' => '19',
                'is_default' => true,
                'archived_at' => now(),
            ]);
        });
    }

    private function company(string $name): Company
    {
        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => $plan->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, $name);
    }
}
