<?php

namespace App\Modules\Recurring\Queries;

use App\Modules\Recurring\Data\RecurringDeliverySource;
use App\Modules\Recurring\Models\RecurringOccurrence;
use App\Modules\Recurring\Models\RecurringTemplate;

final class RecurringInvoiceSource
{
    public function current(string $invoiceId): ?RecurringDeliverySource
    {
        return $this->resolve($invoiceId, false);
    }

    public function lock(string $invoiceId): ?RecurringDeliverySource
    {
        return $this->resolve($invoiceId, true);
    }

    private function resolve(string $invoiceId, bool $lock): ?RecurringDeliverySource
    {
        $preview = RecurringOccurrence::query()->where('invoice_id', $invoiceId)->first();
        if (! $preview instanceof RecurringOccurrence) {
            return null;
        }

        $templateQuery = RecurringTemplate::query()->whereKey($preview->recurring_template_id);
        $occurrenceQuery = RecurringOccurrence::query()->whereKey($preview->id);
        $template = $lock ? $templateQuery->lockForUpdate()->first() : $templateQuery->first();
        $occurrence = $lock ? $occurrenceQuery->lockForUpdate()->first() : $occurrenceQuery->first();

        if (! $template instanceof RecurringTemplate
            || ! $occurrence instanceof RecurringOccurrence
            || $occurrence->recurring_template_id !== $template->id
            || $occurrence->invoice_id !== $invoiceId) {
            return null;
        }

        return new RecurringDeliverySource($template, $occurrence);
    }
}
