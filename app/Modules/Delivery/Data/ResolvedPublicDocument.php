<?php

namespace App\Modules\Delivery\Data;

use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Models\Document;

final readonly class ResolvedPublicDocument
{
    public function __construct(
        public Company $company,
        public Document $document,
        public PublicDocumentLink $link,
    ) {}
}
