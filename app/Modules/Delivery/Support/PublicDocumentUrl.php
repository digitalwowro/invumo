<?php

namespace App\Modules\Delivery\Support;

use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use RuntimeException;

final class PublicDocumentUrl
{
    public function for(DocumentKind $kind, PublicDocumentLink $link): string
    {
        if (! PublicDocumentToken::accepts($link->token_ciphertext)) {
            throw new RuntimeException('Stored public document token plaintext is invalid.');
        }

        return route(
            $kind === DocumentKind::Quote ? 'public-quotes.show' : 'public-invoices.show',
            ['token' => $link->token_ciphertext],
        );
    }
}
