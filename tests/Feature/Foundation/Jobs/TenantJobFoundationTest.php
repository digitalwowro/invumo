<?php

namespace Tests\Feature\Foundation\Jobs;

use App\Foundation\Jobs\JobIdentity;
use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Jobs\SendCompanyInvitation;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\Support\Jobs\TenantProbeJob;
use Tests\TestCase;

class TenantJobFoundationTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        TenantProbeJob::$observations = [];
        parent::tearDown();
    }

    public function test_job_identity_is_stable_and_rejects_unsafe_values(): void
    {
        $companyId = (string) Str::uuid();
        $identity = new JobIdentity($companyId, 'invoice:2026:42', 'invoice.delivery');

        $this->assertSame($identity->uniqueHash(), $identity->uniqueHash());
        $this->assertNotSame(
            $identity->uniqueHash(),
            (new JobIdentity((string) Str::uuid(), 'invoice:2026:42', 'invoice.delivery'))
                ->uniqueHash(),
        );

        foreach ([
            ['not-a-uuid', 'invoice:42', 'invoice.delivery'],
            [$companyId, 'contains spaces', 'invoice.delivery'],
            [$companyId, 'invoice:42', 'not_namespaced'],
        ] as [$company, $key, $component]) {
            try {
                new JobIdentity($company, $key, $component);
                $this->fail('Unsafe tenant job identity input must be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_job_contract_enforces_tenant_context_without_leaking_between_jobs(): void
    {
        $companyA = (string) Str::uuid();
        $companyB = (string) Str::uuid();
        $job = new TenantProbeJob($companyA, $companyA);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame([60, 300, 900, 3600, 21600], $job->backoff());
        $this->assertSame(6, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertSame(604800, $job->uniqueFor);
        $this->assertFalse(method_exists($job, 'retryUntil'));

        Bus::dispatchSync($job);
        Bus::dispatchSync(new TenantProbeJob($companyB, $companyB));

        $this->assertSame([null, $companyA, null, null, $companyB, null], TenantProbeJob::$observations);
        app(TenantContext::class)->assertClear();
    }

    public function test_job_cannot_enter_another_company_and_context_is_cleared_after_failure(): void
    {
        $companyA = (string) Str::uuid();
        $companyB = (string) Str::uuid();

        try {
            Bus::dispatchSync(new TenantProbeJob($companyA, $companyB));
            $this->fail('A tenant job must not switch to another Company.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'A tenant job cannot enter another Company context.',
                $exception->getMessage(),
            );
        }

        app(TenantContext::class)->assertClear();
        $this->addToAssertionCount(1);
    }

    public function test_operational_logs_record_outcomes_without_payload_or_exception_values(): void
    {
        $contexts = [];
        Log::shouldReceive('info')->andReturnUsing(
            function (string $event, array $context) use (&$contexts): void {
                $this->assertSame('queue.tenant_job', $event);
                $contexts[] = $context;
            },
        );
        $execution = app(TenantJobExecution::class);
        $identity = new JobIdentity(
            (string) Str::uuid(),
            'customer-secret-reference',
            'test.tenant_job',
        );

        $execution->run($identity, (string) Str::uuid(), 1, 6, fn () => null);
        $execution->run($identity, (string) Str::uuid(), 1, 6, fn () => $execution->skip('stale'));

        foreach ([1, 6] as $attempt) {
            try {
                $execution->run(
                    $identity,
                    (string) Str::uuid(),
                    $attempt,
                    6,
                    fn () => throw new RuntimeException('token is top-secret'),
                );
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(
            ['started', 'succeeded', 'started', 'skipped', 'started', 'retrying', 'started', 'failed'],
            array_column($contexts, 'outcome'),
        );
        $serialized = json_encode($contexts, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($identity->companyId, $serialized);
        $this->assertStringNotContainsString($identity->idempotencyKey, $serialized);
        $this->assertStringNotContainsString('top-secret', $serialized);
    }

    public function test_database_queue_is_unique_encrypted_and_rolls_back_atomically(): void
    {
        $companyId = (string) Str::uuid();
        $invitationId = (string) Str::uuid();
        $token = 'plain-token-that-must-remain-secret';

        $this->dispatchInvitation($companyId, $invitationId, $token);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('cache_locks')->count());
        $this->dispatchInvitation($companyId, $invitationId, $token);

        $this->assertSame(1, DB::table('jobs')->count());
        $payload = (string) DB::table('jobs')->value('payload');
        $this->assertStringNotContainsString($companyId, $payload);
        $this->assertStringNotContainsString($invitationId, $payload);
        $this->assertStringNotContainsString($token, $payload);

        $rolledInvitationId = (string) Str::uuid();

        try {
            DB::connection(config('database.tenant_connection'))->transaction(function () use (
                $companyId,
                $rolledInvitationId,
            ): void {
                $this->dispatchInvitation($companyId, $rolledInvitationId, 'rolled-back-secret');
                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, DB::table('jobs')->count());
        $this->dispatchInvitation($companyId, $rolledInvitationId, 'rolled-back-secret');
        $this->assertSame(2, DB::table('jobs')->count());
    }

    private function dispatchInvitation(string $companyId, string $invitationId, string $token): void
    {
        SendCompanyInvitation::dispatch(
            companyId: $companyId,
            invitationId: $invitationId,
            plainTextToken: $token,
            locale: 'en',
        )->onConnection('database')->onQueue('default');
    }
}
