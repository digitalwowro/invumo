<?php

namespace App\Modules\Identity\Data;

final readonly class UserErasureState
{
    public function __construct(
        public ?string $accountId,
        public int $ownedCompanyCount,
        public int $membershipCount,
        public bool $platformOperator,
    ) {}

    public function blocked(): bool
    {
        return $this->ownedCompanyCount > 0 || $this->platformOperator;
    }

    public function version(): string
    {
        return hash('sha256', implode('|', [
            $this->accountId ?? 'NO_ACCOUNT',
            $this->ownedCompanyCount,
            $this->membershipCount,
            $this->platformOperator ? '1' : '0',
        ]));
    }
}
