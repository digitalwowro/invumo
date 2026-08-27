<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property EmailTemplateEvent $event_type
 * @property string $language_code
 * @property string $subject
 * @property string $body
 * @property string $button_label
 * @property string|null $signature
 */
#[Fillable([
    'event_type', 'language_code', 'subject', 'body', 'button_label', 'signature',
])]
final class CompanyEmailTemplate extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['event_type' => EmailTemplateEvent::class];
    }
}
