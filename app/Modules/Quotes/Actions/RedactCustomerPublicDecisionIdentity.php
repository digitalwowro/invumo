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
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Quotes\Data\CustomerDecisionIdentityErasureData;
use App\Modules\Quotes\Exceptions\CustomerDecisionIdentityErasureException;
use App\Modules\Quotes\Models\QuotePublicDecision;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RedactCustomerPublicDecisionIdentity
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $customerId,
        CustomerDecisionIdentityErasureData $data,
    ): int {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): int => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): int => $this->redact(
                    $company,
                    $actor,
                    $customerId,
                    $data,
                ), 3),
        );
    }

    private function redact(
        Company $company,
        User $actor,
        string $customerId,
        CustomerDecisionIdentityErasureData $data,
    ): int {
        $this->authorizer->authorize($actor, $company, CompanyAbility::DeleteCustomers);

        if (! $data->confirmed) {
            throw CustomerDecisionIdentityErasureException::confirmationRequired();
        }

        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
        $currentDocumentIds = Document::query()
            ->where('customer_id', $customer->id)
            ->where('kind', DocumentKind::Quote)
            ->pluck('id');
        $historicalDocumentIds = QuotePublicDecision::query()
            ->where('customer_id', $customer->id)
            ->pluck('quote_id');
        $sourceDocumentIds = $currentDocumentIds
            ->merge($historicalDocumentIds)
            ->unique()
            ->sort()
            ->values();
        $lockedDocumentIds = Document::query()
            ->whereIn('id', $sourceDocumentIds)
            ->where('kind', DocumentKind::Quote)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');
        $documentIds = [];

        foreach ($lockedDocumentIds as $documentId) {
            if (! is_string($documentId)) {
                throw new LogicException('A locked Quote identifier is invalid.');
            }

            $documentIds[] = $documentId;
        }

        $decisions = $this->lockDecisions($customer->id, $documentIds);
        $redactedAt = now();

        foreach ($decisions as $decision) {
            $decision->update([
                'customer_name' => null,
                'customer_email' => null,
                'identity_redacted_at' => $redactedAt,
            ]);
        }

        if ($decisions->isNotEmpty()) {
            $this->recordAuditEvent->handle(new AuditEventData(
                actorType: AuditActorType::User,
                actorUserId: $actor->id,
                action: 'company.customer.public_decision_identity_redacted',
                targetType: 'Customer',
                targetId: $customer->id,
                after: AuditPayload::fromAllowedFields([
                    'identity_redacted' => true,
                    'decision_count' => $decisions->count(),
                ], ['identity_redacted', 'decision_count']),
            ));
        }

        return $decisions->count();
    }

    /**
     * @param  list<string>  $documentIds
     * @return Collection<int, QuotePublicDecision>
     */
    private function lockDecisions(string $customerId, array $documentIds): Collection
    {
        if ($documentIds === []) {
            return new Collection;
        }

        return QuotePublicDecision::query()
            ->where('customer_id', $customerId)
            ->whereIn('quote_id', $documentIds)
            ->whereNull('identity_redacted_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
