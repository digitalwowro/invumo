<?php

namespace App\Modules\Invoices\Queries;

use App\Modules\Delivery\Queries\DocumentDeletionExposure;
use App\Modules\Invoices\Data\InvoiceDeletionState;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use App\Modules\Transactions\Models\InvoiceTransaction;
use RuntimeException;

final readonly class InvoiceDeletionPreview
{
    public function __construct(private DocumentDeletionExposure $exposure) {}

    /** @return array{highRisk: bool, stateVersion: string, guard: array{blocked: bool, description: string|null}} */
    public function for(string $documentId, InvoiceLifecycle $lifecycle): array
    {
        $transactions = InvoiceTransaction::query()->where('invoice_id', $documentId)->count();
        $quotes = QuoteInvoiceLink::query()->where('invoice_id', $documentId)->count();
        $exposure = $this->exposure->forDocuments([$documentId])[$documentId];
        $state = new InvoiceDeletionState(
            $lifecycle,
            $transactions,
            $quotes,
            $exposure->publicLinkCount,
            $exposure->deliveryCount,
            $exposure->submissionInFlightCount,
        );

        return [
            'highRisk' => $state->highRisk(),
            'stateVersion' => $state->version(),
            'guard' => [
                'blocked' => $state->blocked(),
                'description' => $state->blocked() ? $this->description([
                    'transactions' => $transactions,
                    'quotes' => $quotes,
                    'submissions' => $exposure->submissionInFlightCount,
                ]) : null,
            ],
        ];
    }

    /** @param array{transactions: int, quotes: int, submissions: int} $replace */
    private function description(array $replace): string
    {
        $translation = __('invoices_ui.deletion.dependency_description', $replace);

        if (! is_string($translation)) {
            throw new RuntimeException('The Invoice deletion dependency text must be a string.');
        }

        return $translation;
    }
}
