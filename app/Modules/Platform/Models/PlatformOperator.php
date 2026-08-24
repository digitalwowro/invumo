<?php

namespace App\Modules\Platform\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Foundation\Database\RuntimeModel;
use App\Models\User;
use App\Modules\Platform\Data\PlatformRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property PlatformRole $role */
#[Fillable(['user_id', 'role'])]
class PlatformOperator extends RuntimeModel
{
    use HasDomainIdentifiers;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => PlatformRole::class];
    }
}
