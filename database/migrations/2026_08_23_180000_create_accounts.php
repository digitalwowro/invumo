<?php

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('email_normalized')->storedAs('lower(email)');
            $table->text('language_code')->default('en');

            $table->unique('email_normalized', 'users_email_normalized_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_language_code_check
            CHECK (language_code IN ('en', 'ro'))
            SQL);

        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('code')->unique();
            $table->text('name');
            $table->jsonb('entitlements')->default('{}');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUuid('plan_id')
                ->constrained('plans')
                ->restrictOnDelete();
            $table->timestampsTz();

            $table->index('plan_id');
        });

        DB::table('plans')->insert(array_map(
            static fn (array $plan): array => [
                'id' => (string) Str::uuid7(),
                'code' => $plan['code'],
                'name' => $plan['name'],
                'entitlements' => '{}',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                ['code' => 'free', 'name' => 'Free'],
                ['code' => 'pro', 'name' => 'Pro'],
                ['code' => 'enterprise', 'name' => 'Enterprise'],
            ],
        ));

        $this->grantRuntimePrivileges();
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('plans');

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_language_code_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_normalized_unique');
            $table->dropColumn(['email_normalized', 'language_code']);
        });
    }

    private function grantRuntimePrivileges(): void
    {
        if (! MigrationDatabaseRole::runtimeIsAvailable()) {
            return;
        }

        DB::statement('REVOKE ALL ON TABLE users, plans, accounts FROM invumo_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE users TO invumo_runtime');
        DB::statement('GRANT SELECT ON TABLE plans TO invumo_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE accounts TO invumo_runtime');
    }
};
