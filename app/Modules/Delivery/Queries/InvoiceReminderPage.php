<?php

namespace App\Modules\Delivery\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Models\DocumentReminderRule;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Delivery\Support\ReminderRuleLimits;
use App\Modules\Documents\Models\Document;

final readonly class InvoiceReminderPage
{
    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, Document $document): array
    {
        $canManage = $this->abilities->allows($actor, $company, CompanyAbility::ManageInvoices);
        $canRetry = $this->abilities->allows($actor, $company, CompanyAbility::ViewOperations);
        $rules = DocumentReminderRule::query()
            ->where('invoice_id', $document->id)->orderBy('display_order')->get();
        $history = ReminderInstance::query()
            ->where('invoice_id', $document->id)
            ->withCount('deliveryAttempts')
            ->orderByDesc('scheduled_at')->orderByDesc('id')->limit(30)->get();

        return [
            'rules' => $rules->map(fn (DocumentReminderRule $rule): array => [
                'id' => $rule->id,
                'relation' => $rule->relation->value,
                'dayOffset' => $rule->day_offset,
                'enabled' => $rule->enabled,
            ])->values()->all(),
            'history' => $history->map(fn (ReminderInstance $instance): array => [
                'id' => $instance->id,
                'relation' => $instance->relation->value,
                'dayOffset' => $instance->day_offset,
                'scheduledAt' => $instance->scheduled_at->toIso8601String(),
                'status' => $instance->status->value,
                'attemptCount' => max($instance->attempts_count, $instance->delivery_attempts_count),
                'failure' => $instance->failure_category === null
                    ? null : $this->failure($instance->failure_category),
                'retryUrl' => $canRetry
                    && $instance->status === ReminderInstanceStatus::Failed
                    ? route('invoices.reminders.retry', [$company, $document, $instance], false)
                    : null,
            ])->values()->all(),
            'saveUrl' => $canManage
                ? route('invoices.reminders.update', [$company, $document], false) : null,
            'limits' => [
                'rules' => ReminderRuleLimits::PER_SCOPE,
                'dayOffset' => ReminderRuleLimits::MAX_DAY_OFFSET,
            ],
        ];
    }

    private function failure(string $category): string
    {
        $key = 'invoice_reminders.failures.'.$category;
        $translation = __($key);

        return $translation === $key
            ? __('invoice_reminders.failures.generic')
            : $translation;
    }
}
