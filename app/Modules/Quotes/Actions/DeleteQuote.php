<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Actions\FinalizeDocumentDeletion;
use App\Modules\Documents\Contracts\DeletesDocumentResources;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Quotes\Data\QuoteDeletionData;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Exceptions\QuoteDeletionException;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use App\Modules\Quotes\Models\QuotePublicDecision;
use Illuminate\Support\Facades\DB;

final readonly class DeleteQuote
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private DeletesDocumentResources $resources,
        private FinalizeDocumentDeletion $finalizeDeletion,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        QuoteDeletionData $data,
    ): void {
        $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): mixed => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): bool => $this->delete($company, $actor, $documentId, $data),
                3,
            ),
        );
    }

    private function delete(
        Company $company,
        User $actor,
        string $documentId,
        QuoteDeletionData $data,
    ): bool {
        $this->authorizer->authorize($actor, $company, CompanyAbility::DeleteQuotes);
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Quote)
            ->lockForUpdate()
            ->firstOrFail();
        $quote = Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $links = QuoteInvoiceLink::query()
            ->where('quote_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $resources = $this->resources->lock($document->id);
        $publicDecisions = QuotePublicDecision::query()
            ->where('quote_id', $document->id)->orderBy('id')->lockForUpdate()->get();

        if ($links->isNotEmpty()) {
            throw QuoteDeletionException::invoiceDependency();
        }

        if ($resources->submissionInFlight) {
            throw QuoteDeletionException::deliveryInProgress();
        }

        if (! $data->confirmed) {
            throw QuoteDeletionException::confirmationRequired();
        }

        $highRisk = $quote->lifecycle !== QuoteLifecycle::Draft
            || $resources->publicLinkCount > 0
            || $publicDecisions->isNotEmpty()
            || $resources->deliveryCount > 0;

        if ($highRisk && ! $data->confirmedHighRisk) {
            throw QuoteDeletionException::highRiskConfirmationRequired();
        }

        $this->finalizeDeletion->handle(
            $company->id,
            $actor,
            $document,
            'company.quote.deleted',
            'Quote',
            AuditPayload::fromAllowedFields([
                'lifecycle' => $quote->lifecycle->value,
                'had_customer' => $document->customer_id !== null,
                'had_public_link_history' => $resources->publicLinkCount > 0,
                'had_customer_decision' => $publicDecisions->isNotEmpty(),
                'had_delivery_history' => $resources->deliveryCount > 0,
            ], [
                'lifecycle', 'had_customer', 'had_public_link_history',
                'had_customer_decision', 'had_delivery_history',
            ]),
        );

        return true;
    }
}
