<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Exceptions\TaxPresetException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class RestoreTaxPreset
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $presetId): TaxPreset
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): TaxPreset => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): TaxPreset => $this->restore($company, $actor, $presetId)),
        );
    }

    private function restore(Company $company, User $actor, string $presetId): TaxPreset
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $presets = TaxPreset::query()->orderBy('id')->lockForUpdate()->get();
        $preset = $presets->firstWhere('id', $presetId);

        if (! $preset instanceof TaxPreset) {
            abort(404);
        }

        if ($preset->archived_at === null) {
            throw TaxPresetException::notArchived();
        }

        $preset->update(['archived_at' => null]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.tax_preset.restored',
            targetType: 'TaxPreset',
            targetId: $preset->id,
            before: AuditPayload::fromAllowedFields(['archived' => true], ['archived']),
            after: AuditPayload::fromAllowedFields(['archived' => false], ['archived']),
        ));

        return $preset->refresh();
    }
}
