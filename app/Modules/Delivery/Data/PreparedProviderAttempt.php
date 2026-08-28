<?php

namespace App\Modules\Delivery\Data;

final readonly class PreparedProviderAttempt
{
    public function __construct(
        public string $attemptId,
        public string $documentId,
        public ProviderDelivery $delivery,
    ) {}
}
