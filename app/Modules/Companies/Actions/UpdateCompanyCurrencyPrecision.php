<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Exceptions\CompanyCurrencyException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Companies\Rules\CurrencyPrecisionCompatibility;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCompanyCurrencyPrecision
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private CurrencyPrecisionCompatibility $precisionCompatibility,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $currencyId,
        int $precision,
    ): CompanyCurrency {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): CompanyCurrency => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): CompanyCurrency => $this->update(
                    $company, $actor, $currencyId, $precision,
                )),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $currencyId,
        int $precision,
    ): CompanyCurrency {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $currencies = CompanyCurrency::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $currency = $currencies->firstWhere('id', $currencyId);

        if (! $currency instanceof CompanyCurrency) {
            abort(404);
        }

        if (! $currency->active) {
            throw CompanyCurrencyException::inactive();
        }

        if ($currency->currency_precision === $precision) {
            return $currency;
        }

        if (! $this->precisionCompatibility->allows($currency, $precision)) {
            throw CompanyCurrencyException::precisionDependency();
        }

        $before = $currency->currency_precision;
        $currency->update(['currency_precision' => $precision]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.currency.updated',
            targetType: 'CompanyCurrency',
            targetId: $currency->id,
            before: $this->auditPayload($currency->currency_code, $before),
            after: $this->auditPayload($currency->currency_code, $precision),
        ));

        return $currency->refresh();
    }

    private function auditPayload(string $code, int $precision): AuditPayload
    {
        return AuditPayload::fromAllowedFields([
            'changed_fields' => ['currency_precision'],
            'currency_code' => $code,
            'currency_precision' => $precision,
        ], ['changed_fields', 'currency_code', 'currency_precision']);
    }
}
