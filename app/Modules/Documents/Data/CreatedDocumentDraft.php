<?php

namespace App\Modules\Documents\Data;

use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Documents\Models\Document;

final readonly class CreatedDocumentDraft
{
    public function __construct(
        public Document $document,
        public CompanySetting $settings,
        public string $localDate,
    ) {}
}
