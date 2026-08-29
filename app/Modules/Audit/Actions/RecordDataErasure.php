<?php

namespace App\Modules\Audit\Actions;

use App\Modules\Audit\Data\DataErasureAction;
use App\Modules\Audit\Models\DataErasureEvent;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RecordDataErasure
{
    public function handle(
        DataErasureAction $action,
        string $subjectId,
        ?string $actorUserId,
    ): DataErasureEvent {
        if (DB::connection(config('database.tenant_connection'))->transactionLevel() === 0) {
            throw new LogicException('Data erasure proof must be recorded inside the owning transaction.');
        }

        return DataErasureEvent::query()->create([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'subject_type' => $action->subjectType(),
            'subject_id' => $subjectId,
            'occurred_at' => now(),
        ]);
    }
}
