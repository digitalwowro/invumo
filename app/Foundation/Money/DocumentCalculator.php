<?php

declare(strict_types=1);

namespace App\Foundation\Money;

use Brick\Math\BigDecimal;

final class DocumentCalculator
{
    /**
     * @param  iterable<LineAmounts>  $lines
     */
    public function calculate(iterable $lines, int $currencyPrecision): DocumentAmounts
    {
        $precision = DecimalRules::currencyPrecision($currencyPrecision);
        $totals = array_fill(0, 6, BigDecimal::zero()->toScale($precision));

        foreach ($lines as $line) {
            $values = [
                $line->itemsSubtotal,
                $line->itemsTotal,
                $line->discountAmount,
                $line->grandSubtotal,
                $line->taxAmount,
                $line->finalLineTotal,
            ];

            foreach ($values as $index => $value) {
                $totals[$index] = DecimalRules::exactMoney(
                    $totals[$index]->plus(DecimalRules::storedMoney($value, $precision)),
                    $precision,
                );
            }
        }

        return new DocumentAmounts(
            itemsSubtotal: (string) $totals[0],
            itemsTotal: (string) $totals[1],
            discountAmount: (string) $totals[2],
            grandSubtotal: (string) $totals[3],
            taxAmount: (string) $totals[4],
            finalTotal: (string) $totals[5],
        );
    }
}
