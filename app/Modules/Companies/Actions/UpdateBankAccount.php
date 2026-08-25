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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateBankAccount
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private BankAccountDataValidator $validator,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $accountId,
        BankAccountData $data,
    ): BankAccount {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): BankAccount => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): BankAccount => $this->update(
                    $company,
                    $actor,
                    $accountId,
                    $data,
                )),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $accountId,
        BankAccountData $data,
    ): BankAccount {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $this->validator->validate($data);
        $currencies = CompanyCurrency::query()->orderBy('id')->lockForUpdate()->get();
        $currencyCode = $this->currencyCode($currencies, $data->currencyId);
        $accounts = BankAccount::query()->orderBy('id')->lockForUpdate()->get();
        $locked = $accounts->firstWhere('id', $accountId);

        if ($locked === null) {
            throw (new ModelNotFoundException)->setModel(BankAccount::class, [$accountId]);
        }

        if ($locked->archived_at !== null) {
            throw BankAccountException::archived();
        }

        $before = $this->snapshot($locked);
        $after = $this->dataSnapshot($data);
        [$changedBefore, $changedAfter] = $this->changedValues($before, $after);

        if ($changedBefore === []) {
            return $locked;
        }

        if ($data->isDefault && ! $locked->is_default) {
            foreach ($accounts as $account) {
                if ($account->id !== $locked->id && $account->is_default) {
                    $account->update(['is_default' => false]);
                }
            }
        }

        $locked->update($this->values($data));
        $changedFields = array_keys($changedAfter);
        $beforeCurrencyCode = $this->currencyCodeForAudit(
            $currencies,
            $changedBefore['currency_id'] ?? null,
        );

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.bank_account.updated',
            targetType: 'BankAccount',
            targetId: $locked->id,
            before: $this->auditPayload($changedBefore, $changedFields, $beforeCurrencyCode),
            after: $this->auditPayload($changedAfter, $changedFields, $currencyCode),
        ));

        return $locked->refresh();
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

    /** @param Collection<int, CompanyCurrency> $currencies */
    private function currencyCodeForAudit(Collection $currencies, mixed $currencyId): ?string
    {
        if (! is_string($currencyId)) {
            return null;
        }

        return $currencies->firstWhere('id', $currencyId)?->currency_code;
    }

    /** @return array<string, mixed> */
    private function snapshot(BankAccount $account): array
    {
        $routing = $account->local_routing_details ?? [];
        ksort($routing);

        return [
            'label' => $account->label,
            'bank_name' => $account->bank_name,
            'account_holder' => $account->account_holder,
            'account_number' => $account->account_number,
            'swift_bic' => $account->swift_bic,
            'currency_id' => $account->currency_id,
            'local_routing_details' => $routing,
            'is_default' => $account->is_default,
        ];
    }

    /** @return array<string, mixed> */
    private function dataSnapshot(BankAccountData $data): array
    {
        $routing = $data->localRoutingDetails;
        ksort($routing);

        return [
            'label' => $data->label,
            'bank_name' => $data->bankName,
            'account_holder' => $data->accountHolder,
            'account_number' => $data->accountNumber,
            'swift_bic' => $data->swiftBic,
            'currency_id' => $data->currencyId,
            'local_routing_details' => $routing,
            'is_default' => $data->isDefault,
        ];
    }

    /** @return array<string, mixed> */
    private function values(BankAccountData $data): array
    {
        return [
            ...$this->dataSnapshot($data),
            'local_routing_details' => $data->localRoutingDetails ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function changedValues(array $before, array $after): array
    {
        $changed = array_filter(
            array_keys($after),
            fn (string $field): bool => $before[$field] !== $after[$field],
        );
        $keys = array_fill_keys($changed, true);

        return [array_intersect_key($before, $keys), array_intersect_key($after, $keys)];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $changedFields
     */
    private function auditPayload(
        array $values,
        array $changedFields,
        ?string $currencyCode,
    ): AuditPayload {
        $payload = ['changed_fields' => $changedFields];

        if (array_key_exists('currency_id', $values)) {
            $payload['currency_code'] = $currencyCode;
        }

        if (array_key_exists('is_default', $values)) {
            $payload['is_default'] = $values['is_default'];
        }

        return AuditPayload::fromAllowedFields(
            $payload,
            ['changed_fields', 'currency_code', 'is_default'],
        );
    }
}
