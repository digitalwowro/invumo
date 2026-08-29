<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Delivery\Models\DocumentReminderRule;
use Illuminate\Support\Collection;

final readonly class CopyCompanyReminderRules
{
    /** @param Collection<int, CompanyReminderRule> $rules */
    public function handle(string $invoiceId, Collection $rules): void
    {
        foreach ($rules->sortBy('display_order')->values() as $rule) {
            DocumentReminderRule::query()->create([
                'invoice_id' => $invoiceId,
                'source_rule_id' => $rule->id,
                'relation' => $rule->relation,
                'day_offset' => $rule->day_offset,
                'enabled' => $rule->enabled,
                'display_order' => $rule->display_order,
            ]);
        }
    }
}
