<?php

declare(strict_types=1);

namespace App\Foundation\Money;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final class LineCalculator
{
    public function calculate(LineCalculationInput $input): LineAmounts
    {
        $precision = DecimalRules::currencyPrecision($input->currencyPrecision);
        $unitPrice = DecimalRules::moneySource($input->unitPrice);
        $quantity = DecimalRules::quantity($input->quantity);
        $discountPercentage = DecimalRules::percentage($input->discountPercentage, true);
        $taxPercentage = DecimalRules::percentage($input->taxPercentage);
        $periodQuantity = $this->resolvePeriodQuantity($input);

        $itemsSubtotal = DecimalRules::roundMoney($unitPrice->multipliedBy($quantity), $precision);
        $itemsTotal = $periodQuantity === null
            ? $itemsSubtotal
            : DecimalRules::roundMoney($itemsSubtotal->multipliedBy($periodQuantity), $precision);
        $discountAmount = DecimalRules::roundMoney(
            $itemsTotal->multipliedBy($discountPercentage)->dividedByExact('100'),
            $precision,
        );
        $grandSubtotal = DecimalRules::exactMoney($itemsTotal->minus($discountAmount), $precision);
        $taxAmount = DecimalRules::roundMoney(
            $grandSubtotal->multipliedBy($taxPercentage)->dividedByExact('100'),
            $precision,
        );
        $finalLineTotal = DecimalRules::exactMoney($grandSubtotal->plus($taxAmount), $precision);

        return new LineAmounts(
            itemsSubtotal: (string) $itemsSubtotal,
            itemsTotal: (string) $itemsTotal,
            discountAmount: (string) $discountAmount,
            grandSubtotal: (string) $grandSubtotal,
            taxAmount: (string) $taxAmount,
            finalLineTotal: (string) $finalLineTotal,
        );
    }

    private function resolvePeriodQuantity(LineCalculationInput $input): ?BigDecimal
    {
        if ($input->periodUnit === PeriodUnit::None) {
            if ($input->periodQuantity !== null) {
                throw new InvalidArgumentException('Lines without a period cannot have a period quantity.');
            }

            return null;
        }

        if ($input->periodQuantity === null) {
            throw new InvalidArgumentException('Periodic lines require a period quantity.');
        }

        return DecimalRules::quantity($input->periodQuantity);
    }
}
