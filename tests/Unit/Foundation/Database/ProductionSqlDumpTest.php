<?php

use App\Foundation\Database\PostgreSqlClientBinaries;
use App\Foundation\Database\PrivateSqlBackupFiles;
use App\Foundation\Database\ProductionSqlDump;

function configuredPostgreSqlBinaries(int $majorVersion = 18): PostgreSqlClientBinaries
{
    return new PostgreSqlClientBinaries('/usr/lib/postgresql/18/bin', $majorVersion);
}

function privateBackupTestDirectory(): string
{
    return sys_get_temp_dir().'/invumo-backup-unit-'.bin2hex(random_bytes(8));
}

function removePrivateBackupTestDirectory(string $directory): void
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

it('propagates the exported snapshot and validates tenant table identifiers', function () {
    $dump = new ProductionSqlDump(configuredPostgreSqlBinaries());
    $command = $dump->command('127.0.0.1', '5432', 'schema', 'invumo', '0001-1');

    expect($command[0])->toBe('/usr/lib/postgresql/18/bin/pg_dump')
        ->and($command)->toContain('--snapshot=0001-1')
        ->and($dump->qualifiedTable((object) [
            'schema_name' => 'public',
            'table_name' => 'company_settings',
        ]))->toBe('public.company_settings');

    $dump->qualifiedTable((object) [
        'schema_name' => 'public',
        'table_name' => 'unsafe-name',
    ]);
})->throws(RuntimeException::class, 'A tenant table name was not safe to export.');

it('detects missing forced-RLS rows in a generated SQL file', function () {
    $path = tempnam(sys_get_temp_dir(), 'invumo-row-count-');
    file_put_contents($path, implode("\n", [
        '-- PostgreSQL database dump',
        'INSERT INTO public.company_settings (id) VALUES (\'one\');',
    ]));

    $dump = new ProductionSqlDump(configuredPostgreSqlBinaries());
    $dump->verifyTenantRowCounts($path, ['public.company_settings' => 1]);

    expect(fn () => $dump->verifyTenantRowCounts(
        $path,
        ['public.company_settings' => 2],
    ))->toThrow(RuntimeException::class, 'every forced-RLS tenant row');

    unlink($path);
});

it('fails closed when the dump process cannot complete', function () {
    $path = tempnam(sys_get_temp_dir(), 'invumo-dump-failure-');
    $destination = fopen($path, 'wb');
    $dump = new ProductionSqlDump(configuredPostgreSqlBinaries());

    expect(fn () => $dump->append($destination, ['/usr/bin/false'], getenv()))
        ->toThrow(RuntimeException::class);

    fclose($destination);
    unlink($path);
});

it('requires the configured PostgreSQL client major version', function () {
    expect(fn () => configuredPostgreSqlBinaries(17)->assertCompatible())
        ->toThrow(RuntimeException::class, 'incompatible major version');
});

it('finalizes private backups atomically with restrictive permissions', function () {
    $directory = privateBackupTestDirectory();
    $files = new PrivateSqlBackupFiles;
    $prepared = $files->prepareDirectory($directory);
    $temporary = $prepared.'/fixture.sql.partial-token';
    $final = $prepared.'/fixture.sql';
    file_put_contents($temporary, "-- PostgreSQL database dump\nSELECT 1;\n");

    $result = $files->finalize($temporary, $final, $prepared);

    expect(fileperms($prepared) & 0777)->toBe(0700)
        ->and(fileperms($final) & 0777)->toBe(0600)
        ->and(is_file($temporary))->toBeFalse()
        ->and($result['sha256'])->toBe(hash_file('sha256', $final));

    removePrivateBackupTestDirectory($directory);
});
