<?php

namespace Tests\Feature\Modules\Quotes;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Actions\UpdateQuoteDraft;
use App\Modules\Quotes\Data\QuoteDraftData;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class QuoteConfigurationLockOrderTest extends TestCase
{
    use DatabaseMigrations;

    public function test_tokenless_update_waits_for_configuration_before_locking_quote(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The lock-order test requires pcntl.');
        }

        $owner = User::factory()->create();
        $company = $this->company($owner);
        $quote = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $directory = sys_get_temp_dir().'/invumo-quote-lock-'.Str::random(12);
        mkdir($directory, 0700);
        $backendFile = "{$directory}/backend";
        $resultFile = "{$directory}/result";
        $this->configureConnection('quote_lock_holder');
        $holder = DB::connection('quote_lock_holder');
        $holder->beginTransaction();
        $this->enterTenant($holder, $company->id);
        $this->assertCount(1, $holder->table('company_settings')->orderBy('id')->lockForUpdate()->get());
        $this->assertCount(1, $holder->table('company_currencies')->orderBy('id')->lockForUpdate()->get());

        $pid = pcntl_fork();

        if ($pid === 0) {
            $this->runBlockedUpdate($company->id, $owner->id, $quote->id, $backendFile, $resultFile);
        }

        $this->assertGreaterThan(0, $pid);
        $backendPid = $this->waitForBackendPid($backendFile);
        $this->waitForLock($backendPid);
        $this->configureConnection('quote_lock_probe');
        $probe = DB::connection('quote_lock_probe');
        $probe->beginTransaction();
        $this->enterTenant($probe, $company->id);
        $locked = $probe->selectOne(
            'SELECT id FROM documents WHERE id = ? FOR UPDATE NOWAIT',
            [$quote->id],
        );
        $this->assertSame($quote->id, $locked->id);
        $probe->rollBack();
        $holder->commit();
        pcntl_waitpid($pid, $status);

        $this->assertTrue(pcntl_wifexited($status));
        $this->assertSame(0, pcntl_wexitstatus($status));
        $this->assertSame('saved', trim((string) file_get_contents($resultFile)));
        unlink($backendFile);
        unlink($resultFile);
        rmdir($directory);
    }

    private function runBlockedUpdate(
        string $companyId,
        string $ownerId,
        string $quoteId,
        string $backendFile,
        string $resultFile,
    ): never {
        foreach (['pgsql', 'pgsql_schema'] as $connection) {
            DB::purge($connection);
        }

        try {
            $backend = DB::connection('pgsql')->selectOne('SELECT pg_backend_pid() AS id');
            file_put_contents($backendFile, (string) $backend->id, LOCK_EX);
            app(UpdateQuoteDraft::class)->handle(
                Company::query()->findOrFail($companyId),
                User::query()->findOrFail($ownerId),
                $quoteId,
                new QuoteDraftData(
                    editVersion: 1, customerId: null, customerConfirmationToken: null,
                    currencyCode: 'RON', documentLanguage: 'en', issueDate: '2026-08-26',
                    validityDays: 30, validUntil: '2026-09-25', customerReference: null,
                    bankAccountId: null, termsAndConditions: null, notes: null, lines: [],
                ),
            );
            file_put_contents($resultFile, 'saved', LOCK_EX);
            exit(0);
        } catch (\Throwable $exception) {
            file_put_contents($resultFile, $exception->getMessage(), LOCK_EX);
            exit(1);
        }
    }

    private function waitForBackendPid(string $file): int
    {
        $deadline = microtime(true) + 5;

        while (! is_file($file) && microtime(true) < $deadline) {
            usleep(1_000);
        }

        $this->assertFileExists($file);

        return (int) file_get_contents($file);
    }

    private function waitForLock(int $backendPid): void
    {
        $deadline = microtime(true) + 5;

        do {
            $activity = DB::connection('pgsql_schema')->selectOne(
                'SELECT EXISTS (SELECT 1 FROM pg_locks WHERE pid = ? AND NOT granted) AS waiting',
                [$backendPid],
            );

            if ($activity?->waiting === true) {
                return;
            }

            usleep(5_000);
        } while (microtime(true) < $deadline);

        $this->fail('The concurrent Quote update did not reach the expected configuration lock.');
    }

    private function configureConnection(string $name): void
    {
        config(["database.connections.{$name}" => config('database.connections.pgsql')]);
        DB::purge($name);
    }

    private function enterTenant($connection, string $companyId): void
    {
        $connection->selectOne("SELECT set_config('app.current_company_id', ?, true)", [$companyId]);
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Lock Order SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest', 'default_document_language' => 'en',
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return $company;
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
