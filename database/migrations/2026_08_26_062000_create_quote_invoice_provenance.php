<?php

use App\Foundation\Database\Schema\TenantTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->integer('invoice_payment_term_days')->nullable();
        });

        Schema::create('quote_invoice_links', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('quote_id');
            $table->uuid('invoice_id');
            $table->uuid('copied_by_user_id')->nullable();
            $table->uuid('creation_key');
            $table->timestampTz('copied_at');
            $table->timestampsTz();

            $table->foreign(
                ['company_id', 'quote_id'],
                'quote_invoice_links_company_quote_foreign',
            )->references(['company_id', 'document_id'])->on('quotes')->restrictOnDelete();
            $table->foreign(
                ['company_id', 'invoice_id'],
                'quote_invoice_links_company_invoice_foreign',
            )->references(['company_id', 'document_id'])->on('invoices')->restrictOnDelete();
            $table->foreign('copied_by_user_id')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(
                ['company_id', 'quote_id', 'creation_key'],
                'quote_invoice_links_quote_creation_unique',
            );
            $table->unique(
                ['company_id', 'invoice_id'],
                'quote_invoice_links_invoice_unique',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE quotes
                ADD CONSTRAINT quotes_invoice_payment_term_days_check
                    CHECK (invoice_payment_term_days IS NULL OR invoice_payment_term_days BETWEEN 0 AND 3652058)
            SQL);

        $this->backfillPaymentTerms();
        TenantTable::protect('quote_invoice_links');
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_invoice_links');

        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn('invoice_payment_term_days');
        });
    }

    private function backfillPaymentTerms(): void
    {
        $connection = DB::connection($this->getConnection());
        $companyIds = $connection->table('companies')->orderBy('id')->pluck('id');

        foreach ($companyIds as $companyId) {
            $connection->transaction(function () use ($companyId, $connection): void {
                $connection->selectOne(
                    "SELECT set_config('app.current_company_id', ?, true)",
                    [(string) $companyId],
                );
                $connection->statement(<<<'SQL'
                    UPDATE quotes AS quote
                    SET invoice_payment_term_days = COALESCE(
                        (
                            SELECT customer.payment_term_days
                            FROM documents AS document
                            LEFT JOIN customers AS customer
                              ON customer.company_id = document.company_id
                             AND customer.id = document.customer_id
                            WHERE document.company_id = quote.company_id
                              AND document.id = quote.document_id
                        ),
                        (
                            SELECT settings.default_payment_term_days
                            FROM company_settings AS settings
                            WHERE settings.company_id = quote.company_id
                        )
                    )
                    WHERE quote.company_id = ?
                    SQL, [(string) $companyId]);
            });
        }
    }
};
