<?php

namespace App\Modules\Delivery\Queries;

use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Rules\PaymentReceivedDeliveryEligibility;
use App\Modules\Delivery\Rules\ReminderDeliveryEligibility;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Recurring\Data\RecurringDeliverySource;
use App\Modules\Recurring\Queries\RecurringAutomaticDeliveryEligibility;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Collection;

final readonly class ProviderSubmissionEligibility
{
    public function __construct(
        private ReminderDeliveryEligibility $reminders,
        private PaymentReceivedDeliveryEligibility $paymentReceived,
        private RecurringAutomaticDeliveryEligibility $recurring,
    ) {}

    /**
     * @param  Collection<int, InvoiceTransaction>  $transactions
     * @return array{category: string, summary: string}|null
     */
    public function failure(
        EmailDelivery $delivery,
        Document $document,
        ?Invoice $invoice,
        Collection $transactions,
        ?RecurringDeliverySource $recurringSource,
    ): ?array {
        if ($delivery->recurring_automatic && ! $this->recurring->allows(
            $recurringSource,
            $delivery,
            $document,
            $invoice,
        )) {
            return [
                'category' => 'recurring_delivery_no_longer_eligible',
                'summary' => 'The recurring Invoice no longer qualified for automatic delivery.',
            ];
        }

        return match ($delivery->event_type) {
            EmailTemplateEvent::PaymentReminder => $this->reminders->allows(
                $delivery, $document, $invoice, $transactions,
            ) ? null : [
                'category' => 'reminder_no_longer_eligible',
                'summary' => 'The Invoice no longer qualified for this reminder.',
            ],
            EmailTemplateEvent::PaymentReceived => $this->paymentReceived->allows(
                $delivery, $invoice, $transactions,
            ) ? null : [
                'category' => 'payment_received_no_longer_eligible',
                'summary' => 'The referenced Payment no longer qualified for this message.',
            ],
            default => null,
        };
    }
}
