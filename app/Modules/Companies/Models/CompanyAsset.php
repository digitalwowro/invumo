<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAssetPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
