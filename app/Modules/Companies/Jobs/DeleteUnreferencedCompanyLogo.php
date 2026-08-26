<?php

namespace App\Modules\Companies\Jobs;

use App\Foundation\Jobs\JobIdentity;
use App\Foundation\Jobs\TenantJob;
use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Data\StoredCompanyAsset;
use App\Modules\Companies\Models\CompanyAsset;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Support\CompanyAssetStorage;
use App\Modules\Documents\Models\DocumentCompanySnapshot;

final class DeleteUnreferencedCompanyLogo extends TenantJob
{
    public function __construct(string $companyId, public readonly string $assetId)
    {
        parent::__construct(new JobIdentity(
            companyId: $companyId,
            idempotencyKey: 'company-logo-cleanup:'.$assetId,
            component: 'company.logo_cleanup',
        ));
    }

    public function handle(
        TenantContext $tenantContext,
        TenantJobExecution $execution,
        CompanyAssetStorage $storage,
    ): void {
        [$asset, $skipCode] = $tenantContext->runAsSystem(
            $this->identity->companyId,
            fn (): array => $this->resolveDeletion(),
        );

        if ($asset === null) {
            $execution->skip($skipCode);

            return;
        }

        $storage->delete($asset);
    }

    /** @return array{StoredCompanyAsset|null, string} */
    private function resolveDeletion(): array
    {
        $asset = CompanyAsset::query()->whereKey($this->assetId)->lockForUpdate()->first();

        if ($asset === null) {
            return [null, 'company_logo_unavailable'];
        }

        if (CompanySetting::query()->where('logo_asset_id', $asset->id)->exists()) {
            return [null, 'company_logo_still_referenced'];
        }

        if (DocumentCompanySnapshot::query()->where('logo_asset_id', $asset->id)->exists()) {
            return [null, 'company_logo_retained_by_document'];
        }

        if ($asset->deleted_at === null) {
            $asset->update(['deleted_at' => now()]);
        }

        return [new StoredCompanyAsset($asset->id, $asset->storage_disk, $asset->storage_key), ''];
    }
}
