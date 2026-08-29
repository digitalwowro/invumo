<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Delivery\Data\JobDispatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'target_id', 'job_type', 'due_at', 'idempotency_key', 'status', 'claim_token', 'claimed_at',
])]
final class JobDispatch extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'status' => JobDispatchStatus::class,
            'claimed_at' => 'immutable_datetime',
        ];
    }
}
