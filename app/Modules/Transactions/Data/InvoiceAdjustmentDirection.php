<?php

namespace App\Modules\Transactions\Data;

enum InvoiceAdjustmentDirection: string
{
    case IncreasePaid = 'INCREASE_PAID';
    case DecreasePaid = 'DECREASE_PAID';
}
