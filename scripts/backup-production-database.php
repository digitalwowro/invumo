#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Foundation\Database\ProductionDatabaseBackup;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $result = app(ProductionDatabaseBackup::class)->handle();
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, 'Production database backup failed before completion.'.PHP_EOL);
    exit(1);
}
