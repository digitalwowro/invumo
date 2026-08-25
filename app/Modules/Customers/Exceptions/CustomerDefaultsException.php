<?php

namespace App\Modules\Customers\Exceptions;

use DomainException;

final class CustomerDefaultsException extends DomainException
{
    public static function customerArchived(): self
    {
        return new self('defaults_customer_archived');
    }

    public static function currencyUnavailable(): self
    {
        return new self('defaults_currency_unavailable');
    }

    public static function languageUnavailable(): self
    {
        return new self('defaults_language_unavailable');
    }

    public static function paymentTermInvalid(): self
    {
        return new self('defaults_payment_term_invalid');
    }

    public static function taxPresetUnavailable(): self
    {
        return new self('defaults_tax_preset_unavailable');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
