<?php

namespace App\Modules\Companies\Rules;

use App\Foundation\Money\DecimalRules;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use InvalidArgumentException;

final readonly class CurrencyPrecisionCompatibility
{
    public function allows(CompanyCurrency $currency, int $precision): bool
    {
        if ($currency->currency_precision === $precision) {
            return true;
        }

        $products = ProductService::query()
            ->where('currency_id', $currency->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'unit_price']);

        // Explicit recurring currency rows are fixed snapshots. The lock closes
        // a concurrent source-selection race without rewriting those snapshots.
        RecurringTemplateCustomerValue::query()
            ->where('currency_id', $currency->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        foreach ($products as $product) {
            try {
                DecimalRules::storedMoney((string) $product->unit_price, $precision);
            } catch (InvalidArgumentException) {
                return false;
            }
        }

        return true;
    }
}
