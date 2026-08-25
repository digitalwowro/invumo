<?php

namespace App\Modules\Companies\Rules;

use App\Modules\Companies\Data\BankAccountData;
use App\Modules\Companies\Data\BankRoutingField;
use App\Modules\Companies\Exceptions\BankAccountException;

final readonly class BankAccountDataValidator
{
    public function validate(BankAccountData $data): void
    {
        $this->bounded($data->label, 120);
        $this->bounded($data->bankName, 160);
        $this->bounded($data->accountHolder, 160);
        $this->bounded($data->accountNumber, 64);

        if (preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $data->swiftBic) !== 1) {
            throw BankAccountException::routingDetailsInvalid();
        }

        if (count($data->localRoutingDetails) > count(BankRoutingField::cases())) {
            throw BankAccountException::routingDetailsInvalid();
        }

        $allowed = array_fill_keys(BankRoutingField::values(), true);

        foreach ($data->localRoutingDetails as $key => $value) {
            if (! is_string($key) || ! is_string($value) || ! isset($allowed[$key])) {
                throw BankAccountException::routingDetailsInvalid();
            }

            $this->bounded($value, 64);
        }
    }

    private function bounded(string $value, int $maximum): void
    {
        $length = mb_strlen(trim($value));

        if ($length < 1 || $length > $maximum) {
            throw BankAccountException::routingDetailsInvalid();
        }
    }
}
