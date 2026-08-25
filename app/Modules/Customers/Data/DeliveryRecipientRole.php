<?php

namespace App\Modules\Customers\Data;

enum DeliveryRecipientRole: string
{
    case To = 'TO';
    case Cc = 'CC';
    case Bcc = 'BCC';
}
