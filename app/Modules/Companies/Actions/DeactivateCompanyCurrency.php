<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Exceptions\CompanyCurrencyException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Customers\Models\Customer;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use Illuminate\Support\Facades\DB;

final readonly class DeactivateCompanyCurrency
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
                ->transaction(fn (): CompanyCurrency => $this->deactivate(
                    $company, $actor, $currencyId,
                )),
        );
    }

    private function deactivate(
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
            throw CompanyCurrencyException::defaultDependency();
        }

        $customers = Customer::query()
            ->where('currency_id', $currency->id)
            ->orderBy('id')->lockForUpdate()->get(['id']);
        $products = ProductService::query()
            ->where('currency_id', $currency->id)
            ->orderBy('id')->lockForUpdate()->get(['id']);
        // Explicit recurring rows retain complete currency snapshots. Locking only
        // serializes concurrent source selection; retained rows do not block deactivation.
        RecurringTemplateCustomerValue::query()
            ->where('currency_id', $currency->id)
            ->orderBy('id')->lockForUpdate()->get(['id']);

        if ($customers->isNotEmpty() || $products->isNotEmpty()) {
            throw CompanyCurrencyException::sourceDependencies();
        }

        $currency->update(['active' => false]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.currency.deactivated',
            targetType: 'CompanyCurrency',
            targetId: $currency->id,
            before: $this->auditPayload($currency->currency_code, true),
            after: $this->auditPayload($currency->currency_code, false),
        ));

        return $currency->refresh();
    }

    private function auditPayload(string $code, bool $active): AuditPayload
    {
        return AuditPayload::fromAllowedFields([
            'changed_fields' => ['active'],
            'currency_code' => $code,
            'active' => $active,
        ], ['changed_fields', 'currency_code', 'active']);
    }
}
