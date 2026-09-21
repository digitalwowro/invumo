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
use Illuminate\Support\Facades\DB;

final readonly class SetDefaultCompanyCurrency
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $currencyId): CompanyCurrency
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): CompanyCurrency => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): CompanyCurrency => $this->setDefault(
                    $company, $actor, $currencyId,
                )),
        );
    }

    private function setDefault(
        Company $company,
        User $actor,
        string $currencyId,
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

        if ($currency->is_default) {
            return $currency;
        }

        $previous = $currencies->firstWhere('is_default', true);

        if ($previous instanceof CompanyCurrency) {
            $previous->update(['is_default' => false]);
        }

        $currency->update(['is_default' => true]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.currency.default_changed',
            targetType: 'CompanyCurrency',
            targetId: $currency->id,
            before: $this->auditPayload($previous?->currency_code),
            after: $this->auditPayload($currency->currency_code),
        ));

        return $currency->refresh();
    }

    private function auditPayload(?string $code): AuditPayload
    {
        return AuditPayload::fromAllowedFields([
            'changed_fields' => ['default_currency'],
            'currency_code' => $code,
        ], ['changed_fields', 'currency_code']);
    }
}
