<?php

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

final class CompanyOwnershipTransferException extends RuntimeException
{
    private function __construct(private readonly string $reason)
    {
        parent::__construct('Company ownership could not be transferred.');
    }

    public static function memberUnavailable(): self
    {
        return new self('transfer_member_unavailable');
    }

    public static function destinationAccountUnavailable(): self
    {
        return new self('transfer_account_unavailable');
    }

    public static function destinationPlanUnavailable(): self
    {
        return new self('transfer_plan_unavailable');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
