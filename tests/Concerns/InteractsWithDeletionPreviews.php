<?php

namespace Tests\Concerns;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Queries\InvoiceDeletionPreview;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Queries\QuoteDeletionPreview;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Queries\RecurringTemplateDeletionPreview;

trait InteractsWithDeletionPreviews
{
    protected function invoiceDeletionState(Company $company, Document|string $invoice): string
    {
        $id = $invoice instanceof Document ? $invoice->id : $invoice;

        return app(TenantContext::class)->runAsSystem($company->id, function () use ($id): string {
            $lifecycle = Invoice::query()->whereKey($id)->firstOrFail()->lifecycle;

            return app(InvoiceDeletionPreview::class)->for($id, $lifecycle)['stateVersion'];
        });
    }

    protected function quoteDeletionState(Company $company, Document|string $quote): string
    {
        $id = $quote instanceof Document ? $quote->id : $quote;

        return app(TenantContext::class)->runAsSystem($company->id, function () use ($id): string {
            $lifecycle = Quote::query()->whereKey($id)->firstOrFail()->lifecycle;

            return app(QuoteDeletionPreview::class)->forDocuments([$id => $lifecycle])[$id]['stateVersion'];
        });
    }

    protected function recurringDeletionState(Company $company, RecurringTemplate|string $template): string
    {
        $id = $template instanceof RecurringTemplate ? $template->id : $template;

        return app(TenantContext::class)->runAsSystem($company->id, function () use ($id): string {
            $state = RecurringTemplate::query()->whereKey($id)->firstOrFail()->state;

            return app(RecurringTemplateDeletionPreview::class)
                ->forTemplates([$id => $state])[$id]['stateVersion'];
        });
    }
}
