<?php

namespace App\Modules\Customers\Data;

final readonly class CustomerContactData
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $positionTitle,
        public bool $isPrimary,
        public bool $isBilling,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position_title' => $this->positionTitle,
            'is_primary' => $this->isPrimary,
            'is_billing' => $this->isBilling,
        ];
    }
}
