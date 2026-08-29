<?php

namespace App\Modules\Documents\Data;

use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Documents\Models\Document;

final readonly class PreparedDocumentDraftUpdate
{
    public function __construct(
        public Document $document,
        public LockedDocumentConfiguration $configuration,
        public ?ResolvedDocumentCustomer $customerSelection,
    ) {}
}
