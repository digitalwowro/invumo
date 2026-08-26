<?php

namespace Tests\Feature\Modules\Quotes;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\ConvertQuoteToInvoice;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Data\QuoteConversionData;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class QuoteInvoiceConversionConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_overlapping_retries_create_one_linked_invoice(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The conversion concurrency test requires pcntl.');
        }

        [$company, $owner, $quote] = $this->quote();
        $key = (string) Str::uuid7();
        $directory = sys_get_temp_dir().'/invumo-conversion-'.Str::random(12);
        mkdir($directory, 0700);
        $barrier = "{$directory}/start";
        $children = [];

        foreach ([1, 2] as $slot) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->runConversion(
                    $company->id, $owner->id, $quote->id, $key,
                    $barrier, "{$directory}/{$slot}",
                );
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

        $ids = [
            trim((string) file_get_contents("{$directory}/1")),
            trim((string) file_get_contents("{$directory}/2")),
        ];
        $this->assertSame($ids[0], $ids[1]);
        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $this->assertSame(1, QuoteInvoiceLink::query()->count());
            $this->assertSame(2, Document::query()->count());
            Quote::query()->update(['lifecycle' => QuoteLifecycle::Draft]);
        });

        foreach (["{$directory}/1", "{$directory}/2", $barrier] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    private function runConversion(
        string $companyId,
        string $ownerId,
        string $quoteId,
        string $key,
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
            $invoice = app(ConvertQuoteToInvoice::class)->handle(
                Company::query()->findOrFail($companyId),
                User::query()->findOrFail($ownerId),
                $quoteId,
                new QuoteConversionData($key, false),
            );
            file_put_contents($result, $invoice->id, LOCK_EX);
            exit(0);
        } catch (\Throwable $exception) {
            file_put_contents($result, $exception->getMessage(), LOCK_EX);
            exit(1);
        }
    }

    /** @return array{Company, User, Document} */
    private function quote(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Concurrency SRL');
        app(TenantContext::class)->runAsSystem($company->id, fn () => CompanySetting::query()
            ->firstOrFail()->update(['timezone' => 'Europe/Bucharest']));
        $quote = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
        app(TenantContext::class)->runAsSystem($company->id, fn () => Quote::query()
            ->whereKey($quote->id)->update(['lifecycle' => QuoteLifecycle::Accepted]));

        return [$company, $owner, $quote];
    }
}
