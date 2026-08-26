<?php

namespace Tests\Feature\Modules\Quotes;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Tests\TestCase;

final class QuoteDraftDatabaseTest extends TestCase
{
    use DatabaseMigrations;

    public function test_all_quote_tables_are_forced_rls_and_default_deny(): void
    {
        $company = $this->company();

        foreach ([
            'number_counters',
            'documents',
            'quotes',
            'document_number_events',
            'document_lines',
            'document_company_snapshots',
            'document_customer_snapshots',
            'document_bank_snapshots',
            'document_tax_defaults',
            'document_delivery_settings',
            'document_delivery_recipients',
        ] as $table) {
            $rls = DB::connection('pgsql_schema')->selectOne(<<<SQL
                SELECT relrowsecurity, relforcerowsecurity
                FROM pg_class WHERE oid = 'public.{$table}'::regclass
                SQL);
            $this->assertTrue($rls->relrowsecurity);
            $this->assertTrue($rls->relforcerowsecurity);
            $this->assertSame(0, DB::connection('pgsql_schema')->table($table)->count());
        }

        app(TenantContext::class)->runAsSystem($company->id, fn () => $this->assertSame(0, Document::query()->count()));
    }

    public function test_position_uniqueness_is_immediate_by_default_and_can_be_narrowly_deferred(): void
    {
        [$company, $quote] = $this->quote();

        app(TenantContext::class)->runAsSystem($company->id, function () use ($quote): void {
            $connection = DB::connection(config('database.tenant_connection'));
            DocumentLine::query()->create($this->line($quote, 1, 'A'));

            try {
                $connection->transaction(fn () => DocumentLine::query()->create($this->line($quote, 1, 'B')));
                $this->fail('Duplicate committed positions must fail immediately.');
            } catch (QueryException) {
                $this->assertSame(1, DocumentLine::query()->count());
            }

            DocumentLine::query()->create($this->line($quote, 2, 'B'));
            $lines = DocumentLine::query()->orderBy('position')->get();
            $connection->statement('SET CONSTRAINTS document_lines_company_document_position_unique DEFERRED');
            $lines[0]->update(['position' => 2]);
            $lines[1]->update(['position' => 1]);
            $connection->statement('SET CONSTRAINTS document_lines_company_document_position_unique IMMEDIATE');
            $this->assertSame(['B', 'A'], DocumentLine::query()->orderBy('position')->pluck('description')->all());
        });
    }

    public function test_database_rejects_inconsistent_calculated_amounts_at_commit(): void
    {
        [$company, $quote] = $this->quote();

        $this->expectException(PDOException::class);

        app(TenantContext::class)->runAsSystem($company->id, function () use ($quote): void {
            DocumentLine::query()->create([
                ...$this->line($quote, 1, 'Invalid'),
                'item_price' => 10,
                'quantity' => 1,
                'items_subtotal' => 10,
                'items_total' => 10,
                'discount_amount' => 0,
                'grand_subtotal' => 10,
                'tax_amount' => 0,
                'final_line_total' => 99,
            ]);
        });
    }

    public function test_rls_hides_another_company_quote_and_blocks_cross_company_line_write(): void
    {
        [$companyA] = $this->quote();
        [$companyB, $quoteB] = $this->quote();

        app(TenantContext::class)->runAsSystem($companyA->id, function () use ($companyB, $quoteB): void {
            $this->assertNull(Document::query()->find($quoteB->id));

            $this->expectException(QueryException::class);
            DB::connection(config('database.tenant_connection'))->table('document_lines')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $companyB->id,
                'document_id' => $quoteB->id,
                'position' => 1,
                'period_unit' => 'NONE',
                'discount_percentage' => 0,
                'tax_percentage' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_database_enforces_quote_reference_offset_and_date_pair(): void
    {
        [$company, $document] = $this->quote();

        app(TenantContext::class)->runAsSystem($company->id, function () use ($document): void {
            $connection = DB::connection(config('database.tenant_connection'));

            foreach ([
                fn () => Document::query()->whereKey($document->id)->update([
                    'customer_reference' => str_repeat('x', 121),
                ]),
                fn () => Quote::query()->whereKey($document->id)->update([
                    'validity_days' => 3_652_059,
                ]),
                fn () => Quote::query()->whereKey($document->id)->update([
                    'valid_until' => '2026-08-25',
                ]),
            ] as $invalidWrite) {
                try {
                    $connection->transaction($invalidWrite);
                    $this->fail('Invalid Quote operational data must fail at the database boundary.');
                } catch (QueryException $exception) {
                    $this->assertSame('23514', $exception->errorInfo[0]);
                }
            }

            $this->assertNull(Document::query()->findOrFail($document->id)->customer_reference);
            $this->assertSame(30, Quote::query()->findOrFail($document->id)->validity_days);
        });
    }

    public function test_validity_backfill_enters_each_forced_rls_company_context(): void
    {
        [$companyA, $quoteA] = $this->quote();
        [$companyB, $quoteB] = $this->quote();

        foreach ([[$companyA, $quoteA], [$companyB, $quoteB]] as [$company, $quote]) {
            app(TenantContext::class)->runAsSystem($company->id, fn () => Quote::query()
                ->whereKey($quote->id)
                ->update(['validity_days' => null, 'valid_until' => null]));
        }

        $defaultConnection = DB::getDefaultConnection();

        try {
            DB::setDefaultConnection('pgsql_schema');
            $migration = require database_path('migrations/2026_08_26_041000_backfill_quote_validity_with_tenant_context.php');
            $migration->up();
        } finally {
            DB::setDefaultConnection($defaultConnection);
        }

        foreach ([[$companyA, $quoteA], [$companyB, $quoteB]] as [$company, $quote]) {
            app(TenantContext::class)->runAsSystem($company->id, function () use ($quote): void {
                $stored = Quote::query()->findOrFail($quote->id);
                $this->assertSame(30, $stored->validity_days);
                $this->assertNotNull($stored->valid_until);
            });
        }

        $this->assertSame(0, DB::connection('pgsql_schema')->table('quotes')->count());
    }

    /** @return array{Company, Document} */
    private function quote(): array
    {
        $company = $this->company();
        $owner = $company->memberships()->firstOrFail()->user;
        $quote = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());

        return [$company, $quote];
    }

    private function company(): Company
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'DB Quote SRL');
        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            CompanySetting::query()->firstOrFail()->update(['timezone' => 'Europe/Bucharest']);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return $company;
    }

    /** @return array<string, mixed> */
    private function line(Document $quote, int $position, string $description): array
    {
        return [
            'document_id' => $quote->id,
            'position' => $position,
            'description' => $description,
            'period_unit' => 'NONE',
            'discount_percentage' => 0,
            'tax_percentage' => 0,
        ];
    }
}
