<?php

namespace App\Foundation\Database;

final readonly class DestructiveCommandSafety
{
    /**
     * @param  list<mixed>  $databaseNames
     */
    public function permits(string $environment, array $databaseNames): bool
    {
        if ($environment !== 'testing' || $databaseNames === []) {
            return false;
        }

        foreach ($databaseNames as $databaseName) {
            if (! is_string($databaseName) || ! str_ends_with($databaseName, '_test')) {
                return false;
            }
        }

        return true;
    }
}
