<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Foundation\Delivery\EmailAttachmentMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $document_id
 * @property EmailAttachmentMode $email_attachment_mode
 * @property bool $public_access_enabled
 */
#[Fillable(['document_id', 'email_attachment_mode', 'public_access_enabled'])]
final class DocumentDeliverySetting extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_attachment_mode' => EmailAttachmentMode::class,
            'public_access_enabled' => 'boolean',
        ];
    }
}
