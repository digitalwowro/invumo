<?php

namespace App\Modules\Documents\Data;

use DomainException;

final class DocumentNumberAllocationException extends DomainException
{
    public static function seriesUnavailable(): self
    {
        return new self('series_unavailable');
    }

    public static function exhausted(): self
    {
        return new self('number_exhausted');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
