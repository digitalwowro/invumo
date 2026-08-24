<?php

namespace App\Modules\Platform\Queries;

use App\Models\User;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Models\PlatformOperator;

final readonly class CurrentPlatformOperator
{
    public function for(?User $user): ?PlatformOperator
    {
        if (
            $user === null
            || $user->email_verified_at === null
            || $user->suspended_at !== null
        ) {
            return null;
        }

        return PlatformOperator::query()
            ->where('user_id', $user->id)
            ->where('role', PlatformRole::Owner->value)
            ->first();
    }
}
