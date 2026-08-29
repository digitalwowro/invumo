<?php

namespace App\Modules\Quotes\Queries;

use App\Modules\Delivery\Queries\DocumentDeletionExposure;
use App\Modules\Quotes\Data\QuoteDeletionState;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use App\Modules\Quotes\Models\QuotePublicDecision;
use RuntimeException;

final readonly class QuoteDeletionPreview
{
    public function __construct(private DocumentDeletionExposure $exposure) {}

    /**
     * @param  array<string, QuoteLifecycle>  $documents
     * @return array<string, array{highRisk: bool, stateVersion: string, guard: array{blocked: bool, description: string|null}}>
     */
    public function forDocuments(array $documents): array
    {
        $ids = array_keys($documents);
        $links = QuoteInvoiceLink::query()
            ->whereIn('quote_id', $ids)
            ->selectRaw('quote_id, count(*) AS aggregate')
            ->groupBy('quote_id')->pluck('aggregate', 'quote_id')
            ->map(fn (mixed $count): int => (int) $count)->all();
        $decisions = QuotePublicDecision::query()
            ->whereIn('quote_id', $ids)
            ->selectRaw('quote_id, count(*) AS aggregate')
            ->groupBy('quote_id')->pluck('aggregate', 'quote_id')
            ->map(fn (mixed $count): int => (int) $count)->all();
        $exposure = $this->exposure->forDocuments($ids);
        $result = [];

        foreach ($documents as $id => $lifecycle) {
            $invoiceCount = $links[$id] ?? 0;
            $submissionCount = $exposure[$id]->submissionInFlightCount ?? 0;
            $state = new QuoteDeletionState(
                $lifecycle,
                $invoiceCount,
                (int) ($decisions[$id] ?? 0),
                $exposure[$id]->publicLinkCount,
                $exposure[$id]->deliveryCount,
                $exposure[$id]->submissionInFlightCount,
            );
            $result[$id] = [
                'highRisk' => $state->highRisk(),
                'stateVersion' => $state->version(),
                'guard' => [
                    'blocked' => $state->blocked(),
                    'description' => $state->blocked() ? $this->description([
                        'invoices' => $invoiceCount,
                        'submissions' => $submissionCount,
                    ]) : null,
                ],
            ];
        }

        return $result;
    }

    /** @param array{invoices: int, submissions: int} $replace */
    private function description(array $replace): string
    {
        $translation = __('quotes_ui.deletion.dependency_description', $replace);

        if (! is_string($translation)) {
            throw new RuntimeException('The Quote deletion dependency text must be a string.');
        }

        return $translation;
    }
}
