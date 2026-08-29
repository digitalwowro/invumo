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
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class RestoreBankAccount
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
                ->transaction(fn (): BankAccount => $this->restore($company, $actor, $accountId)),
        );
    }

    private function restore(Company $company, User $actor, string $accountId): BankAccount
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $currencies = CompanyCurrency::query()->orderBy('id')->lockForUpdate()->get();
        $accounts = BankAccount::query()->orderBy('id')->lockForUpdate()->get();
        $account = $accounts->firstWhere('id', $accountId);

        if (! $account instanceof BankAccount) {
            abort(404);
        }

        if ($account->archived_at === null) {
            throw BankAccountException::notArchived();
        }

        $currency = $account->currency_id === null
            ? null
            : $currencies->firstWhere('id', $account->currency_id);

        if ($account->currency_id !== null && (! $currency instanceof CompanyCurrency || ! $currency->active)) {
            throw BankAccountException::currencyUnavailable();
        }

        $account->update(['archived_at' => null]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.bank_account.restored',
            targetType: 'BankAccount',
            targetId: $account->id,
            before: AuditPayload::fromAllowedFields(['archived' => true], ['archived']),
            after: AuditPayload::fromAllowedFields(['archived' => false], ['archived']),
        ));

        return $account->refresh();
    }
}
