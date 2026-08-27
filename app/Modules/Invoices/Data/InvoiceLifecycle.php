<?php

namespace App\Modules\Invoices\Data;

enum InvoiceLifecycle: string
{
    case Draft = 'DRAFT';
    case Issued = 'ISSUED';
    case Cancelled = 'CANCELLED';
}
