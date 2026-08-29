<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\QueueRecurringInvoiceDelivery;
use App\Modules\Documents\Models\Document;
use App\Modules\Recurring\Models\RecurringOccurrence;
use App\Modules\Recurring\Models\RecurringTemplate;

final readonly class PrepareRecurringAutomaticDelivery
{
    public function __construct(
        private QueueRecurringInvoiceDelivery $queue,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        Company $company,
        RecurringTemplate $template,
        RecurringOccurrence $occurrence,
        Document $invoice,
        bool $currencyInherited,
    ): void {
        if (! $template->automatic_email_enabled) {
            return;
        }

        $occurrence->update(['automatic_email_requested' => true]);
        $currency = (string) $invoice->currency_code;

        if ($currencyInherited && $this->requiresCurrencyReview($template, $currency)) {
            $this->suppressForCurrencyReview($template, $occurrence, $currency);

            return;
        }

        $result = $this->queue->handle($company, $invoice, $occurrence);
        if ($result->failure !== null) {
            $occurrence->update([
                'automatic_delivery_suppression_reason' => $result->failure->value,
            ]);

            return;
        }

        if ($currencyInherited && $template->last_confirmed_delivery_currency === null) {
            $template->update(['last_confirmed_delivery_currency' => $currency]);
        }
    }

    private function requiresCurrencyReview(
        RecurringTemplate $template,
        string $currency,
    ): bool {
        return $template->currency_review_required
            || ($template->last_confirmed_delivery_currency !== null
                && $template->last_confirmed_delivery_currency !== $currency);
    }

    private function suppressForCurrencyReview(
        RecurringTemplate $template,
        RecurringOccurrence $occurrence,
        string $currency,
    ): void {
        $newLatch = ! $template->currency_review_required;
        $template->update([
            'currency_review_required' => true,
            'currency_review_currency' => $template->currency_review_currency ?? $currency,
            'currency_review_detected_at' => $template->currency_review_detected_at ?? now(),
        ]);
        $occurrence->update([
            'automatic_delivery_suppression_reason' => 'CURRENCY_REVIEW_REQUIRED',
        ]);

        if ($newLatch) {
            $this->audit->handle(new AuditEventData(
                actorType: AuditActorType::ScheduledJob,
                actorReference: 'recurring_automation',
                action: 'company.recurring_template.currency_review_required',
                targetType: 'RecurringTemplate',
                targetId: $template->id,
                idempotencyReference: $occurrence->id,
                before: AuditPayload::fromAllowedFields([
                    'currency_review_required' => false,
                ], ['currency_review_required']),
                after: AuditPayload::fromAllowedFields([
                    'currency_review_required' => true,
                    'invoice_id' => $occurrence->invoice_id,
                ], ['currency_review_required', 'invoice_id']),
            ));
        }
    }
}
