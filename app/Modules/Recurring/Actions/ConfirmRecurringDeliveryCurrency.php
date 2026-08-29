<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Documents\Models\Document;
use App\Modules\Recurring\Data\RecurringDeliverySource;

final readonly class ConfirmRecurringDeliveryCurrency
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(
        ?RecurringDeliverySource $source,
        EmailDelivery $delivery,
        Document $document,
    ): void {
        if (! $source instanceof RecurringDeliverySource
            || $delivery->recurring_automatic
            || $delivery->initiated_by_user_id === null
            || ! $source->template->currency_review_required
            || ! $source->occurrence->automatic_email_requested
            || $source->occurrence->automatic_delivery_suppression_reason
                !== 'CURRENCY_REVIEW_REQUIRED'
            || $document->currency_code === null
            || $document->currency_code !== $source->template->currency_review_currency) {
            return;
        }

        $source->template->update([
            'last_confirmed_delivery_currency' => $document->currency_code,
            'currency_review_required' => false,
            'currency_review_currency' => null,
            'currency_review_detected_at' => null,
        ]);
        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::System,
            action: 'company.recurring_template.currency_review_confirmed',
            targetType: 'RecurringTemplate',
            targetId: $source->template->id,
            idempotencyReference: $delivery->id,
            before: AuditPayload::fromAllowedFields([
                'currency_review_required' => true,
            ], ['currency_review_required']),
            after: AuditPayload::fromAllowedFields([
                'currency_review_required' => false,
                'confirming_delivery_id' => $delivery->id,
            ], ['currency_review_required', 'confirming_delivery_id']),
        ));
    }
}
