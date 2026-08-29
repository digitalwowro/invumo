<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Documents\Actions\PrepareDocumentLine;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class PersistRecurringTemplateLines
{
    public function __construct(private PrepareDocumentLine $prepareLine) {}

    /**
     * @param  Collection<int, RecurringTemplateLine>  $persisted
     * @param  list<DocumentLineData>  $submitted
     */
    public function handle(
        string $templateId,
        Collection $persisted,
        array $submitted,
        ?int $precision,
    ): int {
        $submittedIds = array_values(array_filter(array_map(
            fn (DocumentLineData $line): ?string => $line->id,
            $submitted,
        )));

        if (count($submittedIds) !== count(array_unique($submittedIds))
            || array_diff($submittedIds, $persisted->modelKeys()) !== []) {
            throw RecurringTemplateException::lineSetInvalid();
        }

        $connection = DB::connection(config('database.tenant_connection'));
        $connection->statement(
            'SET CONSTRAINTS recurring_template_lines_company_template_position_unique DEFERRED',
        );
        $retained = [];
        $complete = 0;

        foreach ($submitted as $index => $data) {
            $line = $data->id === null
                ? new RecurringTemplateLine
                : $persisted->firstWhere('id', $data->id);

            if (! $line instanceof RecurringTemplateLine) {
                throw RecurringTemplateException::lineSetInvalid();
            }

            $prepared = $this->prepareLine->handle($data, $precision);
            $line->fill([
                'recurring_template_id' => $templateId,
                'position' => $index + 1,
                ...$prepared->attributes,
            ])->save();
            $retained[] = $line->id;
            $complete += $prepared->calculation === null ? 0 : 1;
        }

        RecurringTemplateLine::query()
            ->where('recurring_template_id', $templateId)
            ->whereNotIn('id', $retained)
            ->delete();
        $connection->statement(
            'SET CONSTRAINTS recurring_template_lines_company_template_position_unique IMMEDIATE',
        );

        return $complete;
    }
}
