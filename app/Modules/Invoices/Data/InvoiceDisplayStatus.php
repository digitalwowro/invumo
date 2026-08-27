<?php

namespace App\Modules\Invoices\Data;

enum InvoiceDisplayStatus: string
{
    case Draft = 'DRAFT';
    case Issued = 'ISSUED';
    case Cancelled = 'CANCELLED';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Overdue = 'OVERDUE';
}
