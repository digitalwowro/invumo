<?php

namespace App\Modules\Delivery\Data;

use App\Modules\Delivery\Models\EmailDelivery;

final readonly class AutomatedDeliveryResult
{
    public function __construct(
        public ?EmailDelivery $delivery,
        public ?AutomatedDeliveryFailure $failure,
    ) {}

    public static function queued(EmailDelivery $delivery): self
    {
        return new self($delivery, null);
    }

    public static function suppressed(AutomatedDeliveryFailure $failure): self
    {
        return new self(null, $failure);
    }
}
