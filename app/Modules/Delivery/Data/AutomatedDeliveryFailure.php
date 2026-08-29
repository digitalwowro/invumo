<?php

namespace App\Modules\Delivery\Data;

enum AutomatedDeliveryFailure: string
{
    case PublicAccessDisabled = 'PUBLIC_ACCESS_DISABLED';
    case RecipientsUnavailable = 'RECIPIENTS_UNAVAILABLE';
}
