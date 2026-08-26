<?php

namespace App\Modules\Invoices\Data;

enum InvoicePaymentState: string
{
    case Unpaid = 'UNPAID';
    case Paid = 'PAID';
}
