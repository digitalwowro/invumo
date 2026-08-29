<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Money\DecimalRules;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyConfigurationData;
use App\Modules\Companies\Exceptions\CompanyConfigurationException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Delivery\Actions\RecalculateCompanyPendingReminders;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class UpdateCompanyConfiguration
{
    private const AUDIT_VALUE_FIELDS = [
        'timezone', 'automation_local_time',
        'currency_code', 'currency_precision', 'currency_display_style',
    ];

    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
        private RecalculateCompanyPendingReminders $recalculateReminders,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        CompanyConfigurationData $data,
    ): CompanySetting {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): CompanySetting => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): CompanySetting => $this->update($company, $actor, $data)),
        );
    }

    private function update(
        Company $company,
        User $actor,
        CompanyConfigurationData $data,
    ): CompanySetting {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);

        $company = Company::query()->whereKey($company->id)->firstOrFail();
        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $currencies = CompanyCurrency::query()->orderBy('id')->lockForUpdate()->get();
        $defaultCurrency = $currencies->firstWhere('is_default', true);
        $before = $this->snapshot($company, $settings, $defaultCurrency);
        $after = $this->dataSnapshot($data);
        [$changedBefore, $changedAfter] = $this->changedValues($before, $after);

        if ($changedBefore === []) {
            return $settings;
        }

        $this->confirmScheduleChange($before, $after, $data->scheduleChangeConfirmed);
        $scheduleChanged = $before['timezone'] !== $after['timezone']
            || $before['automation_local_time'] !== $after['automation_local_time'];
        $this->assertCurrencyPrecisionChangeCompatible($currencies, $data);
        $changedFields = array_keys($changedAfter);

        if ($company->name !== $data->displayName) {
            $company->update(['name' => $data->displayName]);
        }

        $settings->update($this->settingsValues($data));
        $this->persistDefaultCurrency($currencies, $data);

        if ($scheduleChanged) {
            $this->recalculateReminders->handle($settings);
        }

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.configuration.updated',
            targetType: 'Company',
            targetId: $company->id,
            before: $this->auditPayload($changedBefore, $changedFields),
            after: $this->auditPayload($changedAfter, $changedFields),
        ));

        return $settings->refresh();
    }

    /** @param Collection<int, CompanyCurrency> $currencies */
    private function assertCurrencyPrecisionChangeCompatible(
        Collection $currencies,
        CompanyConfigurationData $data,
    ): void {
        $currency = $currencies->firstWhere('currency_code', $data->currencyCode);

        if (! $currency instanceof CompanyCurrency
            || $currency->currency_precision === $data->currencyPrecision) {
            return;
        }

        $products = ProductService::query()
            ->where('currency_id', $currency->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'unit_price']);
        RecurringTemplateCustomerValue::query()
            ->where('currency_id', $currency->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        foreach ($products as $product) {
            try {
                DecimalRules::storedMoney((string) $product->unit_price, $data->currencyPrecision);
            } catch (InvalidArgumentException) {
                throw CompanyConfigurationException::currencyPrecisionDependency();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function confirmScheduleChange(array $before, array $after, bool $confirmed): void
    {
        $changed = $before['timezone'] !== null && (
            $before['timezone'] !== $after['timezone']
            || $before['automation_local_time'] !== $after['automation_local_time']
        );

        if ($changed && ! $confirmed) {
            throw CompanyConfigurationException::scheduleChangeNotConfirmed();
        }
    }

    /** @param Collection<int, CompanyCurrency> $currencies */
    private function persistDefaultCurrency(
        Collection $currencies,
        CompanyConfigurationData $data,
    ): void {
        foreach ($currencies as $currency) {
            if ($currency->is_default && $currency->currency_code !== $data->currencyCode) {
                $currency->update(['is_default' => false]);
            }
        }

        $currency = $currencies->firstWhere('currency_code', $data->currencyCode)
            ?? new CompanyCurrency;
        $currency->fill([
            'currency_code' => $data->currencyCode,
            'currency_precision' => $data->currencyPrecision,
            'is_default' => true,
            'active' => true,
        ]);
        $currency->save();
    }

    /** @return array<string, mixed> */
    private function settingsValues(CompanyConfigurationData $data): array
    {
        return [
            'legal_name' => $data->legalName,
            'trading_name' => $data->tradingName,
            'address_line_1' => $data->addressLine1,
            'address_line_2' => $data->addressLine2,
            'city' => $data->city,
            'region' => $data->region,
            'postal_code' => $data->postalCode,
            'country_code' => $data->countryCode,
            'tax_registration_label' => $data->taxRegistrationLabel,
            'tax_registration_identifier' => $data->taxRegistrationIdentifier,
            'business_registration_label' => $data->businessRegistrationLabel,
            'business_registration_number' => $data->businessRegistrationNumber,
            'email' => $data->email,
            'phone' => $data->phone,
            'website' => $data->website,
            'timezone' => $data->timezone,
            'automation_local_time' => $data->automationLocalTime,
            'currency_display_style' => $data->currencyDisplayStyle,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(
        Company $company,
        CompanySetting $settings,
        ?CompanyCurrency $currency,
    ): array {
        return [
            'display_name' => $company->name,
            'legal_name' => $settings->legal_name,
            'trading_name' => $settings->trading_name,
            'address_line_1' => $settings->address_line_1,
            'address_line_2' => $settings->address_line_2,
            'city' => $settings->city,
            'region' => $settings->region,
            'postal_code' => $settings->postal_code,
            'country_code' => $settings->country_code,
            'tax_registration_label' => $settings->tax_registration_label,
            'tax_registration_identifier' => $settings->tax_registration_identifier,
            'business_registration_label' => $settings->business_registration_label,
            'business_registration_number' => $settings->business_registration_number,
            'email' => $settings->email,
            'phone' => $settings->phone,
            'website' => $settings->website,
            'timezone' => $settings->timezone,
            'automation_local_time' => substr((string) $settings->automation_local_time, 0, 5),
            'currency_code' => $currency?->currency_code,
            'currency_precision' => $currency?->currency_precision,
            'currency_display_style' => $settings->currency_display_style?->value,
        ];
    }

    /** @return array<string, mixed> */
    private function dataSnapshot(CompanyConfigurationData $data): array
    {
        return [
            'display_name' => $data->displayName,
            ...$this->settingsValues($data),
            'automation_local_time' => $data->automationLocalTime,
            'currency_code' => $data->currencyCode,
            'currency_precision' => $data->currencyPrecision,
            'currency_display_style' => $data->currencyDisplayStyle->value,
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
     * @param  array<string, mixed>  $changedValues
     * @param  list<string>  $changedFields
     */
    private function auditPayload(array $changedValues, array $changedFields): AuditPayload
    {
        $retainedValues = array_intersect_key(
            $changedValues,
            array_fill_keys(self::AUDIT_VALUE_FIELDS, true),
        );

        return AuditPayload::fromAllowedFields(
            ['changed_fields' => $changedFields, ...$retainedValues],
            ['changed_fields', ...self::AUDIT_VALUE_FIELDS],
        );
    }
}
