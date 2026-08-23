<?php

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

final class CompanyInvitationException extends RuntimeException
{
    private function __construct(private readonly string $reason)
    {
        parent::__construct('The Company invitation action could not be completed.');
    }

    public static function alreadyMember(): self
    {
        return new self('already_member');
    }

    public static function alreadyPending(): self
    {
        return new self('already_pending');
    }

    public static function unavailable(): self
    {
        return new self('unavailable');
    }

    public static function emailMismatch(): self
    {
        return new self('email_mismatch');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
