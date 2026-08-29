<?php

namespace App\Modules\Delivery\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\ReminderRuleData;
use App\Modules\Delivery\Models\DocumentReminderRule;
use App\Modules\Delivery\Support\ReminderRuleLimits;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveDocumentReminderRules
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private InvoiceReminderSchedule $schedule,
        private LockDocumentDeliveryHistory $deliveryHistory,
        private RecordAuditEvent $audit,
    ) {}

    /** @param list<ReminderRuleData> $rules */
    public function handle(
        Company $company,
        User $actor,
        string $invoiceId,
        int $editVersion,
        array $rules,
    ): void {
        $this->tenantContext->runForMember($actor, $company->id, fn (): mixed => DB::connection(
            config('database.tenant_connection'),
        )->transaction(fn (): bool => $this->save(
            $company, $actor, $invoiceId, $editVersion, $rules,
        ), 3));
    }

    /** @param list<ReminderRuleData> $rules */
    private function save(
        Company $company,
        User $actor,
        string $invoiceId,
        int $editVersion,
        array $rules,
    ): bool {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);
        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $document = Document::query()
            ->whereKey($invoiceId)->where('kind', DocumentKind::Invoice)
            ->lockForUpdate()->firstOrFail();
        $invoice = Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $existing = DocumentReminderRule::query()
            ->where('invoice_id', $document->id)->orderBy('id')->lockForUpdate()->get();

        if ($document->edit_version !== $editVersion) {
            throw ValidationException::withMessages([
                'edit_version' => __('invoices_ui.reminders.errors.stale'),
            ]);
        }

        if ($this->deliveryHistory->hasPendingDirect($document->id)) {
            throw ValidationException::withMessages([
                'rules' => __('invoices_ui.errors.document_delivery_pending'),
            ]);
        }

        $this->assertValid(
            $existing->map(fn (DocumentReminderRule $rule): string => $rule->id)->values()->all(),
            $rules,
        );
        DB::connection(config('database.tenant_connection'))->statement(<<<'SQL'
            SET CONSTRAINTS document_reminder_rules_schedule_unique,
                document_reminder_rules_order_unique DEFERRED
            SQL);

        $submitted = [];

        foreach ($rules as $position => $rule) {
            $model = $rule->id === null ? new DocumentReminderRule : $existing->firstWhere('id', $rule->id);
            abort_unless($model instanceof DocumentReminderRule, 404);
            $model->fill([
                'invoice_id' => $document->id,
                'relation' => $rule->relation,
                'day_offset' => $rule->dayOffset,
                'enabled' => $rule->enabled,
                'display_order' => $position + 1,
            ])->save();
            $submitted[] = $model->id;
        }

        DocumentReminderRule::query()
            ->where('invoice_id', $document->id)
            ->whereNotIn('id', $submitted)
            ->delete();
        $document->update(['edit_version' => $document->edit_version + 1]);
        $this->schedule->recalculatePending($document, $invoice, $settings);
        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.invoice.reminder_rules.updated',
            targetType: 'Invoice',
            targetId: $document->id,
            after: AuditPayload::fromAllowedFields([
                'rule_count' => count($rules),
                'enabled_count' => count(array_filter($rules, fn (ReminderRuleData $rule): bool => $rule->enabled)),
                'edit_version' => $document->edit_version,
            ], ['rule_count', 'enabled_count', 'edit_version']),
        ));

        return true;
    }

    /**
     * @param  array<int, string>  $existingIds
     * @param  list<ReminderRuleData>  $rules
     */
    private function assertValid(array $existingIds, array $rules): void
    {
        $submittedIds = array_values(array_filter(array_map(
            fn (ReminderRuleData $rule): ?string => $rule->id,
            $rules,
        )));
        $keys = array_map(
            fn (ReminderRuleData $rule): string => $rule->relation->value.':'.$rule->dayOffset,
            $rules,
        );
        $invalidRange = count($rules) > ReminderRuleLimits::PER_SCOPE
            || array_any($rules, fn (ReminderRuleData $rule): bool => $rule->dayOffset < 0
                || $rule->dayOffset > ReminderRuleLimits::MAX_DAY_OFFSET);

        sort($existingIds);
        sort($submittedIds);

        if ($invalidRange
            || array_diff($submittedIds, $existingIds) !== []
            || count($submittedIds) !== count(array_unique($submittedIds))
            || count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages([
                'rules' => __('invoices_ui.reminders.errors.invalid_rules'),
            ]);
        }
    }
}
