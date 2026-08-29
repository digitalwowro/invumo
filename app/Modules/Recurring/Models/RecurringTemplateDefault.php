<?php

namespace App\Modules\Recurring\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Recurring\Data\RecurringReminderMode;
use App\Modules\Recurring\Data\RecurringValueMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property RecurringValueMode|null $terms_mode
 * @property string|null $terms_and_conditions
 * @property RecurringValueMode|null $notes_mode
 * @property string|null $notes
 * @property RecurringValueMode|null $bank_mode
 * @property string|null $bank_account_id
 * @property string|null $bank_label
 * @property string|null $bank_name
 * @property string|null $bank_account_holder
 * @property string|null $bank_account_number
 * @property string|null $bank_swift_bic
 * @property string|null $bank_currency_code
 * @property array<string, string>|null $bank_local_routing_details
 * @property RecurringReminderMode|null $reminder_mode
 */
#[Fillable([
    'recurring_template_id', 'terms_mode', 'terms_and_conditions', 'notes_mode',
    'notes', 'bank_mode', 'bank_account_id', 'bank_label', 'bank_name',
    'bank_account_holder', 'bank_account_number', 'bank_swift_bic',
    'bank_currency_code', 'bank_local_routing_details', 'reminder_mode',
])]
final class RecurringTemplateDefault extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'terms_mode' => RecurringValueMode::class,
            'notes_mode' => RecurringValueMode::class,
            'bank_mode' => RecurringValueMode::class,
            'bank_local_routing_details' => 'array',
            'reminder_mode' => RecurringReminderMode::class,
        ];
    }
}
