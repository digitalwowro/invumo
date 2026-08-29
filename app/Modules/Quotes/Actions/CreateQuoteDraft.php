<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Documents\DocumentCalendar;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Actions\CreateDocumentDraft;
use App\Modules\Documents\Actions\LockDocumentConfiguration;
use App\Modules\Documents\Actions\RecordDocumentCreated;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Support\Facades\DB;

final readonly class CreateQuoteDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockDocumentConfiguration $lockConfiguration,
        private CreateDocumentDraft $createDocument,
        private RecordDocumentCreated $recordCreated,
    ) {}

    public function handle(Company $company, User $actor, string $creationKey): Document
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->create($company, $actor, $creationKey),
                3,
            ),
        );
    }

    private function create(Company $company, User $actor, string $creationKey): Document
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageQuotes);
        $existing = Document::query()
            ->where('kind', DocumentKind::Quote)
            ->where('client_creation_key', $creationKey)
            ->first();

        if ($existing instanceof Document) {
            return $existing;
        }

        $created = $this->createDocument->handle(
            DocumentKind::Quote,
            $creationKey,
            $this->lockConfiguration->handle(),
        );
        Quote::query()->create([
            'document_id' => $created->document->id,
            'document_kind' => DocumentKind::Quote,
            'lifecycle' => QuoteLifecycle::Draft,
            'validity_days' => $created->settings->default_quote_validity_days,
            'valid_until' => DocumentCalendar::addDays(
                $created->localDate,
                $created->settings->default_quote_validity_days,
            ),
            'invoice_payment_term_days' => $created->settings->default_payment_term_days,
        ]);
        $this->recordCreated->handle($actor, $created->document, $creationKey);

        return $created->document->refresh();
    }
}
