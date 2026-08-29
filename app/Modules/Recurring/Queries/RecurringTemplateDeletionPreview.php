<?php

namespace App\Modules\Recurring\Queries;

use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Models\RecurringOccurrence;
use RuntimeException;

final class RecurringTemplateDeletionPreview
{
    /**
     * @param  array<string, RecurringTemplateState>  $templates
     * @return array<string, array{highRisk: bool, guard: array{blocked: bool, description: string|null}}>
     */
    public function forTemplates(array $templates): array
    {
        $counts = RecurringOccurrence::query()
            ->whereIn('recurring_template_id', array_keys($templates))
            ->selectRaw('recurring_template_id, count(*) AS aggregate')
            ->groupBy('recurring_template_id')
            ->pluck('aggregate', 'recurring_template_id');
        $result = [];

        foreach ($templates as $id => $state) {
            $occurrences = (int) ($counts[$id] ?? 0);
            $result[$id] = [
                'highRisk' => $state !== RecurringTemplateState::Draft,
                'guard' => [
                    'blocked' => $occurrences > 0,
                    'description' => $occurrences > 0
                        ? $this->description([
                            'occurrences' => $occurrences,
                        ])
                        : null,
                ],
            ];
        }

        return $result;
    }

    /** @param array{occurrences: int} $replace */
    private function description(array $replace): string
    {
        $translation = __('recurring_ui.deletion.dependency_description', $replace);

        if (! is_string($translation)) {
            throw new RuntimeException('The recurring deletion dependency text must be a string.');
        }

        return $translation;
    }
}
