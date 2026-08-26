<?php

namespace App\Modules\Documents\Data;

enum DocumentKind: string
{
    case Quote = 'QUOTE';
    case Invoice = 'INVOICE';
}
