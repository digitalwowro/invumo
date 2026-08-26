<?php

namespace Tests\Feature\Modules\Invoices;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Models\Invoice;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InvoiceDraftDatabaseTest extends TestCase
{
    use DatabaseMigrations;

    public function test_invoice_table_is_forced_rls_default_deny_and_cross_company_hidden(): void
    {
        [$company, $invoice] = $this->invoice();
        $other = $this->company();
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class WHERE oid = 'public.invoices'::regclass
            SQL);

        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);
        $this->assertSame(0, DB::connection('pgsql_schema')->table('invoices')->count());
        $this->tenant($company, fn () => $this->assertNotNull(Invoice::query()->find($invoice->id)));
        $this->tenant($other, fn () => $this->assertNull(Invoice::query()->find($invoice->id)));
    }

    public function test_database_enforces_invoice_subtype_offset_and_due_date(): void
    {
        [$company, $document] = $this->invoice();

        $this->tenant($company, function () use ($document): void {
            $connection = DB::connection(config('database.tenant_connection'));

            foreach ([
                fn () => Invoice::query()->whereKey($document->id)->update([
                    'payment_term_days' => 3_652_059,
                ]),
                fn () => Invoice::query()->whereKey($document->id)->update([
                    'due_date' => '2026-08-25',
                ]),
            ] as $invalidWrite) {
                try {
                    $connection->transaction($invalidWrite);
                    $this->fail('Invalid Invoice dates must fail at the database boundary.');
                } catch (QueryException $exception) {
                    $this->assertSame('23514', $exception->errorInfo[0]);
                }
            }

            $this->expectException(QueryException::class);
            $connection->transaction(function () use ($document): void {
                Invoice::query()->whereKey($document->id)->delete();
                DB::connection(config('database.tenant_connection'))->statement(
                    'SET CONSTRAINTS ALL IMMEDIATE',
                );
            });
        });
    }

    public function test_database_rejects_incomplete_issue_and_unknown_lifecycle(): void
    {
        [$company, $document] = $this->invoice();

        $this->tenant($company, function () use ($document): void {
            $connection = DB::connection(config('database.tenant_connection'));

            foreach (['ISSUED', 'CANCELLED'] as $lifecycle) {
                try {
                    $connection->transaction(function () use ($document, $lifecycle, $connection): void {
                        Invoice::query()->whereKey($document->id)->update(['lifecycle' => $lifecycle]);
                        $connection->statement('SET CONSTRAINTS ALL IMMEDIATE');
                    });
                    $this->fail("Invalid Invoice lifecycle [{$lifecycle}] must fail.");
                } catch (QueryException $exception) {
                    $this->assertSame('23514', $exception->errorInfo[0]);
                }
            }
        });
    }

    /** @return array{Company, Document} */
    private function invoice(): array
    {
        $company = $this->company();
        $owner = $company->memberships()->firstOrFail()->user;
        $invoice = app(CreateInvoiceDraft::class)->handle(
            $company,
            $owner,
            (string) Str::uuid7(),
        );

        return [$company, $invoice];
    }

    private function company(): Company
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Invoice DB SRL');
        $this->tenant($company, fn () => CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
            'default_payment_term_days' => 30,
        ]));

        return $company;
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
