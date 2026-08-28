<?php

namespace App\Modules\Delivery\Queries;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Delivery\Support\PublicDocumentUrl;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class DocumentPublicLinkState
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private PublicDocumentUrl $publicUrl,
    ) {}

    /** @return array<string, mixed> */
    public function for(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): array {
        if (! $this->abilities->allows($actor, $company, $kind->manageAbility())) {
            throw new AuthorizationException;
        }

        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', $kind)
            ->firstOrFail();
        $delivery = DocumentDeliverySetting::query()
            ->where('document_id', $document->id)
            ->firstOrFail();
        $current = PublicDocumentLink::query()
            ->where('document_id', $document->id)
            ->whereNull('revoked_at')
            ->first();
        $active = $delivery->public_access_enabled
            && $current instanceof PublicDocumentLink
            && $current->expires_at->isFuture();
        $timezone = CompanySetting::query()->value('timezone') ?? 'UTC';

        return [
            'status' => match (true) {
                ! $delivery->public_access_enabled => 'DISABLED',
                $current === null => 'NOT_CREATED',
                $current->expires_at->isPast() => 'EXPIRED',
                default => 'ACTIVE',
            },
            'url' => $active ? $this->publicUrl->for($kind, $current) : null,
            'expiresAt' => $current?->expires_at->toIso8601String(),
            'locale' => app()->getLocale(),
            'timezone' => $timezone,
            'createUrl' => $this->actionUrl($company, $document, $kind, 'store'),
            'revokeUrl' => $delivery->public_access_enabled && $current !== null
                ? $this->actionUrl($company, $document, $kind, 'destroy')
                : null,
            'regenerateUrl' => $delivery->public_access_enabled && $current !== null
                ? $this->actionUrl($company, $document, $kind, 'regenerate')
                : null,
        ];
    }

    private function actionUrl(
        Company $company,
        Document $document,
        DocumentKind $kind,
        string $action,
    ): string {
        $prefix = $kind === DocumentKind::Quote ? 'quotes' : 'invoices';

        return route("{$prefix}.public-link.{$action}", [$company, $document], false);
    }
}
