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
use App\Modules\Quotes\Data\QuoteDeletionState;
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
        $state = new QuoteDeletionState(
            $quote->lifecycle,
            $links->count(),
            $publicDecisions->count(),
            $resources->publicLinkCount,
            $resources->deliveryCount,
            $resources->submissionInFlightCount,
        );

        if (! hash_equals($state->version(), $data->stateVersion)) {
            throw QuoteDeletionException::stale();
        }

        if ($state->invoiceCount > 0) {
            throw QuoteDeletionException::invoiceDependency();
        }

        if ($resources->submissionInFlightCount > 0) {
            throw QuoteDeletionException::deliveryInProgress();
        }

        if (! $data->confirmed) {
            throw QuoteDeletionException::confirmationRequired();
        }

        $highRisk = $state->highRisk();

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
