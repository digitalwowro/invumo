<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Delivery\Data\JobDispatchStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $target_id
 * @property string $job_type
 * @property CarbonImmutable $due_at
 * @property string $idempotency_key
 * @property JobDispatchStatus $status
 * @property string|null $claim_token
 * @property CarbonImmutable|null $claimed_at
 * @property int $attempt_count
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property string|null $failure_category
 * @property string|null $failure_summary
 */
#[Fillable([
    'target_id', 'job_type', 'due_at', 'idempotency_key', 'status', 'claim_token', 'claimed_at',
    'attempt_count', 'started_at', 'completed_at', 'failure_category', 'failure_summary',
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
            'attempt_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
