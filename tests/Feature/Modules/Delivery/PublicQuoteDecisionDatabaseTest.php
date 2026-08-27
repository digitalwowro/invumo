<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Quotes\Actions\DecidePublicQuote;
use App\Modules\Quotes\Actions\DeleteQuote;
use App\Modules\Quotes\Data\PublicQuoteDecision;
use App\Modules\Quotes\Data\PublicQuoteDecisionData;
use App\Modules\Quotes\Data\QuoteDeletionData;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuotePublicDecision;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\PublicDocumentTestCase;

final class PublicQuoteDecisionDatabaseTest extends PublicDocumentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-27 12:00:00 Europe/Bucharest');
    }

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
            ->runAsSystem($companyId, fn () => Quote::query()->update([
                'lifecycle' => QuoteLifecycle::Draft,
            ])));
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_decisions_are_forced_rls_company_scoped_and_runtime_immutable(): void
    {
        [$ownerA, $companyA] = $this->company('Alpha Decisions SRL');
        [$ownerB, $companyB] = $this->company('Beta Decisions SRL');
        [$quoteA, $tokenA] = $this->sentQuoteAndToken($companyA, $ownerA);
        [$quoteB, $tokenB] = $this->sentQuoteAndToken($companyB, $ownerB);
        $this->decide($tokenA, PublicQuoteDecision::Accepted);
        $this->decide($tokenB, PublicQuoteDecision::Rejected);

        $this->assertSame(0, DB::connection('pgsql_schema')->table('quote_public_decisions')->count());
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.quote_public_decisions'::regclass
            SQL);
        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);
        $this->assertSame([$quoteA->id], $this->tenant(
            $companyA,
            fn (): array => QuotePublicDecision::query()->pluck('quote_id')->all(),
        ));
        $this->assertSame([$quoteB->id], $this->tenant(
            $companyB,
            fn (): array => QuotePublicDecision::query()->pluck('quote_id')->all(),
        ));

        foreach (['update', 'delete'] as $operation) {
            try {
                $this->tenant($companyA, fn () => $operation === 'update'
                    ? QuotePublicDecision::query()->update(['customer_name' => 'Changed'])
                    : QuotePublicDecision::query()->delete());
                $this->fail("Runtime {$operation} unexpectedly changed an immutable decision.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_database_rejects_invalid_or_cross_company_decisions(): void
    {
        [$ownerA, $companyA] = $this->company('Alpha Constraints SRL');
        [$ownerB, $companyB] = $this->company('Beta Constraints SRL');
        [$quoteA] = $this->sentQuoteAndToken($companyA, $ownerA);
        [$quoteB] = $this->sentQuoteAndToken($companyB, $ownerB);

        foreach ([
            ['decision' => 'MAYBE'],
            ['customer_name' => ''],
            ['customer_email' => 'UPPER@EXAMPLE.COM'],
            ['quote_id' => $quoteB->id],
        ] as $change) {
            try {
                $this->tenant($companyA, fn () => DB::connection('pgsql')
                    ->table('quote_public_decisions')->insert([
                        'id' => (string) Str::uuid7(),
                        'company_id' => $companyA->id,
                        'quote_id' => $quoteA->id,
                        'customer_id' => $quoteA->customer_id,
                        'decision' => PublicQuoteDecision::Accepted->value,
                        'customer_name' => 'Ana',
                        'customer_email' => 'ana@example.com',
                        'decided_at' => now(),
                        'idempotency_key' => (string) Str::uuid7(),
                        ...$change,
                    ]));
                $this->fail('An invalid public decision row was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_quote_deletion_cascades_private_identity_and_retains_only_safe_fact(): void
    {
        [$owner, $company] = $this->company();
        [$quote, $token] = $this->sentQuoteAndToken($company, $owner);
        $this->decide($token, PublicQuoteDecision::Accepted);

        app(DeleteQuote::class)->handle(
            $company,
            $owner,
            $quote->id,
            new QuoteDeletionData(confirmed: true, confirmedHighRisk: true),
        );

        $this->tenant($company, function (): void {
            $this->assertSame(0, QuotePublicDecision::query()->count());
            $audit = AuditEvent::query()->where('action', 'company.quote.deleted')->sole();
            $this->assertTrue($audit->before['had_customer_decision']);
            $this->assertArrayNotHasKey('customer_name', $audit->before);
            $this->assertArrayNotHasKey('customer_email', $audit->before);
        });
    }

    public function test_concurrent_identical_decisions_create_one_effect(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The public-decision concurrency test requires pcntl.');
        }

        [$owner, $company] = $this->company();
        [$quote, $token] = $this->sentQuoteAndToken($company, $owner);
        $data = $this->data(PublicQuoteDecision::Accepted);
        $directory = sys_get_temp_dir().'/invumo-public-decision-'.Str::random(12);
        mkdir($directory, 0700);
        $barrier = "{$directory}/start";
        $children = [];

        foreach ([1, 2] as $slot) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->runDecision($token, $data, $barrier, "{$directory}/{$slot}");
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

        $this->assertSame(['ACCEPTED', 'ACCEPTED'], [
            trim((string) file_get_contents("{$directory}/1")),
            trim((string) file_get_contents("{$directory}/2")),
        ]);
        $this->tenant($company, function (): void {
            $this->assertSame(1, QuotePublicDecision::query()->count());
            $this->assertSame(1, AuditEvent::query()
                ->where('action', 'company.quote.public_decided')->count());
        });

        foreach (["{$directory}/1", "{$directory}/2", $barrier] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    private function decide(string $token, PublicQuoteDecision $decision): void
    {
        app(DecidePublicQuote::class)->handle($token, $this->data($decision));
    }

    private function data(PublicQuoteDecision $decision): PublicQuoteDecisionData
    {
        return new PublicQuoteDecisionData(
            $decision,
            'Ana Popescu',
            'ana@example.com',
            (string) Str::uuid7(),
        );
    }

    /** @return array{Document, string} */
    private function sentQuoteAndToken(Company $company, User $owner): array
    {
        $quote = $this->quote($company, $owner);
        $this->tenant($company, function () use ($quote): void {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company,
                'legal_name' => 'Decision Customer SRL',
            ]);
            Document::query()->whereKey($quote->id)->update(['customer_id' => $customer->id]);
            Quote::query()->whereKey($quote->id)->update(['lifecycle' => QuoteLifecycle::Sent]);
        });
        $link = app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $quote->id,
            DocumentKind::Quote,
        );

        return [$quote, $link->token_ciphertext];
    }

    private function runDecision(
        string $token,
        PublicQuoteDecisionData $data,
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
            $decision = app(DecidePublicQuote::class)->handle($token, $data);
            file_put_contents($result, $decision?->decision?->value ?? 'NULL', LOCK_EX);
            exit(0);
        } catch (\Throwable $exception) {
            file_put_contents($result, $exception->getMessage(), LOCK_EX);
            exit(1);
        }
    }
}
