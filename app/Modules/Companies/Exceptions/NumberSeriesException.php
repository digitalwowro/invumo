<?php

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

final class NumberSeriesException extends RuntimeException
{
    /** @param list<string> $fields */
    private function __construct(
        private readonly string $errorReason,
        private readonly array $fields,
    ) {
        parent::__construct($errorReason);
    }

    /** @param list<string> $fields */
    public static function invalidConfiguration(array $fields): self
    {
        return new self('invalid_configuration', $fields);
    }

    /** @param list<string> $fields */
    public static function timezoneRequired(array $fields): self
    {
        return new self('timezone_required', $fields);
    }

    public function reason(): string
    {
        return $this->errorReason;
    }

    /** @return list<string> */
    public function fields(): array
    {
        return $this->fields;
    }
}
