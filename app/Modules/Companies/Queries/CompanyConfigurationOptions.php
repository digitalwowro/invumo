<?php

namespace App\Modules\Companies\Queries;

use App\Modules\Companies\Data\CountryCode;
use App\Modules\Companies\Data\CurrencyCode;
use Collator;
use Locale;

final readonly class CompanyConfigurationOptions
{
    /** @return list<array{value: string, label: string}> */
    public function countries(string $locale): array
    {
        $options = array_map(
            fn (string $code): array => [
                'value' => $code,
                'label' => $this->countryLabel($code, $locale),
            ],
            CountryCode::all(),
        );

        if (class_exists(Collator::class)) {
            $collator = new Collator($locale);
            usort($options, function (array $left, array $right) use ($collator): int {
                $comparison = $collator->compare($left['label'], $right['label']);

                return $comparison === false ? 0 : $comparison;
            });
        }

        return $options;
    }

    /** @return list<array{value: string, label: string}> */
    public function currencies(): array
    {
        return array_map(
            fn (string $code): array => ['value' => $code, 'label' => $code],
            CurrencyCode::all(),
        );
    }

    /** @return list<array{value: string, label: string}> */
    public function timezones(): array
    {
        return array_map(
            fn (string $timezone): array => ['value' => $timezone, 'label' => $timezone],
            timezone_identifiers_list(),
        );
    }

    private function countryLabel(string $code, string $locale): string
    {
        if (! class_exists(Locale::class)) {
            return $code;
        }

        $label = Locale::getDisplayRegion("und_{$code}", $locale);

        return is_string($label) && $label !== '' ? $label : $code;
    }
}
