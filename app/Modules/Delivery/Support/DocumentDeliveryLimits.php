<?php

namespace App\Modules\Delivery\Support;

final class DocumentDeliveryLimits
{
    public const HARD_MAX_RECIPIENTS = 10;

    public static function recipientsPerMessage(): int
    {
        return max(1, min(
            self::HARD_MAX_RECIPIENTS,
            (int) config('invumo.document_delivery.max_recipients_per_message', self::HARD_MAX_RECIPIENTS),
        ));
    }
}
