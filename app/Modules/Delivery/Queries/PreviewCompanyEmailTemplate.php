<?php

namespace App\Modules\Delivery\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Delivery\Data\CompanyEmailTemplateData;
use App\Modules\Delivery\Data\RenderedEmailTemplate;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class PreviewCompanyEmailTemplate
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private SampleEmailTemplatePreview $preview,
    ) {}

    public function for(
        Company $company,
        User $actor,
        CompanyEmailTemplateData $template,
    ): RenderedEmailTemplate {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ManageCompanySettings)) {
            throw new AuthorizationException;
        }

        return $this->preview->for($template);
    }
}
