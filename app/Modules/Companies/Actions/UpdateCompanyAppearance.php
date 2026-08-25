<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyAppearanceData;
use App\Modules\Companies\Data\CompanyAssetPurpose;
use App\Modules\Companies\Data\StoredCompanyAsset;
use App\Modules\Companies\Jobs\DeleteUnreferencedCompanyLogo;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyAsset;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Companies\Support\CompanyAssetStorage;
use App\Modules\Companies\Support\CompanyLogoUploadPolicy;
use App\Modules\Companies\Support\OutwardBrandTheme;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final readonly class UpdateCompanyAppearance
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private CompanyLogoUploadPolicy $logoPolicy,
        private CompanyAssetStorage $assetStorage,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        CompanyAppearanceData $data,
    ): CompanySetting {
        if ($data->logo !== null && $data->removeLogo) {
            throw new InvalidArgumentException('A Company logo cannot be uploaded and removed together.');
        }

        OutwardBrandTheme::resolve($data->primaryBrandColor);
        $stored = null;
        $retiredAssetId = null;

        try {
            $settings = $this->tenantContext->runForMember(
                $actor,
                $company->id,
                function () use ($company, $actor, $data, &$stored, &$retiredAssetId): CompanySetting {
                    return DB::connection(config('database.tenant_connection'))
                        ->transaction(function () use (
                            $company,
                            $actor,
                            $data,
                            &$stored,
                            &$retiredAssetId,
                        ): CompanySetting {
                            return $this->update(
                                $company,
                                $actor,
                                $data,
                                $stored,
                                $retiredAssetId,
                            );
                        });
                },
            );
        } catch (Throwable $exception) {
            if ($stored !== null) {
                try {
                    $this->assetStorage->delete($stored);
                } catch (Throwable) {
                    // The database remains authoritative; orphan cleanup can safely retry later.
                }
            }

            throw $exception;
        }

        if ($retiredAssetId !== null) {
            DeleteUnreferencedCompanyLogo::dispatch($company->id, $retiredAssetId)
                ->onConnection('database')
                ->onQueue('default')
                ->afterCommit();
        }

        return $settings;
    }

    private function update(
        Company $company,
        User $actor,
        CompanyAppearanceData $data,
        ?StoredCompanyAsset &$stored,
        ?string &$retiredAssetId,
    ): CompanySetting {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $currentAsset = $settings->logo_asset_id === null
            ? null
            : CompanyAsset::query()->whereKey($settings->logo_asset_id)->lockForUpdate()->first();
        $newAsset = $this->replacementAsset($company, $actor, $data, $currentAsset, $stored);
        $nextAssetId = null;

        if (! $data->removeLogo) {
            $nextAssetId = $newAsset !== null ? $newAsset->id : $currentAsset?->id;
        }
        $changedFields = [];

        if ($settings->primary_brand_color !== $data->primaryBrandColor) {
            $changedFields[] = 'primary_brand_color';
        }

        if ($settings->logo_asset_id !== $nextAssetId) {
            $changedFields[] = 'logo';
            $retiredAssetId = $settings->logo_asset_id;
        }

        if ($changedFields === []) {
            return $settings;
        }

        $before = [
            'changed_fields' => $changedFields,
            'primary_brand_color' => $settings->primary_brand_color,
            'has_logo' => $settings->logo_asset_id !== null,
        ];
        $settings->update([
            'primary_brand_color' => $data->primaryBrandColor,
            'logo_asset_id' => $nextAssetId,
        ]);
        $after = [
            'changed_fields' => $changedFields,
            'primary_brand_color' => $settings->primary_brand_color,
            'has_logo' => $settings->logo_asset_id !== null,
        ];

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.appearance.updated',
            targetType: 'Company',
            targetId: $company->id,
            before: AuditPayload::fromAllowedFields(
                $before,
                ['changed_fields', 'primary_brand_color', 'has_logo'],
            ),
            after: AuditPayload::fromAllowedFields(
                $after,
                ['changed_fields', 'primary_brand_color', 'has_logo'],
            ),
        ));

        return $settings->refresh();
    }

    private function replacementAsset(
        Company $company,
        User $actor,
        CompanyAppearanceData $data,
        ?CompanyAsset $currentAsset,
        ?StoredCompanyAsset &$stored,
    ): ?CompanyAsset {
        if ($data->logo === null) {
            return null;
        }

        $upload = $this->logoPolicy->inspect($data->logo);

        if ($currentAsset !== null
            && hash_equals($currentAsset->content_sha256, $upload->contentSha256)
            && $currentAsset->mime_type === $upload->mimeType
            && $currentAsset->byte_size === $upload->byteSize
            && $currentAsset->pixel_width === $upload->pixelWidth
            && $currentAsset->pixel_height === $upload->pixelHeight
        ) {
            return null;
        }

        $stored = $this->assetStorage->storeLogo($company->id, $upload);
        $asset = new CompanyAsset;
        $asset->id = $stored->id;
        $asset->fill([
            'purpose' => CompanyAssetPurpose::CompanyLogo,
            'storage_disk' => $stored->disk,
            'storage_key' => $stored->key,
            'mime_type' => $upload->mimeType,
            'byte_size' => $upload->byteSize,
            'content_sha256' => $upload->contentSha256,
            'pixel_width' => $upload->pixelWidth,
            'pixel_height' => $upload->pixelHeight,
            'created_by_user_id' => $actor->id,
        ]);
        $asset->save();

        return $asset;
    }
}
