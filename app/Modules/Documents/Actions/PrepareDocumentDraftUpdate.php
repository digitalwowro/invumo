<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Customers\Queries\ResolveDocumentCustomer;
use App\Modules\Documents\Data\DocumentDraftData;
use App\Modules\Documents\Data\DocumentDraftFailure;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Documents\Data\PreparedDocumentDraftUpdate;
use App\Modules\Documents\Models\Document;

final readonly class PrepareDocumentDraftUpdate
{
    public function __construct(
        private LockDocumentConfiguration $lockConfiguration,
        private ResolveDocumentCustomer $resolveCustomer,
    ) {}

    public function handle(
        DocumentKind $kind,
        string $documentId,
        DocumentDraftData $data,
    ): PreparedDocumentDraftUpdate {
        $configuration = $this->lockConfiguration->handle();
        $selection = $this->customerSelection($data, $configuration);
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', $kind)
            ->lockForUpdate()
            ->firstOrFail();

        if ($document->edit_version !== $data->editVersion) {
            throw DocumentDraftFailure::stale();
        }

        return new PreparedDocumentDraftUpdate($document, $configuration, $selection);
    }

    private function customerSelection(
        DocumentDraftData $data,
        LockedDocumentConfiguration $configuration,
    ): ?ResolvedDocumentCustomer {
        if ($data->customerConfirmationToken === null) {
            return null;
        }

        $selection = $this->resolveCustomer->forLocked($data->customerId, $configuration);

        if (! hash_equals($selection->confirmationToken, $data->customerConfirmationToken)) {
            throw DocumentDraftFailure::customerDefaultsChanged();
        }

        return $selection;
    }
}
