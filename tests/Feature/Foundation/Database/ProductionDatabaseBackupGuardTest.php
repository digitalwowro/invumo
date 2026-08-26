<?php

use App\Foundation\Database\ProductionDatabaseBackup;

it('keeps the production wrapper unavailable in tests', function () {
    expect(fn () => app(ProductionDatabaseBackup::class)->handle())
        ->toThrow(
            RuntimeException::class,
            'Production database backups require the production application environment.',
        );
});
