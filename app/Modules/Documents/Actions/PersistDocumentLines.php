<?php

namespace App\Modules\Documents\Actions;

use App\Foundation\Money\DecimalRules;
use App\Foundation\Money\DocumentCalculator;
use App\Foundation\Money\LineAmounts;
use App\Foundation\Money\LineCalculationInput;
use App\Foundation\Money\LineCalculator;
use App\Foundation\Money\PeriodUnit;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Documents\Data\DocumentLineFailure;
use App\Modules\Documents\Data\DocumentLinePersistence;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class PersistDocumentLines
{
    public function __construct(
        private LineCalculator $lineCalculator,
        private DocumentCalculator $documentCalculator,
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

            [$attributes, $calculation] = $this->attributes($data, $document->currency_precision);
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

    /** @return array{array<string, mixed>, LineAmounts|null} */
    private function attributes(DocumentLineData $data, ?int $precision): array
    {
        try {
            $data->itemPrice === null || DecimalRules::moneySource($data->itemPrice);
            $data->quantity === null || DecimalRules::quantity($data->quantity);
            $data->periodQuantity === null || DecimalRules::quantity($data->periodQuantity);
            DecimalRules::percentage($data->discountPercentage, true);
            DecimalRules::percentage($data->taxPercentage);

            if (($data->periodUnit === PeriodUnit::None && $data->periodQuantity !== null)
                || ! $this->validText($data->description, DocumentFieldLimits::DESCRIPTION, false)
                || ! $this->validText($data->unit, DocumentFieldLimits::UNIT)
                || ! $this->validText($data->taxName, DocumentFieldLimits::TAX_NAME)) {
                throw new InvalidArgumentException;
            }
        } catch (InvalidArgumentException) {
            throw DocumentLineFailure::valueInvalid();
        }

        $complete = $precision !== null && $data->itemPrice !== null && $data->quantity !== null
            && ($data->periodUnit === PeriodUnit::None || $data->periodQuantity !== null);
        $calculation = $complete ? $this->lineCalculator->calculate(new LineCalculationInput(
            unitPrice: (string) $data->itemPrice,
            quantity: (string) $data->quantity,
            periodUnit: $data->periodUnit,
            periodQuantity: $data->periodQuantity,
            discountPercentage: $data->discountPercentage,
            taxPercentage: $data->taxPercentage,
            currencyPrecision: $precision,
        )) : null;

        return [[
            'product_service_id' => $data->productServiceId, 'description' => $data->description,
            'item_price' => $data->itemPrice, 'quantity' => $data->quantity, 'unit' => $data->unit,
            'period_unit' => $data->periodUnit, 'period_quantity' => $data->periodQuantity,
            'discount_percentage' => $data->discountPercentage, 'tax_name' => $data->taxName,
            'tax_percentage' => $data->taxPercentage, 'tax_preset_id' => $data->taxPresetId,
            ...$this->nullableAmounts($calculation),
        ], $calculation];
    }

    /** @return array<string, string|null> */
    private function nullableAmounts(?LineAmounts $amounts): array
    {
        return $amounts?->toArray() ?? [
            'items_subtotal' => null, 'items_total' => null, 'discount_amount' => null,
            'grand_subtotal' => null, 'tax_amount' => null, 'final_line_total' => null,
        ];
    }

    private function validText(?string $value, int $maximum, bool $trimmed = true): bool
    {
        return $value === null || (mb_strlen($value) >= 1 && mb_strlen($value) <= $maximum
            && (! $trimmed || trim($value) === $value));
    }
}
