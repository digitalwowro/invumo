<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyBrandColorPreset;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Policies\CompanyAuthorization;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyAppearancePage
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor): array
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if ($membership === null
            || ! $this->authorization->allows($membership->role, CompanyAbility::ManageCompanySettings)
        ) {
            throw new AuthorizationException;
        }

        $settings = CompanySetting::query()->with('logoAsset')->firstOrFail();

        return [
            'appearance' => [
                'primaryBrandColor' => $settings->primary_brand_color,
                'logo' => $settings->logoAsset === null ? null : [
                    'name' => __('companies_ui.settings.appearance.logo_current_name'),
                    'previewUrl' => route('company-appearance.logo', $company, false),
                ],
            ],
            'brandColorPresets' => array_map(
                fn (CompanyBrandColorPreset $preset): array => [
                    'value' => $preset->value,
                    'label' => __("companies_ui.settings.appearance.presets.{$preset->translationKey()}"),
                ],
                CompanyBrandColorPreset::cases(),
            ),
        ];
    }
}
