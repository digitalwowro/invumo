<?php

namespace Tests\Feature\Modules\Invoices;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
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
use App\Modules\Invoices\Actions\CancelInvoice;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Exceptions\InvoiceLifecycleException;
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

final class InvoiceCancellationDatabaseTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-27 12:00:00');
    }

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(fn (string $companyId) => $this->tenantId(
            $companyId,
            function (): void {
                DB::connection(config('database.tenant_connection'))->transaction(function (): void {
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Cancelled)
                        ->update(['lifecycle' => InvoiceLifecycle::Issued]);
                    DB::statement('SET CONSTRAINTS invoice_transaction_ledger_trigger DEFERRED');
                    InvoiceTransaction::query()->delete();
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)
                        ->update(['lifecycle' => InvoiceLifecycle::Draft]);
                });
            },
        ));
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_database_requires_zero_net_paid_and_freezes_cancelled_transaction_history(): void
    {
        [$company, $invoice] = $this->invoice();

        $this->tenant($company, function () use ($invoice): void {
            $payment = $this->transaction($invoice, 'PAYMENT', '20');
            $this->expectConstraintFailure(fn () => Invoice::query()
                ->whereKey($invoice->id)->update(['lifecycle' => 'CANCELLED']));
            $refund = $this->transaction($invoice, 'REFUND', '20');
            Invoice::query()->whereKey($invoice->id)->update(['lifecycle' => 'CANCELLED']);

            $this->expectConstraintFailure(fn () => $this->transaction($invoice, 'PAYMENT', '1'));
            $this->expectConstraintFailure(fn () => $payment->update(['reference' => 'blocked']));
            $this->expectConstraintFailure(fn () => $refund->delete());

            $this->assertSame(InvoiceLifecycle::Cancelled, Invoice::query()->findOrFail($invoice->id)->lifecycle);
            $this->assertSame(2, InvoiceTransaction::query()->where('invoice_id', $invoice->id)->count());
            Invoice::query()->whereKey($invoice->id)->update(['lifecycle' => 'ISSUED']);
            $payment->refresh()->update(['reference' => 'allowed-after-reopen']);
            $this->assertSame('allowed-after-reopen', $payment->refresh()->reference);
        });
    }

    public function test_concurrent_cancellation_and_payment_commit_only_one_valid_outcome(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The cancellation concurrency test requires pcntl.');
        }

        [$company, $invoice] = $this->invoice();
        $owner = $company->memberships()->firstOrFail()->user;
        $directory = sys_get_temp_dir().'/invumo-cancel-'.Str::random(12);
        mkdir($directory, 0700);
        $barrier = "{$directory}/start";
        $children = [];

        foreach (['cancel', 'payment'] as $operation) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->runConcurrentMutation(
                    $operation, $company->id, $owner->id, $invoice->id,
                    $barrier, "{$directory}/{$operation}",
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
            trim((string) file_get_contents("{$directory}/cancel")),
            trim((string) file_get_contents("{$directory}/payment")),
        ];
        sort($outcomes);
        $this->assertSame(['accepted', 'rejected'], $outcomes);
        $this->tenant($company, function () use ($invoice): void {
            $lifecycle = Invoice::query()->findOrFail($invoice->id)->lifecycle;
            $transactions = InvoiceTransaction::query()->where('invoice_id', $invoice->id)->count();
            $this->assertTrue(
                ($lifecycle === InvoiceLifecycle::Cancelled && $transactions === 0)
                || ($lifecycle === InvoiceLifecycle::Issued && $transactions === 1),
            );
        });

        foreach (["{$directory}/cancel", "{$directory}/payment", $barrier] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    private function runConcurrentMutation(
        string $operation,
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
            $company = Company::query()->findOrFail($companyId);
            $owner = User::query()->findOrFail($ownerId);

            if ($operation === 'cancel') {
                app(CancelInvoice::class)->handle($company, $owner, $invoiceId, 2, true);
            } else {
                app(CreateInvoiceTransaction::class)->handle(
                    $company,
                    $owner,
                    $invoiceId,
                    new InvoiceTransactionData(
                        InvoiceTransactionKind::Payment, null, '20', '2026-08-27',
                        null, null, null, null, (string) Str::uuid7(), null, true,
                    ),
                );
            }

            file_put_contents($result, 'accepted', LOCK_EX);
        } catch (InvoiceLifecycleException|InvoiceTransactionException) {
            file_put_contents($result, 'rejected', LOCK_EX);
        } catch (\Throwable $exception) {
            file_put_contents($result, $exception::class.': '.$exception->getMessage(), LOCK_EX);
            exit(1);
        }

        exit(0);
    }

    private function expectConstraintFailure(Closure $write): void
    {
        try {
            DB::connection(config('database.tenant_connection'))->transaction($write);
            $this->fail('The database must reject this Invoice cancellation invariant violation.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        }
    }

    private function transaction(Document $invoice, string $kind, string $amount): InvoiceTransaction
    {
        return InvoiceTransaction::query()->create([
            'invoice_id' => $invoice->id,
            'kind' => $kind,
            'amount' => $amount,
            'currency_code' => 'RON',
            'currency_precision' => 2,
            'transaction_date' => '2026-08-27',
            'creation_key' => (string) Str::uuid7(),
            'edit_version' => 1,
        ]);
    }

    /** @return array{Company, Document} */
    private function invoice(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Cancellation DB SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest',
                'default_document_language' => 'en',
                'default_payment_term_days' => 30,
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });
        $document = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, function () use ($document): void {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company, 'legal_name' => 'Cancellation DB Customer SRL',
            ]);
            $document->update([
                'customer_id' => $customer->id, 'issue_date' => '2026-08-27',
                'subtotal' => '100', 'total' => '100',
            ]);
            Invoice::query()->whereKey($document->id)->update(['due_date' => '2026-09-26']);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $document->id, 'type' => CustomerType::Company,
                'legal_name' => 'Cancellation DB Customer SRL',
            ]);
            DocumentLine::query()->create([
                'document_id' => $document->id, 'position' => 1,
                'description' => 'Cancellation service', 'item_price' => '100',
                'quantity' => '1', 'unit' => 'item', 'period_unit' => 'NONE',
                'discount_percentage' => '0', 'discount_amount' => '0',
                'tax_name' => 'VAT', 'tax_percentage' => '0',
                'items_subtotal' => '100', 'items_total' => '100',
                'grand_subtotal' => '100', 'tax_amount' => '0', 'final_line_total' => '100',
            ]);
        });
        app(IssueInvoice::class)->handle($company, $owner, $document->id, 1);

        return [$company, $document];
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return $this->tenantId($company->id, $callback);
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenantId(string $companyId, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($companyId, $callback);
    }
}
