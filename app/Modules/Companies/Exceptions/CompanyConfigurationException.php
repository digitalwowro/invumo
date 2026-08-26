<?php

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

final class CompanyConfigurationException extends RuntimeException
{
    private function __construct(private readonly string $errorReason)
    {
        parent::__construct($errorReason);
    }

    public static function scheduleChangeNotConfirmed(): self
    {
        return new self('schedule_change_not_confirmed');
    }

    public static function currencyPrecisionDependency(): self
    {
        return new self('currency_precision_dependency');
    }

    public function reason(): string
    {
        return $this->errorReason;
    }

    public function validationField(): string
    {
        return match ($this->errorReason) {
            'currency_precision_dependency' => 'currency_precision',
            default => 'confirm_schedule_change',
        };
    }
}
