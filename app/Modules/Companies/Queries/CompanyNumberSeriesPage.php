<?php

namespace App\Modules\Companies\Queries;

use App\Foundation\Documents\DocumentNumberPattern;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\NumberSeriesResetPolicy;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\NumberSeries;
use App\Modules\Companies\Policies\CompanyAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

final readonly class CompanyNumberSeriesPage
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor): array
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if (
            $membership === null
            || ! $this->authorization->allows(
                $membership->role,
                CompanyAbility::ManageCompanySettings,
            )
        ) {
            throw new AuthorizationException;
        }

        $settings = CompanySetting::query()->firstOrFail();
        $year = $settings->timezone === null
            ? null
            : CarbonImmutable::now($settings->timezone)->year;
        $active = NumberSeries::query()
            ->whereNull('retired_at')
            ->orderBy('document_type')
            ->get()
            ->keyBy(fn (NumberSeries $series): string => $series->document_type->key());

        if (! $active->has(['quote', 'invoice']) || $active->count() !== 2) {
            throw new LogicException('The active Company number series are incomplete.');
        }

        return [
            'numberSeries' => [
                'quote' => $this->series($active->get('quote'), $year),
                'invoice' => $this->series($active->get('invoice'), $year),
            ],
            'numberSeriesLimits' => [
                'patternCharacters' => DocumentNumberPattern::MAX_PATTERN_CHARACTERS,
                'minimumPadding' => DocumentNumberPattern::MIN_PADDING,
                'maximumPadding' => DocumentNumberPattern::MAX_PADDING,
            ],
            'previewContext' => [
                'year' => $year,
                'sequence' => 1,
            ],
            'resetPolicyOptions' => array_map(
                fn (NumberSeriesResetPolicy $policy): array => [
                    'value' => $policy->value,
                    'label' => __("companies_ui.settings.numbering.reset_policy_options.{$policy->value}"),
                ],
                NumberSeriesResetPolicy::cases(),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function series(NumberSeries $series, ?int $year): array
    {
        return [
            'id' => $series->id,
            'pattern' => $series->format_pattern,
            'padding' => (string) $series->padding,
            'resetPolicy' => $series->reset_policy->value,
            'preview' => $this->preview($series, $year),
        ];
    }

    private function preview(NumberSeries $series, ?int $year): ?string
    {
        if (DocumentNumberPattern::usesYear($series->format_pattern) && $year === null) {
            return null;
        }

        return DocumentNumberPattern::render(
            $series->format_pattern,
            $series->padding,
            1,
            $year,
        );
    }
}
