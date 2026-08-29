<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Foundation\Delivery\EmailAttachmentMode;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Documents\Data\DocumentKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $company_id
 * @property string|null $document_id
 * @property string|null $public_document_link_id
 * @property string|null $reminder_instance_id
 * @property string|null $invoice_transaction_id
 * @property int|null $invoice_transaction_edit_version
 * @property DocumentKind $document_kind
 * @property EmailTemplateEvent $event_type
 * @property string $delivery_key
 * @property int $document_edit_version
 * @property string $language_code
 * @property string|null $subject
 * @property string|null $body
 * @property string|null $button_label
 * @property string|null $signature
 * @property string|null $button_url
 * @property EmailAttachmentMode|null $attachment_mode
 * @property string|null $artifact_id
 * @property string $provider_name
 * @property EmailDeliveryState $dispatch_state
 * @property string|null $provider_message_identifier
 * @property string|null $failure_category
 * @property string|null $failure_summary
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $soft_bounced_at
 * @property CarbonImmutable|null $hard_bounced_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $clicked_at
 * @property CarbonImmutable|null $feedback_loop_at
 * @property CarbonImmutable|null $redacted_at
 * @property string|null $initiated_by_user_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property int $attempts_count
 */
#[Fillable([
    'document_id', 'public_document_link_id', 'reminder_instance_id', 'invoice_transaction_id',
    'invoice_transaction_edit_version',
    'document_kind', 'event_type', 'delivery_key', 'document_edit_version', 'language_code',
    'subject', 'body', 'button_label', 'signature', 'button_url', 'attachment_mode',
    'artifact_id', 'provider_name', 'dispatch_state', 'provider_message_identifier',
    'failure_category', 'failure_summary', 'accepted_at', 'failed_at', 'redacted_at',
    'delivered_at', 'soft_bounced_at', 'hard_bounced_at', 'opened_at', 'clicked_at',
    'feedback_loop_at',
    'initiated_by_user_id',
])]
final class EmailDelivery extends TenantOwnedModel
{
    /** @return HasMany<EmailDeliveryRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(EmailDeliveryRecipient::class, 'delivery_id')->orderBy('display_order');
    }

    /** @return HasMany<EmailDeliveryAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(EmailDeliveryAttempt::class, 'delivery_id')->orderBy('attempt_number');
    }

    /** @return HasMany<EmailProviderEvent, $this> */
    public function providerEvents(): HasMany
    {
        return $this->hasMany(EmailProviderEvent::class, 'delivery_id')->orderBy('occurred_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_kind' => DocumentKind::class,
            'event_type' => EmailTemplateEvent::class,
            'document_edit_version' => 'integer',
            'invoice_transaction_edit_version' => 'integer',
            'attachment_mode' => EmailAttachmentMode::class,
            'dispatch_state' => EmailDeliveryState::class,
            'accepted_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'soft_bounced_at' => 'immutable_datetime',
            'hard_bounced_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'clicked_at' => 'immutable_datetime',
            'feedback_loop_at' => 'immutable_datetime',
            'redacted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
