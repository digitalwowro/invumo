<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;

final readonly class RecalculateCompanyPendingReminders
{
    public function __construct(private InvoiceReminderSchedule $schedule) {}

    public function handle(CompanySetting $settings): void
    {
        $documents = Document::query()
            ->where('kind', DocumentKind::Invoice)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($documents as $document) {
            $invoice = Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($invoice->lifecycle === InvoiceLifecycle::Issued) {
                $this->schedule->recalculatePending($document, $invoice, $settings);
            }
        }
    }
}
