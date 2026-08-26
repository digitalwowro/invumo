<?php

namespace App\Modules\Transactions\Data;

final class InvoiceTransactionFieldLimits
{
    public const PAYMENT_METHOD = 120;

    public const REFERENCE = 500;

    public const NOTES = 5000;

    public const ADJUSTMENT_REASON = 500;
}
