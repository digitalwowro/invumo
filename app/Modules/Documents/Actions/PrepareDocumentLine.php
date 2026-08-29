<?php

namespace App\Modules\Documents\Actions;

use App\Foundation\Money\DecimalRules;
use App\Foundation\Money\LineCalculationInput;
use App\Foundation\Money\LineCalculator;
use App\Foundation\Money\PeriodUnit;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Documents\Data\DocumentLineFailure;
use App\Modules\Documents\Data\PreparedDocumentLine;
use InvalidArgumentException;

final readonly class PrepareDocumentLine
{
    public function __construct(private LineCalculator $calculator) {}

    public function handle(DocumentLineData $data, ?int $precision): PreparedDocumentLine
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
        $calculation = $complete ? $this->calculator->calculate(new LineCalculationInput(
            unitPrice: $data->itemPrice,
            quantity: $data->quantity,
            periodUnit: $data->periodUnit,
            periodQuantity: $data->periodQuantity,
            discountPercentage: $data->discountPercentage,
            taxPercentage: $data->taxPercentage,
            currencyPrecision: $precision,
        )) : null;

        return new PreparedDocumentLine([
            'product_service_id' => $data->productServiceId,
            'description' => $data->description,
            'item_price' => $data->itemPrice,
            'quantity' => $data->quantity,
            'unit' => $data->unit,
            'period_unit' => $data->periodUnit,
            'period_quantity' => $data->periodQuantity,
            'discount_percentage' => $data->discountPercentage,
            'tax_name' => $data->taxName,
            'tax_percentage' => $data->taxPercentage,
        ], $calculation);
    }

    private function validText(?string $value, int $maximum, bool $trimmed = true): bool
    {
        return $value === null || (mb_strlen($value) >= 1 && mb_strlen($value) <= $maximum
            && (! $trimmed || trim($value) === $value));
    }
}
