<?php

namespace App\Modules\Invoices\Exceptions;

use RuntimeException;

final class InvoiceLifecycleException extends RuntimeException
{
    public static function stale(): self
    {
        return new self('stale');
    }

    public static function incomplete(): self
    {
        return new self('issue_incomplete');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
