<?php

namespace App\Modules\Invoices\Data;

use RuntimeException;

final class ScheduledInvoiceFailure extends RuntimeException
{
    public static function incomplete(): self
    {
        return new self('issue_incomplete');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
