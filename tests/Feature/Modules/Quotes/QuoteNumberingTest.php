<?php

namespace Tests\Feature\Modules\Quotes;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Documents\Models\NumberCounter;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class QuoteNumberingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_realigns_with_history_confirmation_while_member_is_denied(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->company($owner);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $counter = $this->tenant($company, fn (): NumberCounter => NumberCounter::query()->sole());
        $url = route('company-number-counters.update', [$company, $counter]);

        $this->actingAs($admin)->patch($url, [
            'next_value' => 10,
            'reason' => 'Reserve the next range',
            'confirmed_reuse' => false,
        ])->assertRedirect();

        $this->actingAs($owner)->patch($url, [
            'next_value' => 1,
            'reason' => 'Correcting a duplicate import',
            'confirmed_reuse' => false,
        ])->assertSessionHasErrors('next_value');
        $this->patch($url, [
            'next_value' => 1,
            'reason' => 'Correcting a duplicate import',
            'confirmed_reuse' => true,
        ])->assertRedirect()->assertSessionHas('status');

        $this->tenant($company, function (): void {
            $this->assertSame(1, NumberCounter::query()->sole()->next_value);
            $audit = AuditEvent::query()
                ->where('action', 'company.quote_number_counter.realigned')
                ->where('reason', 'Correcting a duplicate import')->sole();
            $this->assertSame('Correcting a duplicate import', $audit->reason);
            $this->assertSame(['next_value'], array_keys($audit->before));
            $this->assertEqualsCanonicalizing(
                ['next_value', 'reuse_warning'],
                array_keys($audit->after),
            );
        });

        $this->actingAs($member)->patch($url, [
            'next_value' => 10,
            'reason' => 'Forbidden',
            'confirmed_reuse' => false,
        ])->assertForbidden();
    }

    public function test_allocator_skips_a_live_number_after_confirmed_backward_realignment(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $first = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $counter = $this->tenant($company, fn (): NumberCounter => NumberCounter::query()->sole());
        $this->actingAs($owner)->patch(route('company-number-counters.update', [$company, $counter]), [
            'next_value' => 1,
            'reason' => 'Test realignment',
            'confirmed_reuse' => true,
        ])->assertRedirect();

        $second = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());

        $this->assertSame('Q-2026-0001', $first->rendered_number);
        $this->assertSame('Q-2026-0002', $second->rendered_number);
        $this->tenant($company, fn () => $this->assertSame(3, NumberCounter::query()->sole()->next_value));
    }

    public function test_overlapping_creations_receive_distinct_numbers(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The concurrency test requires pcntl.');
        }

        $owner = User::factory()->create();
        $company = $this->company($owner);
        $directory = sys_get_temp_dir().'/invumo-numbering-'.Str::random(12);
        mkdir($directory, 0700);
        $barrier = "{$directory}/start";
        $children = [];

        foreach ([1, 2] as $slot) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->runConcurrentCreation($company->id, $owner->id, $barrier, "{$directory}/{$slot}");
            }

            $this->assertGreaterThan(0, $pid);
            $children[] = $pid;
        }

        touch($barrier);
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        $numbers = [trim((string) file_get_contents("{$directory}/1")), trim((string) file_get_contents("{$directory}/2"))];
        sort($numbers);
        $this->assertSame(['Q-2026-0001', 'Q-2026-0002'], $numbers);
        foreach (["{$directory}/1", "{$directory}/2", $barrier] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    private function runConcurrentCreation(
        string $companyId,
        string $ownerId,
        string $barrier,
        string $result,
    ): never {
        DB::purge('pgsql');
        DB::purge('pgsql_schema');
        $deadline = microtime(true) + 5;

        while (! is_file($barrier) && microtime(true) < $deadline) {
            usleep(1000);
        }

        try {
            $quote = app(CreateQuoteDraft::class)->handle(
                Company::query()->findOrFail($companyId),
                User::query()->findOrFail($ownerId),
                (string) Str::uuid7(),
            );
            file_put_contents($result, $quote->rendered_number, LOCK_EX);
            exit(0);
        } catch (\Throwable) {
            exit(1);
        }
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Numbering SRL');
        $this->tenant($company, fn () => CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
        ]));

        return $company;
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
