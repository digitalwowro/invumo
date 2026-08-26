<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Documents\Data\DocumentAssignmentSource;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\DocumentNumberEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'document_id', 'document_kind', 'rendered_number', 'event_type',
    'assignment_source', 'occurred_at', 'related_audit_event_id',
])]
class DocumentNumberEvent extends TenantOwnedModel
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_kind' => DocumentKind::class,
            'event_type' => DocumentNumberEventType::class,
            'assignment_source' => DocumentAssignmentSource::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
