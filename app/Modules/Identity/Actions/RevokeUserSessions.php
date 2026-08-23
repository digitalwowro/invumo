<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class RevokeUserSessions
{
    public function handle(User $user, ?string $exceptSessionId = null): int
    {
        $query = DB::connection(config('database.tenant_connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->id);

        if ($exceptSessionId !== null) {
            $query->where('id', '<>', $exceptSessionId);
        }

        return $query->delete();
    }
}
