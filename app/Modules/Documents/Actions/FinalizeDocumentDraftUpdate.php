<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Data\DocumentLinePersistence;
use App\Modules\Documents\Models\Document;

final class FinalizeDocumentDraftUpdate
{
    public function handle(
        Document $document,
        DocumentLinePersistence $lines,
        bool $advanceVersions = true,
    ): void {
        $document->update([
            'subtotal' => $lines->subtotal,
            'tax_total' => $lines->taxTotal,
            'total' => $lines->total,
            'edit_version' => $document->edit_version + ($advanceVersions ? 1 : 0),
            'content_version' => $document->content_version + ($advanceVersions ? 1 : 0),
        ]);
    }
}
