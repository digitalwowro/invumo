<?php

namespace App\Modules\Customers\Exceptions;

use DomainException;

final class CustomerDeliveryException extends DomainException
{
    public static function customerArchived(): self
    {
        return new self('delivery_customer_archived');
    }

    public static function invalidContact(): self
    {
        return new self('delivery_invalid_contact');
    }

    public static function duplicateRecipient(): self
    {
        return new self('delivery_duplicate_recipient');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
