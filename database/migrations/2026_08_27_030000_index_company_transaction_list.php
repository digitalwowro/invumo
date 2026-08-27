<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table): void {
            $table->index(
                ['company_id', 'transaction_date', 'id'],
                'invoice_transactions_company_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table): void {
            $table->dropIndex('invoice_transactions_company_date_index');
        });
    }
};
