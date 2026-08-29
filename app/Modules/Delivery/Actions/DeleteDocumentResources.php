<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Queries\DocumentPublicLinkHistory;
use App\Modules\Documents\Contracts\DeletesDocumentResources as DeletesDocumentResourcesContract;
use App\Modules\Documents\Data\LockedDocumentDeletionResources;

final readonly class DeleteDocumentResources implements DeletesDocumentResourcesContract
{
    public function __construct(
        private DocumentPublicLinkHistory $publicLinkHistory,
        private DeleteDocumentPublicLinks $deletePublicLinks,
        private LockDocumentDeliveryHistory $deliveryHistory,
        private RedactDocumentDeliveries $redactDeliveries,
    ) {}

    public function lock(string $documentId): LockedDocumentDeletionResources
    {
        $publicLinks = $this->publicLinkHistory->lock($documentId);
        $deliveries = $this->deliveryHistory->all($documentId);

        return new LockedDocumentDeletionResources(
            publicLinkCount: $publicLinks->count(),
            deliveryCount: $deliveries->count(),
            submissionInFlightCount: $this->deliveryHistory->countSubmissionsInFlight($deliveries),
        );
    }

    public function delete(string $companyId, string $documentId): void
    {
        $this->redactDeliveries->handle($companyId, $documentId);
        $this->deletePublicLinks->handle($documentId);
    }
}
