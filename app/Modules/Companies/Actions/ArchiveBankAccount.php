<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Exceptions\BankAccountException;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Recurring\Models\RecurringTemplateDefault;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveBankAccount
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $accountId): BankAccount
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): BankAccount => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): BankAccount => $this->archive(
                    $company,
                    $actor,
                    $accountId,
                )),
        );
    }

    private function archive(Company $company, User $actor, string $accountId): BankAccount
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $accounts = BankAccount::query()->orderBy('id')->lockForUpdate()->get();
        $locked = $accounts->firstWhere('id', $accountId);

        if ($locked === null) {
            throw (new ModelNotFoundException)->setModel(BankAccount::class, [$accountId]);
        }

        if ($locked->archived_at !== null) {
            throw BankAccountException::archived();
        }

        // A recurring bank override is a self-contained snapshot. Lock it for stable
        // source mutation ordering, but allow the source account to be archived.
        RecurringTemplateDefault::query()
            ->where('bank_account_id', $locked->id)
            ->orderBy('id')->lockForUpdate()->get(['id']);

        $wasDefault = $locked->is_default;
        $locked->update(['is_default' => false, 'archived_at' => now()]);
        $changedFields = $wasDefault ? ['is_default', 'archived'] : ['archived'];

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.bank_account.archived',
            targetType: 'BankAccount',
            targetId: $locked->id,
            before: AuditPayload::fromAllowedFields([
                'changed_fields' => $changedFields,
                'is_default' => $wasDefault,
                'archived' => false,
            ], ['changed_fields', 'is_default', 'archived']),
            after: AuditPayload::fromAllowedFields([
                'changed_fields' => $changedFields,
                'is_default' => false,
                'archived' => true,
            ], ['changed_fields', 'is_default', 'archived']),
        ));

        return $locked->refresh();
    }
}
