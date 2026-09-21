<?php

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

final class CompanyCurrencyException extends RuntimeException
{
    private function __construct(private readonly string $errorReason)
    {
        parent::__construct($errorReason);
    }

    public static function duplicate(): self
    {
        return new self('duplicate');
    }

    public static function inactive(): self
    {
        return new self('inactive');
    }

    public static function active(): self
    {
        return new self('active');
    }

    public static function defaultDependency(): self
    {
        return new self('default_dependency');
    }

    public static function sourceDependencies(): self
    {
        return new self('source_dependencies');
    }

    public static function precisionDependency(): self
    {
        return new self('precision_dependency');
    }

    public function reason(): string
    {
        return $this->errorReason;
    }

    public function validationField(): string
    {
        return match ($this->errorReason) {
            'duplicate' => 'currency_code',
            'precision_dependency' => 'currency_precision',
            default => 'currency',
        };
    }
}
