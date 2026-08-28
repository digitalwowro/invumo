<?php

namespace App\Modules\Delivery\Actions;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Contracts\RendersDocumentPdf;
use App\Modules\Delivery\Data\DocumentArtifactLimits;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\RenderedDocumentHtml;
use App\Modules\Delivery\Models\DocumentArtifact;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Queries\CurrentDocumentRepresentation;
use App\Modules\Delivery\Queries\DocumentPdfContent;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;

final readonly class PrepareDocumentDeliveryArtifact
{
    public function __construct(
        private TenantContext $tenantContext,
        private FilesystemManager $filesystems,
        private CurrentDocumentRepresentation $representation,
        private DocumentPdfContent $pdfContent,
        private RendersDocumentPdf $renderer,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        string $companyId,
        string $deliveryId,
        string $auditReference,
        TenantJobExecution $execution,
    ): bool {
        $payload = $this->tenantContext->runAsSystem(
            $companyId,
            fn (): RenderedDocumentHtml|bool|null => $this->payload(
                $companyId,
                $deliveryId,
                $execution,
            ),
        );

        if (is_bool($payload) || $payload === null) {
            return $payload ?? false;
        }

        $bytes = $this->renderer->render($payload->html);

        if ($bytes === '' || strlen($bytes) > DocumentArtifactLimits::MAX_BYTES) {
            $this->tenantContext->runAsSystem(
                $companyId,
                fn (): bool => $this->fail($deliveryId, $auditReference),
            );

            return false;
        }

        $disk = (string) config('invumo.document_artifacts.disk');
        $key = $deliveryId.'/'.Str::uuid7().'.pdf';
        $persisted = false;

        try {
            $this->filesystems->disk($disk)->put($key, $bytes);
            $persisted = $this->tenantContext->runAsSystem(
                $companyId,
                fn (): bool => $this->persist($deliveryId, $payload->fileName, $disk, $key, $bytes),
            );

            return $persisted;
        } finally {
            if (! $persisted) {
                $this->filesystems->disk($disk)->delete($key);
            }
        }
    }

    private function payload(
        string $companyId,
        string $deliveryId,
        TenantJobExecution $execution,
    ): RenderedDocumentHtml|bool|null {
        $unlocked = EmailDelivery::query()->whereKey($deliveryId)->first();

        if (! $unlocked instanceof EmailDelivery || $unlocked->document_id === null) {
            $execution->skip('delivery_unavailable');

            return null;
        }

        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $document = $this->lockDocument($unlocked->document_id);
        $delivery = EmailDelivery::query()->whereKey($deliveryId)->lockForUpdate()->firstOrFail();

        if (! $this->active($delivery)) {
            $execution->skip('delivery_unavailable');

            return null;
        }

        if ($delivery->attachment_mode !== EmailAttachmentMode::AttachPdf
            || $delivery->artifact_id !== null) {
            return true;
        }

        if ($delivery->document_edit_version !== $document->edit_version) {
            $execution->skip('document_changed');

            return null;
        }

        $company = Company::query()->findOrFail($companyId);
        $outward = $document->kind === DocumentKind::Quote
            ? $this->representation->publicQuote($company, $document)
            : $this->representation->publicInvoice($company, $document);

        return $this->pdfContent->prepare($document->id, $outward);
    }

    private function persist(
        string $deliveryId,
        string $fileName,
        string $disk,
        string $key,
        string $bytes,
    ): bool {
        $unlocked = EmailDelivery::query()->whereKey($deliveryId)->first();

        if (! $unlocked instanceof EmailDelivery || $unlocked->document_id === null) {
            return false;
        }

        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $document = $this->lockDocument($unlocked->document_id);
        $delivery = EmailDelivery::query()->whereKey($deliveryId)->lockForUpdate()->firstOrFail();

        if ($delivery->artifact_id !== null) {
            return false;
        }

        if (! $this->active($delivery)
            || $delivery->document_edit_version !== $document->edit_version) {
            return false;
        }

        $artifact = DocumentArtifact::query()->create([
            'document_id' => $document->id,
            'artifact_type' => 'PDF_ATTACHMENT',
            'document_edit_version' => $document->edit_version,
            'storage_disk' => $disk,
            'storage_key' => $key,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'generated_at' => now(),
        ]);
        $delivery->update(['artifact_id' => $artifact->id]);

        return true;
    }

    private function fail(string $deliveryId, string $auditReference): bool
    {
        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $delivery = EmailDelivery::query()->whereKey($deliveryId)->lockForUpdate()->first();

        if (! $delivery instanceof EmailDelivery || ! $this->active($delivery)) {
            return false;
        }

        $delivery->update([
            'dispatch_state' => EmailDeliveryState::Rejected,
            'failure_category' => 'artifact_too_large',
            'failure_summary' => 'The generated PDF exceeded the provider attachment limit.',
            'failed_at' => now(),
        ]);
        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::System,
            action: 'company.document.delivery.completed',
            targetType: $delivery->document_kind === DocumentKind::Quote ? 'Quote' : 'Invoice',
            targetId: (string) $delivery->document_id,
            idempotencyReference: $auditReference,
            after: AuditPayload::fromAllowedFields([
                'delivery_id' => $delivery->id,
                'dispatch_state' => EmailDeliveryState::Rejected->value,
                'attempt_count' => 0,
            ], ['delivery_id', 'dispatch_state', 'attempt_count']),
        ));

        return false;
    }

    private function lockDocument(string $documentId): Document
    {
        $document = Document::query()->whereKey($documentId)->lockForUpdate()->firstOrFail();
        match ($document->kind) {
            DocumentKind::Quote => Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
            DocumentKind::Invoice => Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
        };

        return $document;
    }

    private function active(EmailDelivery $delivery): bool
    {
        return in_array(
            $delivery->dispatch_state,
            [EmailDeliveryState::Queued, EmailDeliveryState::Retrying],
            true,
        ) && $delivery->redacted_at === null;
    }
}
