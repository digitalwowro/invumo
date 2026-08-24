<?php

namespace App\Modules\Platform\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Foundation\Database\RuntimeModel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $action
 * @property string $target_type
 * @property string $target_id
 * @property string|null $reason
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property CarbonImmutable $occurred_at
 * @property-read User|null $actor
 * @property-read User|null $impersonator
 */
#[Fillable([
    'actor_user_id',
    'impersonator_user_id',
    'action',
    'target_type',
    'target_id',
    'reason',
    'before',
    'after',
    'occurred_at',
    'correlation_id',
    'idempotency_reference',
])]
class PlatformAuditEvent extends RuntimeModel
{
    use HasDomainIdentifiers;

    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
