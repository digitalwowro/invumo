<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Data\DocumentLinePersistence;
use App\Modules\Documents\Models\Document;

final class FinalizeDocumentDraftUpdate
{
    public function handle(Document $document, DocumentLinePersistence $lines): void
    {
        $document->update([
            'subtotal' => $lines->subtotal,
            'tax_total' => $lines->taxTotal,
            'total' => $lines->total,
            'edit_version' => $document->edit_version + 1,
            'content_version' => $document->content_version + 1,
        ]);
    }
}
