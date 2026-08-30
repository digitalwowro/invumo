<?php

namespace App\Modules\Documents\Actions;

use App\Foundation\Money\DocumentCalculator;
use App\Foundation\Money\LineAmounts;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Documents\Data\DocumentLineFailure;
use App\Modules\Documents\Data\DocumentLinePersistence;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class PersistDocumentLines
{
    public function __construct(
        private PrepareDocumentLine $prepareLine,
        private DocumentCalculator $documentCalculator,
        private ResolveDocumentLineCustomization $customization,
    ) {}

    /**
     * @param  Collection<int, DocumentLine>  $persisted
     * @param  list<DocumentLineData>  $submitted
     */
    public function handle(Document $document, Collection $persisted, array $submitted): DocumentLinePersistence
    {
        $submittedIds = array_values(array_filter(array_map(
            fn (DocumentLineData $line): ?string => $line->id,
            $submitted,
        )));

        if (count($submittedIds) !== count(array_unique($submittedIds))
            || array_diff($submittedIds, $persisted->modelKeys()) !== []) {
            throw DocumentLineFailure::setInvalid();
        }

        $connection = DB::connection(config('database.tenant_connection'));
        $connection->statement('SET CONSTRAINTS document_lines_company_document_position_unique DEFERRED');
        $amounts = [];
        $retainedIds = [];

        foreach ($submitted as $index => $data) {
            $line = $data->id === null ? new DocumentLine : $persisted->firstWhere('id', $data->id);

            if (! $line instanceof DocumentLine) {
                throw DocumentLineFailure::setInvalid();
            }

            $prepared = $this->prepareLine->handle($data, $document->currency_precision);
            $calculation = $prepared->calculation;
            $attributes = [
                ...$prepared->attributes,
                'tax_preset_id' => $data->taxPresetId,
                'is_customized' => $this->customization->for($line, $data),
                ...$this->nullableAmounts($calculation),
            ];
            $line->fill(['document_id' => $document->id, 'position' => $index + 1, ...$attributes]);
            $line->save();
            $retainedIds[] = $line->id;

            if ($calculation instanceof LineAmounts) {
                $amounts[] = $calculation;
            }
        }

        DocumentLine::query()
            ->where('document_id', $document->id)
            ->whereNotIn('id', $retainedIds)
            ->delete();
        $connection->statement('SET CONSTRAINTS document_lines_company_document_position_unique IMMEDIATE');
        $totals = $document->currency_precision === null
            ? ['grand_subtotal' => '0', 'tax_amount' => '0', 'final_total' => '0']
            : $this->documentCalculator->calculate($amounts, $document->currency_precision)->toArray();

        return new DocumentLinePersistence(
            subtotal: $totals['grand_subtotal'],
            taxTotal: $totals['tax_amount'],
            total: $totals['final_total'],
            completeLineCount: count($amounts),
        );
    }

    /** @return array<string, string|null> */
    private function nullableAmounts(?LineAmounts $amounts): array
    {
        return $amounts?->toArray() ?? [
            'items_subtotal' => null, 'items_total' => null, 'discount_amount' => null,
            'grand_subtotal' => null, 'tax_amount' => null, 'final_line_total' => null,
        ];
    }
}
