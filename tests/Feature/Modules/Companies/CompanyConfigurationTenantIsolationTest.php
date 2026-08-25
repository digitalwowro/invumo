<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Documents\DocumentFieldLimits;
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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_runtime_cannot_read_or_update_another_company_document_defaults(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $settingsBId = app(TenantContext::class)->runAsSystem(
            $companyB->id,
            fn (): string => CompanySetting::query()->firstOrFail()->id,
        );

        app(TenantContext::class)->runAsSystem($companyA->id, function () use ($settingsBId): void {
            $this->assertNull(CompanySetting::query()->find($settingsBId));
            $this->assertSame(0, CompanySetting::query()->whereKey($settingsBId)->update([
                'default_document_language' => 'ro',
            ]));
        });

        app(TenantContext::class)->runAsSystem($companyB->id, function (): void {
            $this->assertNull(CompanySetting::query()->firstOrFail()->default_document_language);
        });
    }

    public function test_database_rejects_malformed_default_document_language(): void
    {
        $company = $this->company('Alpha SRL');

        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => CompanySetting::query()->firstOrFail()->update([
                'default_document_language' => '../de',
            ]),
        );
    }

    public function test_database_rejects_negative_document_day_defaults(): void
    {
        $company = $this->company('Alpha SRL');

        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => CompanySetting::query()->firstOrFail()->update([
                'default_payment_term_days' => -1,
            ]),
        );
    }

    public function test_database_rejects_negative_quote_validity_days(): void
    {
        $company = $this->company('Alpha SRL');

        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => CompanySetting::query()->firstOrFail()->update([
                'default_quote_validity_days' => -1,
            ]),
        );
    }

    #[DataProvider('outOfBoundsDocumentDefaults')]
    public function test_database_rejects_document_defaults_outside_the_domain_envelope(
        string $field,
        int|string $value,
    ): void {
        $company = $this->company('Alpha SRL');

        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => CompanySetting::query()->firstOrFail()->update([$field => $value]),
        );
    }

    public function test_document_day_offsets_use_integer_storage(): void
    {
        $columns = DB::connection('pgsql_schema')
            ->table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'company_settings')
            ->whereIn('column_name', [
                'default_payment_term_days',
                'default_quote_validity_days',
            ])
            ->pluck('data_type', 'column_name');

        $this->assertSame('integer', $columns['default_payment_term_days']);
        $this->assertSame('integer', $columns['default_quote_validity_days']);
    }

    /** @return array<string, array{string, int|string}> */
    public static function outOfBoundsDocumentDefaults(): array
    {
        return [
            'payment term exceeds the application date range' => [
                'default_payment_term_days',
                DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET + 1,
            ],
            'quote validity exceeds the application date range' => [
                'default_quote_validity_days',
                DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET + 1,
            ],
            'Terms and Conditions exceed the approved renderer envelope' => [
                'default_terms_and_conditions',
                str_repeat('x', DocumentFieldLimits::TERMS_AND_CONDITIONS_CHARACTERS + 1),
            ],
            'Quote notes exceed the approved renderer envelope' => [
                'default_quote_notes',
                str_repeat('x', DocumentFieldLimits::NOTES_CHARACTERS + 1),
            ],
            'Invoice notes exceed the approved renderer envelope' => [
                'default_invoice_notes',
                str_repeat('x', DocumentFieldLimits::NOTES_CHARACTERS + 1),
            ],
        ];
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
