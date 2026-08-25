<?php

namespace App\Modules\Customers\Exceptions;

use DomainException;

final class CustomerContactException extends DomainException
{
    public static function customerArchived(): self
    {
        return new self('contact_customer_archived');
    }

    public static function archived(): self
    {
        return new self('contact_archived');
    }

    public static function alreadyArchived(): self
    {
        return new self('contact_already_archived');
    }

    public static function notArchived(): self
    {
        return new self('contact_not_archived');
    }

    public static function recipientDependency(): self
    {
        return new self('contact_recipient_dependency');
    }

    public static function duplicateRecipient(): self
    {
        return new self('contact_duplicate_recipient');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
