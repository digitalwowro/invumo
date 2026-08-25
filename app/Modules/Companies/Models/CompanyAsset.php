<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAssetPurpose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $company_id
 * @property CompanyAssetPurpose $purpose
 * @property string $storage_disk
 * @property string $storage_key
 * @property string $mime_type
 * @property int $byte_size
 * @property string $content_sha256
 * @property int $pixel_width
 * @property int $pixel_height
 * @property string|null $created_by_user_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'purpose',
    'storage_disk',
    'storage_key',
    'mime_type',
    'byte_size',
    'content_sha256',
    'pixel_width',
    'pixel_height',
    'created_by_user_id',
    'deleted_at',
])]
class CompanyAsset extends TenantOwnedModel
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purpose' => CompanyAssetPurpose::class,
            'byte_size' => 'integer',
            'pixel_width' => 'integer',
            'pixel_height' => 'integer',
            'created_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
