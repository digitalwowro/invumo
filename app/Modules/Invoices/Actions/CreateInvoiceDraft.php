<?php

namespace App\Modules\Invoices\Actions;

use App\Foundation\Documents\DocumentCalendar;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\CopyCompanyReminderRules;
use App\Modules\Delivery\Actions\LockCompanyReminderRules;
use App\Modules\Documents\Actions\CreateDocumentDraft;
use App\Modules\Documents\Actions\LockDocumentConfiguration;
use App\Modules\Documents\Actions\RecordDocumentCreated;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\InvoiceDraftData;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Support\Facades\DB;

final readonly class CreateInvoiceDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockDocumentConfiguration $lockConfiguration,
        private LockCompanyReminderRules $lockReminderRules,
        private CreateDocumentDraft $createDocument,
        private CopyCompanyReminderRules $copyReminderRules,
        private UpdateInvoiceDraft $updateDraft,
        private RecordDocumentCreated $recordCreated,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $creationKey,
        ?InvoiceDraftData $data = null,
    ): Document {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->create($company, $actor, $creationKey, $data),
                3,
            ),
        );
    }

    private function create(
        Company $company,
        User $actor,
        string $creationKey,
        ?InvoiceDraftData $data,
    ): Document {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);
        $existing = Document::query()
            ->where('kind', DocumentKind::Invoice)
            ->where('client_creation_key', $creationKey)
            ->first();

        if ($existing instanceof Document) {
            return $existing;
        }

        $configuration = $this->lockConfiguration->handle();
        $reminderRules = $this->lockReminderRules->handle();
        $created = $this->createDocument->handle(
            DocumentKind::Invoice,
            $creationKey,
            $configuration,
        );
        Invoice::query()->create([
            'document_id' => $created->document->id,
            'document_kind' => DocumentKind::Invoice,
            'lifecycle' => InvoiceLifecycle::Draft,
            'payment_term_days' => $created->settings->default_payment_term_days,
            'due_date' => $created->settings->default_payment_term_days === null
                ? null
                : DocumentCalendar::addDays(
                    $created->localDate,
                    $created->settings->default_payment_term_days,
                ),
        ]);
        $this->copyReminderRules->handle($created->document->id, $reminderRules);
        $document = $data === null
            ? $created->document
            : $this->updateDraft->update(
                $company,
                $actor,
                $created->document->id,
                $data,
                recordAudit: false,
                advanceVersions: false,
            );
        $this->recordCreated->handle($actor, $document, $creationKey);

        return $document->refresh();
    }
}
