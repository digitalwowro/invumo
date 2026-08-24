<?php

namespace App\Modules\Identity\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Foundation\Database\RuntimeModel;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Data\PlanStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $owner_user_id
 * @property string $plan_id
 * @property PlanStatus $plan_status
 * @property CarbonImmutable $plan_started_at
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $access_ends_at
 * @property bool $cancel_at_period_end
 * @property CarbonImmutable|null $ended_at
 * @property CarbonImmutable|null $suspended_at
 * @property int $companies_count
 * @property-read User $owner
 * @property-read Plan $plan
 */
#[Fillable([
    'owner_user_id',
    'plan_id',
    'plan_status',
    'plan_started_at',
    'trial_ends_at',
    'access_ends_at',
    'cancel_at_period_end',
    'ended_at',
    'suspended_at',
])]
class Account extends RuntimeModel
{
    use HasDomainIdentifiers;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'owning_account_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'plan_status' => PlanStatus::class,
            'plan_started_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'access_ends_at' => 'immutable_datetime',
            'cancel_at_period_end' => 'boolean',
            'ended_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
        ];
    }
}
