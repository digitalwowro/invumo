<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyCurrencyData;
use App\Modules\Companies\Exceptions\CompanyCurrencyException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class CreateCompanyCurrency
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        CompanyCurrencyData $data,
    ): CompanyCurrency {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): CompanyCurrency => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): CompanyCurrency => $this->create($company, $actor, $data)),
        );
    }

    private function create(
        Company $company,
        User $actor,
        CompanyCurrencyData $data,
    ): CompanyCurrency {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $currencies = CompanyCurrency::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($currencies->contains('currency_code', $data->currencyCode)) {
            throw CompanyCurrencyException::duplicate();
        }

        $isDefault = $data->isDefault || ! $currencies->contains('is_default', true);

        if ($isDefault) {
            foreach ($currencies as $currency) {
                if ($currency->is_default) {
                    $currency->update(['is_default' => false]);
                }
            }
        }

        $currency = CompanyCurrency::query()->create([
            'currency_code' => $data->currencyCode,
            'currency_precision' => $data->currencyPrecision,
            'is_default' => $isDefault,
            'active' => true,
        ]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.currency.created',
            targetType: 'CompanyCurrency',
            targetId: $currency->id,
            after: AuditPayload::fromAllowedFields([
                'changed_fields' => ['currency_code', 'currency_precision', 'is_default', 'active'],
                'currency_code' => $currency->currency_code,
                'currency_precision' => $currency->currency_precision,
                'is_default' => $currency->is_default,
                'active' => true,
            ], ['changed_fields', 'currency_code', 'currency_precision', 'is_default', 'active']),
        ));

        return $currency;
    }
}
