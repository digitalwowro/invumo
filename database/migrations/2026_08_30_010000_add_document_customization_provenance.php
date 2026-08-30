<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('defaults_customized')->default(false);
        });
        Schema::table('document_lines', function (Blueprint $table): void {
            $table->boolean('is_customized')->default(false);
        });

        $connection = DB::connection($this->getConnection());
        $companyIds = $connection->table('companies')->orderBy('id')->pluck('id');

        foreach ($companyIds as $companyId) {
            $connection->transaction(function () use ($companyId, $connection): void {
                $connection->selectOne(
                    "SELECT set_config('app.current_company_id', ?, true)",
                    [(string) $companyId],
                );
                $connection->table('document_lines')
                    ->where('company_id', $companyId)
                    ->whereNull('product_service_id')
                    ->update(['is_customized' => true]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('document_lines', function (Blueprint $table): void {
            $table->dropColumn('is_customized');
        });
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('defaults_customized');
        });
    }
};
