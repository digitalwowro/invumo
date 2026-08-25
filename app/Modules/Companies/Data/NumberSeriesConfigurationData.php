<?php

namespace App\Modules\Companies\Data;

final readonly class NumberSeriesConfigurationData
{
    public function __construct(
        public NumberSeriesData $quote,
        public NumberSeriesData $invoice,
    ) {}

    /** @return list<NumberSeriesData> */
    public function all(): array
    {
        return [$this->quote, $this->invoice];
    }
}
