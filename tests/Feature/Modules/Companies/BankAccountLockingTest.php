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
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BankAccountLockingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_default_reassignment_locks_currencies_then_accounts_in_uuid_order(): void
    {
        [$owner, $company] = $this->company();
        [$currency, $reserve] = app(TenantContext::class)->runAsSystem(
            $company->id,
            function (): array {
                $currency = CompanyCurrency::query()->create([
                    'currency_code' => 'RON', 'currency_precision' => 2,
                    'is_default' => true, 'active' => true,
                ]);
                BankAccount::query()->create([
                    ...$this->storedPayload('Main'), 'is_default' => true,
                ]);
                $reserve = BankAccount::query()->create([
                    ...$this->storedPayload('Reserve'), 'is_default' => false,
                ]);

                return [$currency, $reserve];
            },
        );
        $queries = [];
        DB::connection(config('database.tenant_connection'))->listen(
            static function (QueryExecuted $query) use (&$queries): void {
                $queries[] = $query->sql;
            },
        );
        $this->actingAs($owner)->patch(
            route('company-bank-accounts.update', [$company, $reserve]),
            [...$this->payload('Reserve'), 'currency_id' => $currency->id, 'is_default' => true],
        )->assertRedirect();

        $currencyLock = $this->queryIndex($queries, 'from "company_currencies"', 'for update');
        $accountLock = $this->queryIndex($queries, 'from "bank_accounts"', 'for update');
        $mutation = $this->queryIndex($queries, 'update "bank_accounts"');
        $this->assertStringContainsString('order by "id" asc', $queries[$currencyLock]);
        $this->assertStringContainsString('order by "id" asc', $queries[$accountLock]);
        $this->assertLessThan($accountLock, $currencyLock);
        $this->assertLessThan($mutation, $accountLock);
    }

    public function test_archiving_also_locks_the_full_account_set_in_uuid_order(): void
    {
        [$owner, $company] = $this->company();
        $account = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): BankAccount => BankAccount::query()->create([
                ...$this->storedPayload('Main'), 'is_default' => true,
            ]),
        );
        $queries = [];
        DB::connection(config('database.tenant_connection'))->listen(
            static function (QueryExecuted $query) use (&$queries): void {
                $queries[] = $query->sql;
            },
        );

        $this->actingAs($owner)->patch(
            route('company-bank-accounts.archive', [$company, $account]),
        )->assertRedirect();
        $lock = $this->queryIndex($queries, 'from "bank_accounts"', 'for update');
        $mutation = $this->queryIndex($queries, 'update "bank_accounts"');
        $this->assertStringContainsString('order by "id" asc', $queries[$lock]);
        $this->assertLessThan($mutation, $lock);
    }

    /** @param list<string> $queries */
    private function queryIndex(array $queries, string ...$needles): int
    {
        foreach ($queries as $index => $query) {
            if (array_all($needles, fn (string $needle): bool => str_contains($query, $needle))) {
                return $index;
            }
        }

        $this->fail('Expected query was not recorded.');
    }

    /** @return array{User, Company} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id, 'plan_id' => $plan->id,
        ]);

        return [$owner, app(CreateCompany::class)->handle($account, $owner, 'Acme SRL')];
    }

    /** @return array<string, mixed> */
    private function payload(string $label): array
    {
        return [
            ...$this->storedPayload($label),
            'swift_bic' => 'AAAAROBUXXX',
            'local_routing_details' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function storedPayload(string $label): array
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
