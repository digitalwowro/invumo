<?php

namespace App\Modules\Invoices\Actions;

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
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Exceptions\InvoiceLifecycleException;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Rules\InvoiceIssuability;
use Illuminate\Support\Facades\DB;

final readonly class IssueInvoice
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private InvoiceIssuability $issuability,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $documentId, int $editVersion): Document
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->issue($company, $actor, $documentId, $editVersion),
                3,
            ),
        );
    }

    private function issue(Company $company, User $actor, string $documentId, int $editVersion): Document
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Invoice)
            ->lockForUpdate()
            ->firstOrFail();
        $invoice = Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

        if ($invoice->lifecycle === InvoiceLifecycle::Issued) {
            return $document;
        }

        if ($invoice->lifecycle !== InvoiceLifecycle::Draft) {
            throw InvoiceLifecycleException::unavailable();
        }

        if ($document->edit_version !== $editVersion) {
            throw InvoiceLifecycleException::stale();
        }

        $lines = DocumentLine::query()
            ->where('document_id', $document->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $this->issuability->assert($document, $invoice, $lines);
        $invoice->update(['lifecycle' => InvoiceLifecycle::Issued]);
        $document->update([
            'edit_version' => $document->edit_version + 1,
            'content_version' => $document->content_version + 1,
        ]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.invoice.issued',
            targetType: 'Invoice',
            targetId: $document->id,
            before: AuditPayload::fromAllowedFields([
                'lifecycle' => InvoiceLifecycle::Draft->value,
            ], ['lifecycle']),
            after: AuditPayload::fromAllowedFields([
                'lifecycle' => InvoiceLifecycle::Issued->value,
                'edit_version' => $document->edit_version,
            ], ['lifecycle', 'edit_version']),
        ));

        return $document->refresh();
    }
}
