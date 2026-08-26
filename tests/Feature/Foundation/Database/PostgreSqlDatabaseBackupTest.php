<?php

use App\Foundation\Database\PostgreSqlClientBinaries;
use App\Foundation\Database\PostgreSqlDatabaseBackup;
use App\Foundation\Database\PrivateSqlBackupFiles;
use App\Foundation\Database\ProductionSqlDump;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Database\PostgreSqlTestRestore;
use Tests\Support\Database\RecordingSqlDumpProcess;

uses(DatabaseMigrations::class);

function backupTestCompany(string $name): array
{
    $owner = User::factory()->create();
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);

    return [$owner, app(CreateCompany::class)->handle($account, $owner, $name)];
}

function backupFeatureDirectory(): string
{
    return sys_get_temp_dir().'/invumo-backup-feature-'.bin2hex(random_bytes(8));
}

function removeBackupFeatureDirectory(string $directory): void
{
    foreach (glob($directory.'/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
}

it('creates a consistent forced-RLS backup and restores it', function () {
    [, $companyA] = backupTestCompany('Backup Alpha');
    backupTestCompany('Backup Beta');
    $lateOwner = User::factory()->create();
    $lateAccount = Account::query()->create([
        'owner_user_id' => $lateOwner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $lateCompanyId = (string) Str::uuid7();
    $lateMembershipId = (string) Str::uuid7();
    $connection = config('database.connections.pgsql_schema');
    config()->set('database.connections.backup_concurrent', $connection);
    $directory = backupFeatureDirectory();
    $recording = new RecordingSqlDumpProcess(
        new ProductionSqlDump(app(PostgreSqlClientBinaries::class)),
        afterAppend: function (int $append) use (
            $lateAccount,
            $lateCompanyId,
            $lateMembershipId,
            $lateOwner,
        ): void {
            if ($append !== 1) {
                return;
            }

            DB::connection('backup_concurrent')->transaction(function () use (
                $lateAccount,
                $lateCompanyId,
                $lateMembershipId,
                $lateOwner,
            ): void {
                $now = now();
                DB::connection('backup_concurrent')->table('companies')->insert([
                    'id' => $lateCompanyId,
                    'owning_account_id' => $lateAccount->id,
                    'name' => 'Created during backup',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::connection('backup_concurrent')->table('company_memberships')->insert([
                    'id' => $lateMembershipId,
                    'company_id' => $lateCompanyId,
                    'user_id' => $lateOwner->id,
                    'role' => 'OWNER',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        },
    );

    $result = (new PostgreSqlDatabaseBackup(
        $recording,
        new PrivateSqlBackupFiles,
    ))->handle('pgsql_schema', $directory, 'test');
    $snapshots = collect($recording->commands)
        ->flatten()
        ->filter(fn (string $argument): bool => str_starts_with($argument, '--snapshot='))
        ->unique();
    $tenantTableCount = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
        SELECT count(*)::int AS count
        FROM pg_class AS relation
        JOIN pg_namespace AS namespace ON namespace.oid = relation.relnamespace
        WHERE namespace.nspname = 'public'
            AND relation.relforcerowsecurity
            AND EXISTS (
                SELECT 1 FROM pg_attribute AS attribute
                WHERE attribute.attrelid = relation.oid
                    AND attribute.attname = 'company_id'
                    AND NOT attribute.attisdropped
            )
        SQL)->count;

    expect($snapshots)->toHaveCount(1)
        ->and($recording->tenantContexts)->toHaveCount($tenantTableCount * 2)
        ->and(fileperms($directory) & 0777)->toBe(0700)
        ->and(fileperms($result['path']) & 0777)->toBe(0600)
        ->and(file_get_contents($result['path']))->not->toContain($lateCompanyId)
        ->and(glob($directory.'/*.partial-*') ?: [])->toBe([]);

    DB::purge('backup_concurrent');
    DB::purge('pgsql');
    DB::purge('pgsql_schema');
    (new PostgreSqlTestRestore(app(PostgreSqlClientBinaries::class)))
        ->restore($connection, $result['path']);
    DB::purge('pgsql');
    DB::purge('pgsql_schema');

    expect(DB::connection('pgsql_schema')->table('companies')->count())->toBe(2)
        ->and(DB::connection('pgsql_schema')->table('companies')->where('id', $lateCompanyId)->exists())
        ->toBeFalse();
    app(TenantContext::class)->runAsSystem(
        $companyA->id,
        fn () => expect(CompanySetting::query()->count())->toBe(1),
    );

    removeBackupFeatureDirectory($directory);
});

it('removes partial output when an exported snapshot expires', function () {
    backupTestCompany('Expired Snapshot');
    $directory = backupFeatureDirectory();
    $recording = new RecordingSqlDumpProcess(
        new ProductionSqlDump(app(PostgreSqlClientBinaries::class)),
        expireSnapshotOnAppend: 2,
    );
    $backup = new PostgreSqlDatabaseBackup($recording, new PrivateSqlBackupFiles);

    expect(fn () => $backup->handle('pgsql_schema', $directory, 'test'))
        ->toThrow(RuntimeException::class);
    expect(glob($directory.'/*') ?: [])->toBe([]);

    removeBackupFeatureDirectory($directory);
});

it('removes partial output when tenant row verification disagrees', function () {
    backupTestCompany('Mismatched Rows');
    $directory = backupFeatureDirectory();
    $recording = new RecordingSqlDumpProcess(
        new ProductionSqlDump(app(PostgreSqlClientBinaries::class)),
        forceRowMismatch: true,
    );
    $backup = new PostgreSqlDatabaseBackup($recording, new PrivateSqlBackupFiles);

    expect(fn () => $backup->handle('pgsql_schema', $directory, 'test'))
        ->toThrow(RuntimeException::class, 'every forced-RLS tenant row');
    expect(glob($directory.'/*') ?: [])->toBe([]);

    removeBackupFeatureDirectory($directory);
});

it('refuses restore verification outside an isolated test database', function () {
    $connection = config('database.connections.pgsql_schema');
    $connection['database'] = 'invumo';

    expect(fn () => (new PostgreSqlTestRestore(app(PostgreSqlClientBinaries::class)))
        ->restore($connection, '/tmp/not-used.sql'))
        ->toThrow(RuntimeException::class, 'requires an isolated test database');
});
