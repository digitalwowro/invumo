<?php

namespace App\Modules\Delivery\Data;

final readonly class SendPaymentReceivedData
{
    public function __construct(
        public string $deliveryKey,
        public int $transactionEditVersion,
        public bool $confirmed,
    ) {}
}
