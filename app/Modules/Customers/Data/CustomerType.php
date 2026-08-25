<?php

namespace App\Modules\Customers\Data;

enum CustomerType: string
{
    case Individual = 'INDIVIDUAL';
    case Company = 'COMPANY';
}
