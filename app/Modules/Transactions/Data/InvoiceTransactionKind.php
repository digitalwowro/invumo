<?php

namespace App\Modules\Transactions\Data;

enum InvoiceTransactionKind: string
{
    case Payment = 'PAYMENT';
    case Refund = 'REFUND';
    case Adjustment = 'ADJUSTMENT';
}
