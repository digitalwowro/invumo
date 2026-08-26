<?php

namespace App\Modules\Invoices\Data;

enum InvoiceDisplayStatus: string
{
    case Draft = 'DRAFT';
    case Issued = 'ISSUED';
    case Paid = 'PAID';
    case Overdue = 'OVERDUE';
}
