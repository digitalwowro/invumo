<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\ResolvedPublicDocument;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Delivery\Queries\ResolvePublicDocument;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Quotes\Data\PublicQuoteDecision;
use App\Modules\Quotes\Data\PublicQuoteDecisionData;
use App\Modules\Quotes\Data\PublicQuoteDecisionResult;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Exceptions\PublicQuoteDecisionException;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuotePublicDecision;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class DecidePublicQuote
{
    public function __construct(
        private TenantContext $tenantContext,
        private ResolvePublicDocument $resolvePublicDocument,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        string $token,
        PublicQuoteDecisionData $data,
    ): ?PublicQuoteDecisionResult {
        try {
            try {
                $decision = $this->connection()->transaction(
                    fn (): ?PublicQuoteDecision => $this->resolvePublicDocument->withinTransaction(
                        $token,
                        DocumentKind::Quote,
                        fn (ResolvedPublicDocument $resolved): PublicQuoteDecision => $this->decide(
                            $resolved,
                            $data,
                        ),
                    ),
                    3,
                );
            } catch (PublicQuoteDecisionException $exception) {
                return new PublicQuoteDecisionResult(null, $exception->reason());
            }

            return $decision === null
                ? null
                : new PublicQuoteDecisionResult($decision, null);
        } finally {
            $this->tenantContext->assertClear();
        }
    }

    private function decide(
        ResolvedPublicDocument $resolved,
        PublicQuoteDecisionData $data,
    ): PublicQuoteDecision {
        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $document = Document::query()
            ->whereKey($resolved->document->id)
            ->where('kind', DocumentKind::Quote)
            ->lockForUpdate()
            ->firstOrFail();
        $quote = Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $delivery = DocumentDeliverySetting::query()
            ->where('document_id', $document->id)
            ->lockForUpdate()
            ->firstOrFail();
        $links = PublicDocumentLink::query()
            ->where('document_id', $document->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $decisions = QuotePublicDecision::query()
            ->where('quote_id', $document->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $this->assertLink($resolved, $delivery, $links);
        $replay = $decisions->firstWhere('idempotency_key', $data->idempotencyKey);

        if ($replay instanceof QuotePublicDecision) {
            if ($replay->decision !== $data->decision
                || ! hash_equals($replay->customer_name, $data->customerName)
                || ! hash_equals($replay->customer_email, $data->customerEmail)) {
                throw PublicQuoteDecisionException::idempotencyConflict();
            }

            return $replay->decision;
        }

        if ($quote->lifecycle === $data->decision->lifecycle()
            && $decisions->contains('decision', $data->decision)) {
            return $data->decision;
        }

        if (in_array($quote->lifecycle, [QuoteLifecycle::Accepted, QuoteLifecycle::Rejected], true)) {
            throw PublicQuoteDecisionException::oppositeDecision();
        }

        if ($quote->lifecycle !== QuoteLifecycle::Sent) {
            throw PublicQuoteDecisionException::unavailable();
        }

        $this->assertCommercialValidity($quote, $settings);

        QuotePublicDecision::query()->create([
            'quote_id' => $document->id,
            'decision' => $data->decision,
            'customer_name' => $data->customerName,
            'customer_email' => $data->customerEmail,
            'decided_at' => now(),
            'idempotency_key' => $data->idempotencyKey,
        ]);
        $quote->update(['lifecycle' => $data->decision->lifecycle()]);
        $document->update([
            'edit_version' => $document->edit_version + 1,
            'content_version' => $document->content_version + 1,
        ]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::PublicCustomer,
            action: 'company.quote.public_decided',
            targetType: 'Quote',
            targetId: $document->id,
            idempotencyReference: $data->idempotencyKey,
            before: AuditPayload::fromAllowedFields([
                'lifecycle' => QuoteLifecycle::Sent->value,
            ], ['lifecycle']),
            after: AuditPayload::fromAllowedFields([
                'lifecycle' => $data->decision->value,
                'edit_version' => $document->edit_version,
            ], ['lifecycle', 'edit_version']),
        ));

        return $data->decision;
    }

    /** @param Collection<int, PublicDocumentLink> $links */
    private function assertLink(
        ResolvedPublicDocument $resolved,
        DocumentDeliverySetting $delivery,
        Collection $links,
    ): void {
        $link = $links->firstWhere('id', $resolved->link->id);

        if (! $link instanceof PublicDocumentLink
            || $link->revoked_at !== null
            || ! $link->expires_at->isFuture()
            || ! $delivery->public_access_enabled) {
            throw PublicQuoteDecisionException::unavailable();
        }
    }

    private function assertCommercialValidity(Quote $quote, CompanySetting $settings): void
    {
        $localDate = Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay();

        if ($quote->valid_until !== null && $localDate->greaterThan($quote->valid_until)) {
            throw PublicQuoteDecisionException::unavailable();
        }
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(config('database.tenant_connection'));
    }
}
