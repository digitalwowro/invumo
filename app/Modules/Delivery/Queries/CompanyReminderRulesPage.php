<?php

namespace App\Modules\Delivery\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Data\ReminderRelation;
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Delivery\Support\ReminderRuleLimits;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyReminderRulesPage
{
    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ManageCompanySettings)) {
            throw new AuthorizationException;
        }

        $settings = CompanySetting::query()->firstOrFail();
        $failures = ReminderInstance::query()
            ->with('invoiceDocument')
            ->withCount('deliveryAttempts')
            ->where('status', ReminderInstanceStatus::Failed)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [
            'rules' => CompanyReminderRule::query()->orderBy('display_order')->get()
                ->map(fn (CompanyReminderRule $rule): array => [
                    'id' => $rule->id,
                    'relation' => $rule->relation->value,
                    'dayOffset' => $rule->day_offset,
                    'enabled' => $rule->enabled,
                ])->values()->all(),
            'relationOptions' => array_map(fn (ReminderRelation $relation): array => [
                'value' => $relation->value,
                'label' => __('companies_ui.settings.reminders.relations.'.$relation->value),
            ], ReminderRelation::cases()),
            'limits' => [
                'rules' => ReminderRuleLimits::PER_SCOPE,
                'dayOffset' => ReminderRuleLimits::MAX_DAY_OFFSET,
            ],
            'saveUrl' => route('company-reminder-rules.update', $company, false),
            'locale' => $actor->language_code,
            'timezone' => $settings->timezone ?? 'UTC',
            'failures' => $failures->map(fn (ReminderInstance $instance): array => [
                'id' => $instance->id,
                'invoiceNumber' => $instance->invoiceDocument->rendered_number,
                'scheduledAt' => $instance->scheduled_at->toIso8601String(),
                'failure' => $this->failure($instance->failure_category),
                'attemptCount' => max($instance->attempts_count, $instance->delivery_attempts_count),
                'invoiceUrl' => route('invoices.edit', [$company, $instance->invoice_id], false),
                'retryUrl' => route(
                    'invoices.reminders.retry',
                    [$company, $instance->invoice_id, $instance->id],
                    false,
                ),
            ])->values()->all(),
        ];
    }

    private function failure(?string $category): string
    {
        $key = 'invoice_reminders.failures.'.($category ?? 'generic');
        $translation = __($key);

        return $translation === $key
            ? __('invoice_reminders.failures.generic')
            : $translation;
    }
}
