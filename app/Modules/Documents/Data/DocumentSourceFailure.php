<?php

namespace App\Modules\Documents\Data;

use DomainException;

final class DocumentSourceFailure extends DomainException
{
    public static function lineUnavailable(): self
    {
        return new self('source_unavailable');
    }

    public static function currencyUnavailable(): self
    {
        return new self('currency_unavailable');
    }

    public static function bankUnavailable(): self
    {
        return new self('bank_unavailable');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
