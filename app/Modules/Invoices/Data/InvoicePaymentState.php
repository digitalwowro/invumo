<?php

namespace App\Modules\Invoices\Data;

enum InvoicePaymentState: string
{
    case Unpaid = 'UNPAID';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
}
