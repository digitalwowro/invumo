<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BankAccountTenantIsolationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_bank_accounts_are_forced_rls_and_default_deny(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.bank_accounts'::regclass
            SQL);

        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);
        $this->assertSame(0, DB::connection('pgsql_schema')->table('bank_accounts')->count());

        app(TenantContext::class)->runAsSystem($companyA->id, function (): void {
            BankAccount::query()->create($this->values());
            $this->assertSame(1, BankAccount::query()->count());
        });
        app(TenantContext::class)->runAsSystem(
            $companyB->id,
            fn () => $this->assertSame(0, BankAccount::query()->count()),
        );
    }

    public function test_runtime_cannot_write_a_bank_account_for_another_company(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($companyA->id, function () use ($companyB): void {
            DB::connection('pgsql')->table('bank_accounts')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $companyB->id,
                ...$this->values(),
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_same_company_currency_reference_is_enforced(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $currencyB = app(TenantContext::class)->runAsSystem(
            $companyB->id,
            fn (): CompanyCurrency => $this->currency('EUR'),
        );
        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($companyA->id, function () use ($currencyB): void {
            BankAccount::query()->create([
                ...$this->values(), 'currency_id' => $currencyB->id,
            ]);
        });
    }

    public function test_database_rejects_invalid_routing_json_shapes_and_values(): void
    {
        $company = $this->company('Alpha SRL');
        $invalid = [
            ['unknown' => 'value'],
            ['routing_number' => ['nested']],
            ['routing_number' => str_repeat('1', 65)],
        ];

        foreach ($invalid as $routing) {
            try {
                app(TenantContext::class)->runAsSystem(
                    $company->id,
                    fn (): BankAccount => BankAccount::query()->create([
                        ...$this->values(), 'local_routing_details' => $routing,
                    ]),
                );
                $this->fail('Invalid local routing details were accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_database_allows_missing_swift_bic_but_rejects_malformed_values(): void
    {
        $company = $this->company('Alpha SRL');
        $account = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): BankAccount => BankAccount::query()->create([
                ...$this->values(), 'swift_bic' => null,
            ]),
        );
        $this->assertNull($account->swift_bic);

        $this->expectException(QueryException::class);
        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): BankAccount => BankAccount::query()->create([
                ...$this->values('Invalid'), 'swift_bic' => 'INVALID',
            ]),
        );
    }

    public function test_database_allows_only_one_active_default_and_no_archived_default(): void
    {
        $company = $this->company('Alpha SRL');

        try {
            app(TenantContext::class)->runAsSystem($company->id, function (): void {
                BankAccount::query()->create([...$this->values('One'), 'is_default' => true]);
                BankAccount::query()->create([...$this->values('Two'), 'is_default' => true]);
            });
            $this->fail('A second active default was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);
        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): BankAccount => BankAccount::query()->create([
                ...$this->values('Archived'),
                'is_default' => true,
                'archived_at' => now(),
            ]),
        );
    }

    public function test_runtime_has_only_the_required_bank_validation_function_privilege(): void
    {
        $privilege = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT
                has_function_privilege(
                    'invumo_runtime',
                    'public.invumo_bank_routing_details_valid(jsonb)',
                    'EXECUTE'
                ) AS runtime_execute,
                has_function_privilege(
                    'public',
                    'public.invumo_bank_routing_details_valid(jsonb)',
                    'EXECUTE'
                ) AS public_execute
            SQL);

        $this->assertTrue($privilege->runtime_execute);
        $this->assertFalse($privilege->public_execute);
    }

    private function company(string $name): Company
    {
        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id, 'plan_id' => $plan->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, $name);
    }

    private function currency(string $code): CompanyCurrency
    {
        return CompanyCurrency::query()->create([
            'currency_code' => $code,
            'currency_precision' => 2,
            'is_default' => true,
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function values(string $label = 'Main'): array
    {
        return [
            'label' => $label,
            'bank_name' => 'Bank',
            'account_holder' => 'Holder',
            'account_number' => "ACCOUNT-{$label}",
            'swift_bic' => 'AAAAROBUXXX',
        ];
    }
}
