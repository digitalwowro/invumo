<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Delivery\Queries\DocumentPublicLinkHistory;

final readonly class DeleteDocumentPublicLinks
{
    public function __construct(private DocumentPublicLinkHistory $history) {}

    public function handle(string $documentId): int
    {
        $links = $this->history->lock($documentId);
        $links->each(fn (PublicDocumentLink $link): bool => $link->delete());

        return $links->count();
    }
}
