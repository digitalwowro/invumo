<?php

namespace App\Modules\Documents\Actions;

use App\Foundation\Money\PeriodUnit;

final class DocumentLineCompleteness
{
    public function accepts(
        ?int $currencyPrecision,
        ?string $itemPrice,
        ?string $quantity,
        PeriodUnit $periodUnit,
        ?string $periodQuantity,
    ): bool {
        return $currencyPrecision !== null && $this->acceptsInputs(
            $itemPrice,
            $quantity,
            $periodUnit,
            $periodQuantity,
        );
    }

    public function acceptsInputs(
        ?string $itemPrice,
        ?string $quantity,
        PeriodUnit $periodUnit,
        ?string $periodQuantity,
    ): bool {
        return $itemPrice !== null
            && $quantity !== null
            && ($periodUnit === PeriodUnit::None || $periodQuantity !== null);
    }
}
