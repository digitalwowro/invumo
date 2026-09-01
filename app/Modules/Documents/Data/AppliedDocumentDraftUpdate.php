<?php

namespace App\Modules\Documents\Data;

use App\Modules\Documents\Models\Document;

final readonly class AppliedDocumentDraftUpdate
{
    /** @param list<string> $changedFields */
    public function __construct(
        public Document $document,
        public int $lineCount,
        public DocumentLinePersistence $lines,
        public bool $customerSelectionApplied,
        public array $changedFields,
    ) {}
}
