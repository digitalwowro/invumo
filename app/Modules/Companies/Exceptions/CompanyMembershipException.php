<?php

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

final class CompanyMembershipException extends RuntimeException
{
    private function __construct(private readonly string $reason)
    {
        parent::__construct('The Company membership action could not be completed.');
    }

    public static function cannotManageSelf(): self
    {
        return new self('cannot_manage_self');
    }

    public static function ownerRequiresTransfer(): self
    {
        return new self('owner_requires_transfer');
    }

    public static function invalidRole(): self
    {
        return new self('invalid_role');
    }

    public static function roleUnchanged(): self
    {
        return new self('role_unchanged');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
