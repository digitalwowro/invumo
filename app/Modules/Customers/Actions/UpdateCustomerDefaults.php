<?php

namespace App\Modules\Customers\Actions;

use App\Foundation\Documents\DocumentFieldLimits;
use App\Foundation\Localization\SupportedLocales;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Data\CustomerDefaultsData;
use App\Modules\Customers\Exceptions\CustomerDefaultsException;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCustomerDefaults
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $customerId,
        CustomerDefaultsData $data,
    ): Customer {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Customer => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): Customer => $this->update(
                    $company, $actor, $customerId, $data,
                )),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $customerId,
        CustomerDefaultsData $data,
    ): Customer {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCustomers);
        $currencies = CompanyCurrency::query()->orderBy('id')->lockForUpdate()->get();
        $taxPresets = TaxPreset::query()->orderBy('id')->lockForUpdate()->get();
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

        if ($customer->archived_at !== null) {
            throw CustomerDefaultsException::customerArchived();
        }

        $this->assertValid($data, $currencies, $taxPresets);
        $before = $this->snapshot($customer);
        $after = $data->attributes();
        $changedFields = array_keys(array_filter(
            $after,
            fn (mixed $value, string $field): bool => $before[$field] !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changedFields === []) {
            return $customer;
        }

        $customer->update($after);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.customer_defaults.updated',
            targetType: 'Customer',
            targetId: $customer->id,
            before: $this->auditPayload($before, $changedFields, $currencies, $taxPresets),
            after: $this->auditPayload($after, $changedFields, $currencies, $taxPresets),
        ));

        return $customer->refresh();
    }

    /**
     * @param  Collection<int, CompanyCurrency>  $currencies
     * @param  Collection<int, TaxPreset>  $taxPresets
     */
    private function assertValid(
        CustomerDefaultsData $data,
        Collection $currencies,
        Collection $taxPresets,
    ): void {
        if ($data->currencyId !== null && ! $currencies->contains(
            fn (CompanyCurrency $currency): bool => $currency->id === $data->currencyId
                && $currency->active,
        )) {
            throw CustomerDefaultsException::currencyUnavailable();
        }

        if ($data->documentLanguage !== null && ! SupportedLocales::includes($data->documentLanguage)) {
            throw CustomerDefaultsException::languageUnavailable();
        }

        if ($data->paymentTermDays !== null && (
            $data->paymentTermDays < 0
            || $data->paymentTermDays > DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET
        )) {
            throw CustomerDefaultsException::paymentTermInvalid();
        }

        if ($data->taxPresetId !== null && ! $taxPresets->contains(
            fn (TaxPreset $preset): bool => $preset->id === $data->taxPresetId
                && $preset->archived_at === null,
        )) {
            throw CustomerDefaultsException::taxPresetUnavailable();
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Customer $customer): array
    {
        return [
            'currency_id' => $customer->currency_id,
            'document_language' => $customer->document_language,
            'payment_term_days' => $customer->payment_term_days,
            'tax_preset_id' => $customer->tax_preset_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $changedFields
     * @param  Collection<int, CompanyCurrency>  $currencies
     * @param  Collection<int, TaxPreset>  $taxPresets
     */
    private function auditPayload(
        array $values,
        array $changedFields,
        Collection $currencies,
        Collection $taxPresets,
    ): AuditPayload {
        $payload = ['changed_fields' => $changedFields];

        if (in_array('currency_id', $changedFields, true)) {
            $payload['currency_code'] = $currencies
                ->firstWhere('id', $values['currency_id'])?->currency_code;
        }

        if (in_array('document_language', $changedFields, true)) {
            $payload['document_language'] = $values['document_language'];
        }

        if (in_array('payment_term_days', $changedFields, true)) {
            $payload['payment_term_days'] = $values['payment_term_days'];
        }

        if (in_array('tax_preset_id', $changedFields, true)) {
            $payload['tax_percentage'] = $taxPresets
                ->firstWhere('id', $values['tax_preset_id'])?->percentage;
        }

        return AuditPayload::fromAllowedFields($payload, [
            'changed_fields', 'currency_code', 'document_language',
            'payment_term_days', 'tax_percentage',
        ]);
    }
}
