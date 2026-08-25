<?php

namespace App\Modules\Catalog\Rules;

use App\Foundation\Money\DecimalRules;
use App\Foundation\Money\DecimalTransport;
use App\Modules\Catalog\Data\ProductServiceData;
use App\Modules\Catalog\Exceptions\ProductServiceException;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

final class ProductServiceDataValidator
{
    /**
     * @param  Collection<int, CompanyCurrency>  $currencies
     * @param  Collection<int, TaxPreset>  $taxPresets
     * @return array<string, mixed>
     */
    public function attributes(
        ProductServiceData $data,
        Collection $currencies,
        Collection $taxPresets,
    ): array {
        $attributes = $data->attributes();

        if (($data->unitPrice === null) !== ($data->currencyId === null)) {
            throw ProductServiceException::priceInvalid();
        }

        if ($data->currencyId !== null) {
            $currency = $currencies->firstWhere('id', $data->currencyId);

            if (! $currency instanceof CompanyCurrency || ! $currency->active) {
                throw ProductServiceException::currencyUnavailable();
            }

            try {
                $exact = DecimalTransport::money((string) $data->unitPrice, $currency->currency_precision);
                $attributes['unit_price'] = (string) DecimalRules::moneySource($exact)->toScale(8);
            } catch (InvalidArgumentException) {
                throw ProductServiceException::priceInvalid();
            }
        }

        if ($data->taxPresetId !== null) {
            $taxPreset = $taxPresets->firstWhere('id', $data->taxPresetId);

            if (! $taxPreset instanceof TaxPreset || $taxPreset->archived_at !== null) {
                throw ProductServiceException::taxUnavailable();
            }
        }

        return $attributes;
    }
}
