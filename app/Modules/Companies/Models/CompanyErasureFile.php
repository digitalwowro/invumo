<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Foundation\Database\RuntimeModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $data_erasure_event_id
 * @property string|null $storage_disk
 * @property string|null $storage_key
 * @property string|null $storage_configuration_fingerprint
 * @property int $attempt_count
 * @property CarbonImmutable|null $last_attempted_at
 * @property string|null $last_failure_category
 * @property string|null $last_failure_summary
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'data_erasure_event_id', 'storage_disk', 'storage_key', 'storage_configuration_fingerprint', 'attempt_count',
    'last_attempted_at', 'last_failure_category', 'last_failure_summary',
    'completed_at', 'created_at',
])]
final class CompanyErasureFile extends RuntimeModel
{
    use HasDomainIdentifiers;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'last_attempted_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
