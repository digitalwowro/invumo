<?php

namespace App\Modules\Quotes\Data;

enum QuoteLifecycle: string
{
    case Draft = 'DRAFT';
    case Sent = 'SENT';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
}
