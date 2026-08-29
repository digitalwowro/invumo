<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Data\ReminderRelation;
use App\Modules\Delivery\Models\DocumentReminderRule;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Delivery\Support\ReminderScheduleCalculator;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

final readonly class InvoiceReminderSchedule
{
    public function __construct(
        private ReminderScheduleCalculator $calculator,
        private MaterializeReminderInstance $materialize,
    ) {}

    public function materialize(
        Document $document,
        Invoice $invoice,
        CompanySetting $settings,
    ): void {
        $this->reconcile($document, $invoice, $settings, false);
    }

    public function recalculatePending(
        Document $document,
        Invoice $invoice,
        CompanySetting $settings,
    ): void {
        $instances = $this->lockInstances($document->id)
            ->whereIn('status', [ReminderInstanceStatus::Pending, ReminderInstanceStatus::Claimed]);
        $this->complete($instances, ReminderInstanceStatus::Skipped, 'schedule_changed');
        $this->reconcile($document, $invoice, $settings, false);
    }

    public function suppress(Document $document, string $reason): void
    {
        $instances = $this->lockInstances($document->id)
            ->whereIn('status', [ReminderInstanceStatus::Pending, ReminderInstanceStatus::Claimed]);
        $this->complete($instances, ReminderInstanceStatus::Suppressed, $reason);
    }

    public function resume(
        Document $document,
        Invoice $invoice,
        CompanySetting $settings,
    ): void {
        $this->reconcile($document, $invoice, $settings, true);
    }

    public function reconcileLedger(
        Document $document,
        Invoice $invoice,
        CompanySetting $settings,
    ): void {
        $transactions = InvoiceTransaction::query()
            ->where('invoice_id', $document->id)->orderBy('id')->lockForUpdate()->get();

        if ($invoice->lifecycle !== InvoiceLifecycle::Issued
            || InvoiceLedger::fromTransactions($transactions)->outstanding($document->total)->isZero()) {
            $this->suppress($document, 'nothing_outstanding');

            return;
        }

        $active = $this->lockInstances($document->id)
            ->contains(fn (ReminderInstance $instance): bool => in_array(
                $instance->status,
                [ReminderInstanceStatus::Pending, ReminderInstanceStatus::Claimed],
                true,
            ));

        if (! $active) {
            $this->resume($document, $invoice, $settings);
        }
    }

    private function reconcile(
        Document $document,
        Invoice $invoice,
        CompanySetting $settings,
        bool $resume,
    ): void {
        $rules = DocumentReminderRule::query()
            ->where('invoice_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $transactions = InvoiceTransaction::query()
            ->where('invoice_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $instances = $this->lockInstances($document->id);

        if ($invoice->lifecycle !== InvoiceLifecycle::Issued
            || InvoiceLedger::fromTransactions($transactions)->outstanding($document->total)->isZero()) {
            $this->complete(
                $instances->whereIn('status', [ReminderInstanceStatus::Pending, ReminderInstanceStatus::Claimed]),
                ReminderInstanceStatus::Suppressed,
                $invoice->lifecycle === InvoiceLifecycle::Cancelled ? 'invoice_cancelled' : 'nothing_outstanding',
            );

            return;
        }

        if ($invoice->due_date === null || $settings->timezone === null) {
            return;
        }

        $completedRuleIds = $instances
            ->whereIn('status', [ReminderInstanceStatus::Sent, ReminderInstanceStatus::Failed])
            ->pluck('document_reminder_rule_id')
            ->filter(fn (?string $id): bool => $id !== null)
            ->all();
        $enabled = $rules
            ->where('enabled', true)
            ->reject(fn (DocumentReminderRule $rule): bool => in_array(
                $rule->id,
                $completedRuleIds,
                true,
            ))
            ->sortBy('display_order')
            ->values();

        if ($resume) {
            $this->resumeRules(
                $document,
                $invoice,
                $settings,
                $enabled,
                $this->resumeSuffix($document, $instances),
            );

            return;
        }

        foreach ($enabled as $rule) {
            $this->materialize->pending($document, $invoice, $settings, $rule, null);
        }
    }

    /**
     * @param  Collection<int, DocumentReminderRule>  $rules
     */
    private function resumeRules(
        Document $document,
        Invoice $invoice,
        CompanySetting $settings,
        Collection $rules,
        string $suffix,
    ): void {
        $now = Date::now()->toImmutable()->utc();
        $pastAfter = $rules->filter(
            fn (DocumentReminderRule $rule): bool => $rule->relation === ReminderRelation::AfterDue
                && $this->calculator->scheduledAt($invoice, $settings, $rule)?->lte($now),
        )->sortByDesc('day_offset')->values();
        $newestPastAfterId = $pastAfter->first()?->id;

        foreach ($rules as $rule) {
            $scheduled = $this->calculator->scheduledAt($invoice, $settings, $rule);

            if ($scheduled === null) {
                continue;
            }

            if ($scheduled->lte($now)) {
                if ($rule->relation === ReminderRelation::BeforeDue) {
                    $this->materialize->terminal(
                        $document, $invoice, $settings, $rule,
                        ReminderInstanceStatus::Skipped, 'stale_before_due', $suffix,
                    );
                } elseif ($rule->id === $newestPastAfterId) {
                    $this->materialize->pending(
                        $document, $invoice, $settings, $rule,
                        $suffix,
                        $this->calculator->nextAutomationAt($settings),
                    );
                } else {
                    $this->materialize->terminal(
                        $document, $invoice, $settings, $rule,
                        ReminderInstanceStatus::Superseded, 'newer_after_due', $suffix,
                    );
                }

                continue;
            }

            $this->materialize->pending(
                $document, $invoice, $settings, $rule, $suffix,
            );
        }
    }

    /** @param Collection<int, ReminderInstance> $instances */
    private function resumeSuffix(Document $document, Collection $instances): string
    {
        $latestTerminal = $instances
            ->filter(fn (ReminderInstance $instance): bool => $instance->status->isTerminal())
            ->last();

        return 'resume-'.($latestTerminal->id ?? 'initial-'.$document->edit_version);
    }

    /** @param Collection<int, ReminderInstance> $instances */
    private function complete(Collection $instances, ReminderInstanceStatus $status, string $reason): void
    {
        $ids = $instances->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        ReminderInstance::query()->whereIn('id', $ids)->update([
            'status' => $status,
            'failure_category' => $reason,
            'failure_summary' => $reason,
            'completed_at' => now(),
        ]);
        JobDispatch::query()
            ->whereIn('target_id', $ids)
            ->where('status', JobDispatchStatus::Pending)
            ->update(['status' => JobDispatchStatus::Cancelled]);
    }

    /** @return Collection<int, ReminderInstance> */
    private function lockInstances(string $invoiceId): Collection
    {
        return ReminderInstance::query()
            ->where('invoice_id', $invoiceId)->orderBy('id')->lockForUpdate()->get();
    }
}
