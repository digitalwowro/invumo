<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Data\DocumentArtifactFile;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Jobs\DeleteDocumentArtifactFiles;
use App\Modules\Delivery\Models\DocumentArtifact;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Models\EmailProviderEvent;
use LogicException;

final class RedactDocumentDeliveries
{
    public function handle(string $companyId, string $documentId): int
    {
        $deliveries = EmailDelivery::query()
            ->where('document_id', $documentId)->orderBy('id')->lockForUpdate()->get();

        if ($deliveries->isEmpty()) {
            return 0;
        }

        $ids = $deliveries->pluck('id');
        $attempts = EmailDeliveryAttempt::query()
            ->whereIn('delivery_id', $ids)->orderBy('id')->lockForUpdate()->get();
        $recipients = EmailDeliveryRecipient::query()
            ->whereIn('delivery_id', $ids)->orderBy('id')->lockForUpdate()->get();
        $providerEvents = EmailProviderEvent::query()
            ->whereIn('delivery_id', $ids)->orderBy('id')->lockForUpdate()->get();
        $artifacts = DocumentArtifact::query()
            ->where('document_id', $documentId)->orderBy('id')->lockForUpdate()->get();

        if ($attempts->contains('state', 'PENDING')) {
            throw new LogicException('A document delivery cannot be redacted during provider submission.');
        }

        foreach ($deliveries as $delivery) {
            if (in_array($delivery->dispatch_state, [
                EmailDeliveryState::Queued,
                EmailDeliveryState::Retrying,
            ], true)) {
                $delivery->update([
                    'dispatch_state' => EmailDeliveryState::Rejected,
                    'failure_category' => 'document_deleted_before_submission',
                    'failure_summary' => 'The document was deleted before provider submission.',
                    'failed_at' => now(),
                ]);
            }

            $delivery->update([
                'document_id' => null,
                'public_document_link_id' => null,
                'subject' => null,
                'body' => null,
                'button_label' => null,
                'signature' => null,
                'button_url' => null,
                'attachment_mode' => null,
                'artifact_id' => null,
                'provider_message_identifier' => null,
                'failure_summary' => null,
                'initiated_by_user_id' => null,
                'redacted_at' => now(),
            ]);
        }

        $recipients->each->delete();

        foreach ($providerEvents as $providerEvent) {
            $providerEvent->update([
                'provider_name' => null,
                'provider_event_identifier' => null,
                'redacted_at' => now(),
            ]);
        }

        foreach ($attempts as $attempt) {
            $attempt->update([
                'client_reference' => null,
                'provider_message_identifier' => null,
                'failure_summary' => null,
                'redacted_at' => now(),
            ]);
        }

        $files = array_values($artifacts->map(fn (DocumentArtifact $artifact): DocumentArtifactFile => new DocumentArtifactFile(
            $artifact->storage_disk,
            $artifact->storage_key,
        ))->all());
        $artifacts->each->delete();

        if ($files !== []) {
            DeleteDocumentArtifactFiles::dispatch($companyId, $documentId, $files)
                ->onConnection('database')->onQueue('default');
        }

        return $deliveries->count();
    }
}
