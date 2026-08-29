<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Data\CompanyDeliveryErasureState;
use App\Modules\Delivery\Data\DocumentArtifactFile;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Models\DocumentArtifact;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\JobDispatch;

final readonly class PrepareCompanyDeliveryErasure
{
    public function handle(): CompanyDeliveryErasureState
    {
        EmailDelivery::query()->orderBy('id')->lockForUpdate()->get(['id']);
        $attempts = EmailDeliveryAttempt::query()
            ->orderBy('id')->lockForUpdate()->get(['id', 'state']);
        $artifacts = DocumentArtifact::query()
            ->orderBy('id')->lockForUpdate()->get(['storage_disk', 'storage_key']);
        $dispatches = JobDispatch::query()->orderBy('id')->lockForUpdate()->get();

        foreach ($dispatches as $dispatch) {
            if (! in_array($dispatch->status, [
                JobDispatchStatus::Pending,
                JobDispatchStatus::Queued,
            ], true)) {
                continue;
            }

            $dispatch->update([
                'status' => JobDispatchStatus::Cancelled,
                'claim_token' => null,
                'claimed_at' => null,
                'completed_at' => now(),
            ]);
        }

        return new CompanyDeliveryErasureState(
            pendingSubmissionCount: $attempts
                ->where('state', EmailDeliveryAttemptState::Pending)->count(),
            files: array_values($artifacts->map(
                fn (DocumentArtifact $artifact): DocumentArtifactFile => new DocumentArtifactFile(
                    $artifact->storage_disk,
                    $artifact->storage_key,
                ),
            )->all()),
        );
    }
}
