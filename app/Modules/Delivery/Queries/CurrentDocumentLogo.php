<?php

namespace App\Modules\Delivery\Queries;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CurrentDocumentLogo
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private DocumentLogoContent $content,
    ) {}

    public function dataUri(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): ?string {
        $this->authorize($company, $actor, $documentId, $kind);

        return $this->content->dataUri($documentId);
    }

    public function response(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): StreamedResponse {
        $this->authorize($company, $actor, $documentId, $kind);

        return $this->content->response($documentId);
    }

    private function authorize(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): void {
        if (! $this->abilities->allows($actor, $company, $kind->viewAbility())) {
            throw new AuthorizationException;
        }

        Document::query()
            ->whereKey($documentId)
            ->where('kind', $kind)
            ->firstOrFail();
    }
}
