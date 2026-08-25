<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\NumberSeries;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NumberSeriesTenantIsolationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_number_series_are_forced_rls_and_default_deny(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.number_series'::regclass
            SQL);

        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);
        $this->assertSame(0, DB::connection('pgsql_schema')->table('number_series')->count());

        app(TenantContext::class)->runAsSystem(
            $companyA->id,
            fn () => $this->assertSame(2, NumberSeries::query()->count()),
        );
        app(TenantContext::class)->runAsSystem(
            $companyB->id,
            fn () => $this->assertSame(2, NumberSeries::query()->count()),
        );
    }

    public function test_runtime_cannot_write_a_series_for_another_company(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($companyA->id, function () use ($companyB): void {
            DB::connection('pgsql')->table('number_series')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $companyB->id,
                'document_type' => 'QUOTE',
                'format_pattern' => 'Q-{NUMBER}',
                'padding' => 4,
                'reset_policy' => 'NEVER',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_runtime_cannot_delete_series_history(): void
    {
        $company = $this->company('Alpha SRL');
        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => NumberSeries::query()->firstOrFail()->delete(),
        );
    }

    public function test_database_allows_only_one_active_series_per_document_type(): void
    {
        $company = $this->company('Alpha SRL');
        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            NumberSeries::query()->create([
                'document_type' => 'QUOTE',
                'format_pattern' => 'O-{NUMBER}',
                'padding' => 4,
                'reset_policy' => 'NEVER',
            ]);
        });
    }

    #[DataProvider('invalidSeriesValues')]
    public function test_database_rejects_invalid_series_values(
        string $pattern,
        int $padding,
        string $resetPolicy,
    ): void {
        $company = $this->company('Alpha SRL');
        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($company->id, function () use (
            $company,
            $pattern,
            $padding,
            $resetPolicy,
        ): void {
            DB::connection('pgsql')->table('number_series')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $company->id,
                'document_type' => 'QUOTE',
                'format_pattern' => $pattern,
                'padding' => $padding,
                'reset_policy' => $resetPolicy,
                'retired_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** @return array<string, array{string, int, string}> */
    public static function invalidSeriesValues(): array
    {
        return [
            'missing number token' => ['Q-{YEAR}', 4, 'NEVER'],
            'duplicate number token' => ['Q-{NUMBER}-{NUMBER}', 4, 'NEVER'],
            'duplicate year token' => ['Q-{YEAR}-{YEAR}-{NUMBER}', 4, 'NEVER'],
            'unknown token' => ['Q-{MONTH}-{NUMBER}', 4, 'NEVER'],
            'control character' => ["Q-\n-{NUMBER}", 4, 'NEVER'],
            'oversized padding' => ['Q-{NUMBER}', 13, 'NEVER'],
            'unknown reset policy' => ['Q-{NUMBER}', 4, 'MONTHLY'],
            'annual reset without year token' => ['Q-{NUMBER}', 4, 'ANNUAL'],
        ];
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
