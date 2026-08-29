<?php

namespace App\Modules\Delivery\Queries;

use App\Modules\Delivery\Data\DocumentDeletionExposure as Exposure;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\PublicDocumentLink;

final class DocumentDeletionExposure
{
    /**
     * @param  list<string>  $documentIds
     * @return array<string, Exposure>
     */
    public function forDocuments(array $documentIds): array
    {
        $ids = array_values(array_unique($documentIds));

        if ($ids === []) {
            return [];
        }

        $links = PublicDocumentLink::query()
            ->whereIn('document_id', $ids)
            ->selectRaw('document_id, count(*) AS aggregate')
            ->groupBy('document_id')->pluck('aggregate', 'document_id')
            ->map(fn (mixed $count): int => (int) $count);
        $deliveries = EmailDelivery::query()
            ->whereIn('document_id', $ids)
            ->selectRaw('document_id, count(*) AS aggregate')
            ->groupBy('document_id')->pluck('aggregate', 'document_id')
            ->map(fn (mixed $count): int => (int) $count);
        $submissions = EmailDeliveryAttempt::query()
            ->join('email_deliveries', function ($join): void {
                $join->on('email_deliveries.company_id', '=', 'email_delivery_attempts.company_id')
                    ->on('email_deliveries.id', '=', 'email_delivery_attempts.delivery_id');
            })
            ->whereIn('email_deliveries.document_id', $ids)
            ->where('email_delivery_attempts.state', EmailDeliveryAttemptState::Pending)
            ->selectRaw('email_deliveries.document_id, count(*) AS aggregate')
            ->groupBy('email_deliveries.document_id')
            ->pluck('aggregate', 'email_deliveries.document_id')
            ->map(fn (mixed $count): int => (int) $count);
        $result = [];

        foreach ($ids as $id) {
            $result[$id] = new Exposure(
                (int) ($links[$id] ?? 0),
                (int) ($deliveries[$id] ?? 0),
                (int) ($submissions[$id] ?? 0),
            );
        }

        return $result;
    }
}
