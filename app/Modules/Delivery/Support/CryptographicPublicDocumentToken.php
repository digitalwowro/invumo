<?php

namespace App\Modules\Delivery\Support;

use App\Modules\Delivery\Contracts\GeneratesPublicDocumentTokens;
use App\Modules\Delivery\Data\PublicDocumentToken;

final readonly class CryptographicPublicDocumentToken implements GeneratesPublicDocumentTokens
{
    public function generate(): PublicDocumentToken
    {
        return PublicDocumentToken::fromBytes(random_bytes(PublicDocumentToken::BYTES));
    }
}
