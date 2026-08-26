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
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Quotes\Data\QuoteLifecycleCorrectionData;
use App\Modules\Quotes\Exceptions\QuoteLifecycleException;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Support\Facades\DB;

final readonly class CorrectQuoteLifecycle
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        QuoteLifecycleCorrectionData $data,
    ): Document {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->correct($company, $actor, $documentId, $data),
                3,
            ),
        );
    }

    private function correct(
        Company $company,
        User $actor,
        string $documentId,
        QuoteLifecycleCorrectionData $data,
    ): Document {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageQuotes);

        if (! $data->confirmed) {
            throw QuoteLifecycleException::confirmationRequired();
        }

        if ($data->reason === '' || mb_strlen($data->reason) > 500) {
            throw QuoteLifecycleException::reasonInvalid();
        }

        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Quote)
            ->lockForUpdate()
            ->firstOrFail();
        $quote = Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

        if ($quote->lifecycle === $data->lifecycle) {
            return $document;
        }

        $previous = $quote->lifecycle;
        $quote->update(['lifecycle' => $data->lifecycle]);
        $document->update([
            'edit_version' => $document->edit_version + 1,
            'content_version' => $document->content_version + 1,
        ]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.quote.lifecycle_corrected',
            targetType: 'Quote',
            targetId: $document->id,
            reason: $data->reason,
            before: AuditPayload::fromAllowedFields([
                'lifecycle' => $previous->value,
            ], ['lifecycle']),
            after: AuditPayload::fromAllowedFields([
                'lifecycle' => $data->lifecycle->value,
                'edit_version' => $document->edit_version,
            ], ['lifecycle', 'edit_version']),
        ));

        return $document->refresh();
    }
}
