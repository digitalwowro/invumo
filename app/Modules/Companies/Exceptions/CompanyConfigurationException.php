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

    public function reason(): string
    {
        return $this->errorReason;
    }
}
