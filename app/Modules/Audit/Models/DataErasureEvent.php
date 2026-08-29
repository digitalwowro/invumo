<?php

namespace App\Modules\Audit\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Foundation\Database\RuntimeModel;
use App\Modules\Audit\Data\DataErasureAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string|null $actor_user_id
 * @property DataErasureAction $action
 * @property string $subject_type
 * @property string $subject_id
 * @property CarbonImmutable $occurred_at
 */
#[Fillable(['actor_user_id', 'action', 'subject_type', 'subject_id', 'occurred_at'])]
final class DataErasureEvent extends RuntimeModel
{
    use HasDomainIdentifiers;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action' => DataErasureAction::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
