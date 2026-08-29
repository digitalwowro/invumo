<?php

namespace App\Modules\Recurring\Queries;

use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Models\Document;
use App\Modules\Recurring\Models\RecurringOccurrence;
use App\Modules\Recurring\Models\RecurringTemplate;

final class RecurringInvoiceAutomationState
{
    /** @return array{currencyReviewRequired: bool, templateUrl: string|null} */
    public function for(Company $company, Document $invoice): array
    {
        $occurrence = RecurringOccurrence::query()
            ->where('invoice_id', $invoice->id)->first();

        if (! $occurrence instanceof RecurringOccurrence) {
            return ['currencyReviewRequired' => false, 'templateUrl' => null];
        }

        $template = RecurringTemplate::query()
            ->whereKey($occurrence->recurring_template_id)->first();
        $requiresReview = $template instanceof RecurringTemplate
            && $template->currency_review_required
            && $occurrence->currency_inherited
            && $occurrence->automatic_delivery_suppression_reason
                === 'CURRENCY_REVIEW_REQUIRED'
            && $invoice->currency_code === $template->currency_review_currency;

        return [
            'currencyReviewRequired' => $requiresReview,
            'templateUrl' => $template instanceof RecurringTemplate
                ? route('recurring.edit', [$company, $template], false) : null,
        ];
    }
}
