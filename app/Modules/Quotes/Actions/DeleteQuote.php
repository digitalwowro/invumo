<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\DeleteDocumentPublicLinks;
use App\Modules\Delivery\Actions\LockDocumentDeliveryHistory;
use App\Modules\Delivery\Actions\RedactDocumentDeliveries;
use App\Modules\Delivery\Queries\DocumentPublicLinkHistory;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\DocumentNumberEventType;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentNumberEvent;
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
        private RecordAuditEvent $recordAuditEvent,
        private DocumentPublicLinkHistory $publicLinkHistory,
        private DeleteDocumentPublicLinks $deletePublicLinks,
        private LockDocumentDeliveryHistory $deliveryHistory,
        private RedactDocumentDeliveries $redactDeliveries,
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
        $publicLinks = $this->publicLinkHistory->lock($document->id);
        $publicDecisions = QuotePublicDecision::query()
            ->where('quote_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $deliveries = $this->deliveryHistory->all($document->id);

        if ($links->isNotEmpty()) {
            throw QuoteDeletionException::invoiceDependency();
        }

        if ($this->deliveryHistory->hasSubmissionInFlight($deliveries)) {
            throw QuoteDeletionException::deliveryInProgress();
        }

        if (! $data->confirmed) {
            throw QuoteDeletionException::confirmationRequired();
        }

        $highRisk = $quote->lifecycle !== QuoteLifecycle::Draft
            || $publicLinks->isNotEmpty()
            || $publicDecisions->isNotEmpty()
            || $deliveries->isNotEmpty();

        if ($highRisk && ! $data->confirmedHighRisk) {
            throw QuoteDeletionException::highRiskConfirmationRequired();
        }

        $audit = $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.quote.deleted',
            targetType: 'Quote',
            targetId: $document->id,
            before: AuditPayload::fromAllowedFields([
                'lifecycle' => $quote->lifecycle->value,
                'had_customer' => $document->customer_id !== null,
                'had_public_link_history' => $publicLinks->isNotEmpty(),
                'had_customer_decision' => $publicDecisions->isNotEmpty(),
                'had_delivery_history' => $deliveries->isNotEmpty(),
            ], [
                'lifecycle', 'had_customer', 'had_public_link_history',
                'had_customer_decision', 'had_delivery_history',
            ]),
        ));
        DocumentNumberEvent::query()->create([
            'document_id' => $document->id,
            'document_kind' => DocumentKind::Quote,
            'rendered_number' => $document->rendered_number,
            'event_type' => DocumentNumberEventType::Deleted,
            'assignment_source' => $document->assignment_source,
            'occurred_at' => now(),
            'related_audit_event_id' => $audit->id,
        ]);
        $this->redactDeliveries->handle($company->id, $document->id);
        $this->deletePublicLinks->handle($document->id);
        $document->delete();

        return true;
    }
}
