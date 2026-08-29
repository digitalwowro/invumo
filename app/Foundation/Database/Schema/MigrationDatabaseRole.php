<?php

namespace App\Foundation\Database\Schema;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MigrationDatabaseRole
{
    public const RUNTIME = 'invumo_runtime';

    public const DISPATCHER = 'invumo_dispatcher';

    /**
     * Production-like migrations must never succeed with an incomplete grant
     * outcome. Local and test databases may deliberately omit split roles.
     */
    public static function runtimeIsAvailable(): bool
    {
        return self::isAvailable(self::RUNTIME);
    }

    public static function isAvailable(string $role): bool
    {
        $exists = DB::table('pg_roles')
            ->where('rolname', $role)
            ->exists();

        if ($exists) {
            return true;
        }

        if (app()->environment(['local', 'testing'])) {
            return false;
        }

        throw new RuntimeException(
            "Required PostgreSQL role [{$role}] is missing. "
            .'Create the restricted runtime role before running migrations.',
        );
    }
}
