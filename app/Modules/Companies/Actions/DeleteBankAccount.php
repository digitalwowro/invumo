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
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Recurring\Models\RecurringTemplateDefault;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class DeleteBankAccount
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $accountId): void
    {
        try {
            $this->tenantContext->runForMember(
                $actor,
                $company->id,
                fn () => DB::connection(config('database.tenant_connection'))
                    ->transaction(fn () => $this->delete($company, $actor, $accountId)),
            );
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? null, ['23001', '23503'], true)) {
                throw BankAccountException::dependencies();
            }

            throw $exception;
        }
    }

    private function delete(Company $company, User $actor, string $accountId): void
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $accounts = BankAccount::query()->orderBy('id')->lockForUpdate()->get();
        $account = $accounts->firstWhere('id', $accountId);

        if (! $account instanceof BankAccount) {
            abort(404);
        }

        $documentSnapshots = DocumentBankSnapshot::query()
            ->where('bank_account_id', $account->id)
            ->orderBy('id')->lockForUpdate()->first(['id']);
        $templateDefaults = RecurringTemplateDefault::query()
            ->where('bank_account_id', $account->id)
            ->orderBy('id')->lockForUpdate()->first(['id']);

        if ($documentSnapshots !== null || $templateDefaults !== null) {
            throw BankAccountException::dependencies();
        }

        $account->delete();

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.bank_account.deleted',
            targetType: 'BankAccount',
            targetId: $account->id,
            before: AuditPayload::fromAllowedFields(['deleted' => false], ['deleted']),
            after: AuditPayload::fromAllowedFields(['deleted' => true], ['deleted']),
        ));
    }
}
