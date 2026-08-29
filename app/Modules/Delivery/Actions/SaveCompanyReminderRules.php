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
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Delivery\Support\ReminderRuleLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveCompanyReminderRules
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockCompanyReminderRules $lockRules,
        private RecordAuditEvent $audit,
    ) {}

    /** @param list<ReminderRuleData> $rules */
    public function handle(Company $company, User $actor, array $rules): void
    {
        $this->tenantContext->runForMember($actor, $company->id, fn (): mixed => DB::connection(
            config('database.tenant_connection'),
        )->transaction(fn (): bool => $this->save($company, $actor, $rules), 3));
    }

    /** @param list<ReminderRuleData> $rules */
    private function save(Company $company, User $actor, array $rules): bool
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $existing = $this->lockRules->handle();
        $this->assertUnique($rules);
        DB::connection(config('database.tenant_connection'))->statement(<<<'SQL'
            SET CONSTRAINTS company_reminder_rules_schedule_unique,
                company_reminder_rules_order_unique DEFERRED
            SQL);
        $submitted = [];

        foreach ($rules as $position => $rule) {
            $model = $rule->id === null ? null : $existing->firstWhere('id', $rule->id);

            if ($rule->id !== null && ! $model instanceof CompanyReminderRule) {
                abort(404);
            }

            $model ??= new CompanyReminderRule;
            $model->fill([
                'relation' => $rule->relation,
                'day_offset' => $rule->dayOffset,
                'enabled' => $rule->enabled,
                'display_order' => $position + 1,
            ])->save();
            $submitted[] = $model->id;
        }

        CompanyReminderRule::query()->whereNotIn('id', $submitted)->delete();
        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.reminder_rules.updated',
            targetType: 'Company',
            targetId: $company->id,
            after: AuditPayload::fromAllowedFields([
                'rule_count' => count($rules),
                'enabled_count' => count(array_filter($rules, fn (ReminderRuleData $rule): bool => $rule->enabled)),
            ], ['rule_count', 'enabled_count']),
        ));

        return true;
    }

    /** @param list<ReminderRuleData> $rules */
    private function assertUnique(array $rules): void
    {
        $keys = array_map(
            fn (ReminderRuleData $rule): string => $rule->relation->value.':'.$rule->dayOffset,
            $rules,
        );
        $ids = array_values(array_filter(array_map(
            fn (ReminderRuleData $rule): ?string => $rule->id,
            $rules,
        )));
        $invalidRange = count($rules) > ReminderRuleLimits::PER_SCOPE
            || array_any($rules, fn (ReminderRuleData $rule): bool => $rule->dayOffset < 0
                || $rule->dayOffset > ReminderRuleLimits::MAX_DAY_OFFSET);

        if ($invalidRange
            || count($ids) !== count(array_unique($ids))
            || count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages([
                'rules' => __('companies_ui.settings.reminders.errors.invalid'),
            ]);
        }
    }
}
