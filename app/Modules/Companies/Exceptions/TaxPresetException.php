<?php

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

final class TaxPresetException extends RuntimeException
{
    private function __construct(private readonly string $errorReason)
    {
        parent::__construct($errorReason);
    }

    public static function archived(): self
    {
        return new self('archived');
    }

    public static function defaultDependency(): self
    {
        return new self('default_dependency');
    }

    public function reason(): string
    {
        return $this->errorReason;
    }
}
