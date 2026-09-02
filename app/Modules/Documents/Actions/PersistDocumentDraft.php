<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Documents\Data\DocumentDraftData;
use App\Modules\Documents\Data\DocumentLinePersistence;
use App\Modules\Documents\Data\PreparedDocumentDraftUpdate;

final readonly class PersistDocumentDraft
{
    public function __construct(
        private ApplyDocumentCustomer $applyCustomer,
        private ApplyDocumentTaxDefault $applyTaxDefault,
        private ApplyDocumentDraftSources $applyDraftSources,
        private LockDocumentLineSources $sourceGuard,
        private PersistDocumentLines $persistLines,
    ) {}

    public function handle(
        PreparedDocumentDraftUpdate $prepared,
        DocumentDraftData $data,
    ): DocumentLinePersistence {
        $document = $prepared->document;
        $persisted = $this->sourceGuard->lockSourcesAndLines(
            $document->id,
            $data->lines,
            $prepared->configuration,
        );

        if ($prepared->customerSelection instanceof ResolvedDocumentCustomer) {
            $this->applyCustomer->handle($document, $prepared->customerSelection);
        }

        $this->applyTaxDefault->handle(
            $document,
            $data->taxDefaultPresetId,
            $prepared->configuration,
        );

        $this->applyDraftSources->handle(
            $document,
            $data->currencyCode,
            $data->documentLanguage,
            $data->bankAccountId,
            $data->termsAndConditions,
            $data->notes,
            $prepared->configuration,
        );
        $document->fill([
            'issue_date' => $data->issueDate,
            'customer_reference' => $data->customerReference,
        ]);

        return $this->persistLines->handle($document, $persisted, $data->lines);
    }
}
