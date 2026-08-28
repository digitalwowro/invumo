<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $document_id
 * @property string $id
 * @property string $company_id
 * @property int $document_edit_version
 * @property string $storage_disk
 * @property string $storage_key
 * @property string $file_name
 * @property string $mime_type
 * @property int $byte_size
 * @property string $sha256
 * @property CarbonImmutable $generated_at
 */
#[Fillable([
    'document_id', 'artifact_type', 'document_edit_version', 'storage_disk', 'storage_key',
    'file_name', 'mime_type', 'byte_size', 'sha256', 'generated_at',
])]
final class DocumentArtifact extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_edit_version' => 'integer',
            'byte_size' => 'integer',
            'generated_at' => 'immutable_datetime',
        ];
    }
}
