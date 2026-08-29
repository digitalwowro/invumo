<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use Illuminate\Database\Eloquent\Collection;

final class LockDocumentDeliveryHistory
{
    /** @return Collection<int, EmailDelivery> */
    public function all(string $documentId): Collection
    {
        return EmailDelivery::query()
            ->where('document_id', $documentId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function hasPending(string $documentId): bool
    {
        return EmailDelivery::query()
            ->where('document_id', $documentId)
            ->whereIn('dispatch_state', [
                EmailDeliveryState::Queued,
                EmailDeliveryState::Retrying,
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->first() instanceof EmailDelivery;
    }

    /** @param Collection<int, EmailDelivery> $deliveries */
    public function hasPendingIn(Collection $deliveries): bool
    {
        return $deliveries->contains(fn (EmailDelivery $delivery): bool => in_array(
            $delivery->dispatch_state,
            [EmailDeliveryState::Queued, EmailDeliveryState::Retrying],
            true,
        ));
    }

    public function hasPendingDirect(string $documentId): bool
    {
        return $this->hasPendingDirectIn($this->all($documentId));
    }

    /** @param Collection<int, EmailDelivery> $deliveries */
    public function hasPendingDirectIn(Collection $deliveries): bool
    {
        return $deliveries->contains(fn (EmailDelivery $delivery): bool => $delivery->event_type !== EmailTemplateEvent::PaymentReminder
            && in_array($delivery->dispatch_state, [
                EmailDeliveryState::Queued,
                EmailDeliveryState::Retrying,
            ], true));
    }

    /** @param Collection<int, EmailDelivery> $deliveries */
    public function countSubmissionsInFlight(Collection $deliveries): int
    {
        if ($deliveries->isEmpty()) {
            return 0;
        }

        return count(EmailDeliveryAttempt::query()
            ->whereIn('delivery_id', $deliveries->pluck('id'))
            ->where('state', 'PENDING')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->all());
    }
}
