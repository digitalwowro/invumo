<?php

namespace App\Modules\Platform\Queries;

use App\Modules\Platform\Data\PlatformCursorPage;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final readonly class PlatformErasureHistory
{
    /** @return array<string, mixed> */
    public function page(): array
    {
        $cleanup = DB::connection(config('database.tenant_connection'))
            ->table('company_erasure_files')
            ->selectRaw(<<<'SQL'
                data_erasure_event_id,
                COUNT(*) AS file_count,
                COUNT(*) FILTER (WHERE completed_at IS NULL) AS pending_file_count,
                COUNT(*) FILTER (
                    WHERE completed_at IS NULL AND last_failure_category IS NOT NULL
                ) AS failed_file_count
                SQL)
            ->groupBy('data_erasure_event_id');

        $page = DB::connection(config('database.tenant_connection'))
            ->table('data_erasure_events as event')
            ->leftJoin('users as actor', 'actor.id', '=', 'event.actor_user_id')
            ->leftJoinSub($cleanup, 'cleanup', 'cleanup.data_erasure_event_id', '=', 'event.id')
            ->select([
                'event.id',
                'event.action',
                'event.subject_type',
                'event.subject_id',
                'event.occurred_at',
                'actor.name as actor_name',
                DB::raw('COALESCE(cleanup.file_count, 0) AS file_count'),
                DB::raw('COALESCE(cleanup.pending_file_count, 0) AS pending_file_count'),
                DB::raw('COALESCE(cleanup.failed_file_count, 0) AS failed_file_count'),
            ])
            ->orderByDesc('event.occurred_at')
            ->orderByDesc('event.id')
            ->cursorPaginate(25, ['*'], 'erasure_cursor')
            ->withQueryString();

        return PlatformCursorPage::from(
            $page,
            fn (object $event): array => $this->row($event),
        )->toArray();
    }

    /** @return array<string, mixed> */
    private function row(object $event): array
    {
        $values = get_object_vars($event);

        foreach (['id', 'action', 'subject_type', 'subject_id', 'occurred_at'] as $field) {
            if (! is_string($values[$field] ?? null)) {
                throw new UnexpectedValueException("Data erasure field [{$field}] is invalid.");
            }
        }

        if (! is_string($values['actor_name'] ?? null) && ($values['actor_name'] ?? null) !== null) {
            throw new UnexpectedValueException('Data erasure actor is invalid.');
        }

        return [
            'id' => $values['id'],
            'actorName' => $values['actor_name'],
            'action' => $values['action'],
            'subjectType' => $values['subject_type'],
            'subjectId' => $values['subject_id'],
            'occurredAt' => $values['occurred_at'],
            'fileCount' => $this->count($values['file_count'] ?? null),
            'pendingFileCount' => $this->count($values['pending_file_count'] ?? null),
            'failedFileCount' => $this->count($values['failed_file_count'] ?? null),
        ];
    }

    private function count(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException('Data erasure cleanup count is invalid.');
    }
}
