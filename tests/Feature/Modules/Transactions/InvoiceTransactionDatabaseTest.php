<?php

namespace Tests\Feature\Modules\Transactions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Actions\CreateInvoiceTransaction;
use App\Modules\Transactions\Data\InvoiceTransactionData;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use App\Modules\Transactions\Exceptions\InvoiceTransactionException;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InvoiceTransactionDatabaseTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-27 12:00:00');
    }

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(function (string $companyId): void {
            app(TenantContext::class)->runAsSystem($companyId, function (): void {
                InvoiceTransaction::query()->delete();
                Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)
                    ->update(['lifecycle' => InvoiceLifecycle::Draft]);
            });
        });
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_transaction_table_is_forced_rls_and_cross_company_rows_are_hidden(): void
    {
        [$company, $owner, $invoice] = $this->invoice();
        $transaction = $this->createPayment($company, $owner, $invoice, '20');
        [$other] = $this->invoice();
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class WHERE oid = 'public.invoice_transactions'::regclass
            SQL);

        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);
        $this->assertSame(0, DB::connection('pgsql_schema')->table('invoice_transactions')->count());
        $this->tenant($company, fn () => $this->assertNotNull(
            InvoiceTransaction::query()->find($transaction->id),
        ));
        $this->tenant($other, fn () => $this->assertNull(
            InvoiceTransaction::query()->find($transaction->id),
        ));
    }

    public function test_database_independently_rejects_invalid_ledger_currency_lifecycle_and_dates(): void
    {
        [$company, $owner, $invoice] = $this->invoice();
        $this->createPayment($company, $owner, $invoice, '60');

        $this->tenant($company, function () use ($invoice): void {
            foreach ([
                fn () => $this->directTransaction($invoice, 'PAYMENT', '50'),
                fn () => $this->directTransaction($invoice, 'PAYMENT', '1', currency: 'USD'),
                fn () => $this->directTransaction($invoice, 'PAYMENT', '1', date: '9999-12-31'),
                fn () => Document::query()->whereKey($invoice->id)->update(['total' => '50']),
                fn () => Invoice::query()->whereKey($invoice->id)->update(['lifecycle' => 'DRAFT']),
            ] as $write) {
                try {
                    DB::connection(config('database.tenant_connection'))->transaction($write);
                    $this->fail('The database must reject an invalid Invoice ledger write.');
                } catch (QueryException $exception) {
                    $this->assertSame('23514', $exception->errorInfo[0]);
                }
            }

            $this->assertSame('100.00000000', Document::query()->findOrFail($invoice->id)->total);
            $this->assertSame(InvoiceLifecycle::Issued, Invoice::query()->findOrFail($invoice->id)->lifecycle);
            $this->assertSame(1, InvoiceTransaction::query()->count());
        });
    }

    public function test_creation_key_retry_returns_one_transaction_and_one_audit_event(): void
    {
        [$company, $owner, $invoice] = $this->invoice();
        $key = (string) Str::uuid7();
        $data = $this->transactionData('25', $key);
        $first = app(CreateInvoiceTransaction::class)->handle($company, $owner, $invoice->id, $data);
        $second = app(CreateInvoiceTransaction::class)->handle($company, $owner, $invoice->id, $data);

        $this->assertSame($first->id, $second->id);
        $this->tenant($company, function (): void {
            $this->assertSame(1, InvoiceTransaction::query()->count());
            $this->assertSame(1, AuditEvent::query()
                ->where('action', 'company.invoice_transaction.created')->count());
        });
    }

    public function test_concurrent_payments_reconcile_under_the_invoice_lock(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The transaction concurrency test requires pcntl.');
        }

        [$company, $owner, $invoice] = $this->invoice();
        $directory = sys_get_temp_dir().'/invumo-ledger-'.Str::random(12);
        mkdir($directory, 0700);
        $barrier = "{$directory}/start";
        $children = [];

        foreach ([1, 2] as $slot) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->runConcurrentPayment(
                    $company->id,
                    $owner->id,
                    $invoice->id,
                    $barrier,
                    "{$directory}/{$slot}",
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

        $outcomes = [
            trim((string) file_get_contents("{$directory}/1")),
            trim((string) file_get_contents("{$directory}/2")),
        ];
        sort($outcomes);
        $this->assertSame(['accepted', 'rejected'], $outcomes);
        $this->tenant($company, function (): void {
            $this->assertSame(1, InvoiceTransaction::query()->count());
            $this->assertSame('75.00000000', InvoiceTransaction::query()->sole()->amount);
        });

        foreach (["{$directory}/1", "{$directory}/2", $barrier] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    private function runConcurrentPayment(
        string $companyId,
        string $ownerId,
        string $invoiceId,
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
            app(CreateInvoiceTransaction::class)->handle(
                Company::query()->findOrFail($companyId),
                User::query()->findOrFail($ownerId),
                $invoiceId,
                $this->transactionData('75', (string) Str::uuid7()),
            );
            file_put_contents($result, 'accepted', LOCK_EX);
        } catch (InvoiceTransactionException $exception) {
            file_put_contents($result, 'rejected', LOCK_EX);
        } catch (\Throwable $exception) {
            file_put_contents($result, $exception::class.': '.$exception->getMessage(), LOCK_EX);
            exit(1);
        }

        exit(0);
    }

    private function directTransaction(
        Document $invoice,
        string $kind,
        string $amount,
        string $currency = 'RON',
        string $date = '2026-08-27',
    ): void {
        InvoiceTransaction::query()->create([
            'invoice_id' => $invoice->id, 'kind' => $kind, 'amount' => $amount,
            'currency_code' => $currency, 'currency_precision' => 2,
            'transaction_date' => $date, 'creation_key' => (string) Str::uuid7(),
            'edit_version' => 1,
        ]);
    }

    private function createPayment(
        Company $company,
        User $owner,
        Document $invoice,
        string $amount,
    ): InvoiceTransaction {
        return app(CreateInvoiceTransaction::class)->handle(
            $company,
            $owner,
            $invoice->id,
            $this->transactionData($amount, (string) Str::uuid7()),
        );
    }

    private function transactionData(string $amount, string $key): InvoiceTransactionData
    {
        return new InvoiceTransactionData(
            InvoiceTransactionKind::Payment, null, $amount, '2026-08-27',
            null, null, null, null, $key, null, true,
        );
    }

    /** @return array{Company, User, Document} */
    private function invoice(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Transaction DB SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest', 'default_document_language' => 'en',
                'default_payment_term_days' => 30,
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });
        $invoice = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, function () use ($invoice): void {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company, 'legal_name' => 'Database Customer SRL',
            ]);
            $invoice->update([
                'customer_id' => $customer->id, 'issue_date' => '2026-08-27',
                'subtotal' => '100', 'total' => '100',
            ]);
            Invoice::query()->whereKey($invoice->id)->update(['due_date' => '2026-09-26']);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $invoice->id, 'type' => CustomerType::Company,
                'legal_name' => 'Database Customer SRL',
            ]);
            DocumentLine::query()->create([
                'document_id' => $invoice->id, 'position' => 1,
                'description' => 'Database service', 'item_price' => '100',
                'quantity' => '1', 'unit' => 'item', 'period_unit' => 'NONE',
                'discount_percentage' => '0', 'discount_amount' => '0',
                'tax_name' => 'VAT', 'tax_percentage' => '0',
                'items_subtotal' => '100', 'items_total' => '100',
                'grand_subtotal' => '100', 'tax_amount' => '0', 'final_line_total' => '100',
            ]);
        });
        app(IssueInvoice::class)->handle($company, $owner, $invoice->id, 1);

        return [$company, $owner, $invoice];
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
