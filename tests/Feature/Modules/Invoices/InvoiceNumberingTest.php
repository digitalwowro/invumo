<?php

namespace Tests\Feature\Modules\Invoices;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InvoiceNumberingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_overlapping_invoice_creations_receive_distinct_numbers(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The concurrency test requires pcntl.');
        }

        $owner = User::factory()->create();
        $company = $this->company($owner);
        $directory = sys_get_temp_dir().'/invumo-invoice-numbering-'.Str::random(12);
        mkdir($directory, 0700);
        $barrier = "{$directory}/start";
        $children = [];

        foreach ([1, 2] as $slot) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->runCreation($company->id, $owner->id, $barrier, "{$directory}/{$slot}");
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

        $numbers = [
            trim((string) file_get_contents("{$directory}/1")),
            trim((string) file_get_contents("{$directory}/2")),
        ];
        sort($numbers);
        $this->assertSame(['I-2026-0001', 'I-2026-0002'], $numbers);
        foreach (["{$directory}/1", "{$directory}/2", $barrier] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    private function runCreation(
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
            $invoice = app(CreateInvoiceDraft::class)->handle(
                Company::query()->findOrFail($companyId),
                User::query()->findOrFail($ownerId),
                (string) Str::uuid7(),
            );
            file_put_contents($result, $invoice->rendered_number, LOCK_EX);
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
        $company = app(CreateCompany::class)->handle($account, $owner, 'Invoice Numbering SRL');
        app(TenantContext::class)->runAsSystem($company->id, fn () => CompanySetting::query()
            ->firstOrFail()
            ->update(['timezone' => 'Europe/Bucharest']));

        return $company;
    }
}
