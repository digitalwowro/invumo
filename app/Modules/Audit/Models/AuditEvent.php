<?php

namespace App\Modules\Audit\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Models\User;
use App\Modules\Audit\Data\AuditActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'actor_type',
    'actor_user_id',
    'actor_reference',
    'action',
    'target_type',
    'target_id',
    'occurred_at',
    'correlation_id',
    'idempotency_reference',
    'reason',
    'before',
    'after',
])]
class AuditEvent extends TenantOwnedModel
{
    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'actor_type' => AuditActorType::class,
            'occurred_at' => 'immutable_datetime',
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
