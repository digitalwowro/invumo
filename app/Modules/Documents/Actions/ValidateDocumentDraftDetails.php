<?php

namespace App\Modules\Documents\Actions;

use App\Foundation\Documents\DocumentCalendar;
use App\Foundation\Documents\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentDraftFailure;
use InvalidArgumentException;

final class ValidateDocumentDraftDetails
{
    public function handle(
        ?string $issueDate,
        ?int $dayOffset,
        ?string $resolvedDate,
        ?string $customerReference,
    ): void {
        try {
            if ($issueDate !== null && $dayOffset !== null) {
                DocumentCalendar::addDays($issueDate, $dayOffset);
            }
        } catch (InvalidArgumentException) {
            throw DocumentDraftFailure::detailsInvalid();
        }

        if ($issueDate !== null && $resolvedDate !== null && $resolvedDate < $issueDate) {
            throw DocumentDraftFailure::detailsInvalid();
        }

        if ($customerReference !== null && (
            trim($customerReference) !== $customerReference
            || mb_strlen($customerReference) < 1
            || mb_strlen($customerReference) > DocumentFieldLimits::CUSTOMER_REFERENCE_CHARACTERS
        )) {
            throw DocumentDraftFailure::detailsInvalid();
        }
    }
}
