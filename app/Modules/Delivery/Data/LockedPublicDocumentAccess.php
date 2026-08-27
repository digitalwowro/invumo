<?php

namespace App\Modules\Delivery\Data;

use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use Illuminate\Database\Eloquent\Collection;

final readonly class LockedPublicDocumentAccess
{
    /** @param Collection<int, PublicDocumentLink> $links */
    public function __construct(
        public CompanySetting $settings,
        public Document $document,
        public DocumentDeliverySetting $delivery,
        public Collection $links,
    ) {}

    public function current(): ?PublicDocumentLink
    {
        return $this->links->first(
            fn (PublicDocumentLink $link): bool => $link->revoked_at === null,
        );
    }

    public function nextGeneration(): int
    {
        return ((int) $this->links->max('generation')) + 1;
    }
}
