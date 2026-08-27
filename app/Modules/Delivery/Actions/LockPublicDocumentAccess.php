<?php

namespace App\Modules\Delivery\Actions;

use App\Models\User;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\LockedPublicDocumentAccess;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Models\Quote;

final readonly class LockPublicDocumentAccess
{
    public function __construct(private AuthorizesCompanyActions $authorizer) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): LockedPublicDocumentAccess {
        $this->authorizer->authorize($actor, $company, $kind->manageAbility());
        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', $kind)
            ->lockForUpdate()
            ->firstOrFail();

        match ($kind) {
            DocumentKind::Quote => Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
            DocumentKind::Invoice => Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
        };

        $delivery = DocumentDeliverySetting::query()
            ->where('document_id', $document->id)
            ->lockForUpdate()
            ->firstOrFail();
        $links = PublicDocumentLink::query()
            ->where('document_id', $document->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return new LockedPublicDocumentAccess($settings, $document, $delivery, $links);
    }
}
