<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\BankAccountData;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Exceptions\BankAccountException;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Companies\Rules\BankAccountDataValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class CreateBankAccount
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private BankAccountDataValidator $validator,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, BankAccountData $data): BankAccount
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): BankAccount => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): BankAccount => $this->create($company, $actor, $data)),
        );
    }

    private function create(Company $company, User $actor, BankAccountData $data): BankAccount
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $this->validator->validate($data);
        $currencies = CompanyCurrency::query()->orderBy('id')->lockForUpdate()->get();
        $currencyCode = $this->currencyCode($currencies, $data->currencyId);
        $accounts = BankAccount::query()->orderBy('id')->lockForUpdate()->get();

        if ($data->isDefault) {
            foreach ($accounts as $account) {
                if ($account->is_default) {
                    $account->update(['is_default' => false]);
                }
            }
        }

        $account = BankAccount::query()->create($this->values($data));
        $changedFields = [
            'label', 'bank_name', 'account_holder', 'account_number', 'is_default',
        ];

        foreach (['swift_bic', 'currency_id', 'local_routing_details'] as $field) {
            if ($account->{$field} !== null && $account->{$field} !== []) {
                $changedFields[] = $field;
            }
        }

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.bank_account.created',
            targetType: 'BankAccount',
            targetId: $account->id,
            after: AuditPayload::fromAllowedFields([
                'changed_fields' => $changedFields,
                'currency_code' => $currencyCode,
                'is_default' => $account->is_default,
            ], ['changed_fields', 'currency_code', 'is_default']),
        ));

        return $account;
    }

    /** @param Collection<int, CompanyCurrency> $currencies */
    private function currencyCode(Collection $currencies, ?string $currencyId): ?string
    {
        if ($currencyId === null) {
            return null;
        }

        $currency = $currencies->first(
            fn (CompanyCurrency $candidate): bool => $candidate->id === $currencyId
                && $candidate->active,
        );

        if ($currency === null) {
            throw BankAccountException::currencyUnavailable();
        }

        return $currency->currency_code;
    }

    /** @return array<string, mixed> */
    private function values(BankAccountData $data): array
    {
        return [
            'label' => $data->label,
            'bank_name' => $data->bankName,
            'account_holder' => $data->accountHolder,
            'account_number' => $data->accountNumber,
            'swift_bic' => $data->swiftBic,
            'currency_id' => $data->currencyId,
            'local_routing_details' => $data->localRoutingDetails ?: null,
            'is_default' => $data->isDefault,
        ];
    }
}
