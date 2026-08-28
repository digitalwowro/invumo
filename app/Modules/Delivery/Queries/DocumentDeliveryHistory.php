<?php

namespace App\Modules\Delivery\Queries;

use App\Modules\Delivery\Models\EmailDelivery;

final readonly class DocumentDeliveryHistory
{
    public function exists(string $documentId): bool
    {
        return EmailDelivery::query()->where('document_id', $documentId)->exists();
    }
}
