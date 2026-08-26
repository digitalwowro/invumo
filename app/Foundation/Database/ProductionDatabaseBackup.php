<?php

declare(strict_types=1);

namespace App\Foundation\Database;

use RuntimeException;

final readonly class ProductionDatabaseBackup
{
    private const BACKUP_DIRECTORY = '/home/invumo/backups';

    public function __construct(private PostgreSqlDatabaseBackup $databaseBackup) {}

    /** @return array{path: string, bytes: int, sha256: string} */
    public function handle(): array
    {
        $database = config('database.connections.pgsql_schema.database');

        if ((string) config('app.env') !== 'production') {
            throw new RuntimeException(
                'Production database backups require the production application environment.',
            );
        }

        if (! is_string($database) || $database === '') {
            throw new RuntimeException('The PostgreSQL database is unavailable.');
        }

        if (str_ends_with($database, '_test')) {
            throw new RuntimeException('The production backup command refuses a disposable test database.');
        }

        return $this->databaseBackup->handle(
            'pgsql_schema',
            self::BACKUP_DIRECTORY,
            'invumo',
        );
    }
}
