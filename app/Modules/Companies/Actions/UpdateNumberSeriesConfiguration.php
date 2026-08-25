<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Documents\DocumentNumberPattern;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\NumberSeriesConfigurationData;
use App\Modules\Companies\Data\NumberSeriesData;
use App\Modules\Companies\Data\NumberSeriesResetPolicy;
use App\Modules\Companies\Exceptions\NumberSeriesException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\NumberSeries;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class UpdateNumberSeriesConfiguration
{
    private const AUDIT_VALUE_FIELDS = ['padding', 'reset_policy'];

    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @return array{quote: NumberSeries, invoice: NumberSeries} */
    public function handle(
        Company $company,
        User $actor,
        NumberSeriesConfigurationData $configuration,
    ): array {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): array => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): array => $this->update($company, $actor, $configuration)),
        );
    }

    /** @return array{quote: NumberSeries, invoice: NumberSeries} */
    private function update(
        Company $company,
        User $actor,
        NumberSeriesConfigurationData $configuration,
    ): array {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $series = NumberSeries::query()
            ->whereNull('retired_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $current = $this->currentByType($series);
        $this->assertValid($configuration);

        $changed = array_values(array_filter(
            $configuration->all(),
            fn (NumberSeriesData $data): bool => $this->snapshot($current[$data->documentType->key()])
                !== $this->dataSnapshot($data),
        ));

        if ($changed === []) {
            return $current;
        }

        $timezoneFields = array_map(
            fn (NumberSeriesData $data): string => $this->timezoneField($data),
            array_values(array_filter(
                $changed,
                fn (NumberSeriesData $data): bool => DocumentNumberPattern::usesYear($data->pattern)
                    || $data->resetPolicy === NumberSeriesResetPolicy::Annual,
            )),
        );

        if ($settings->timezone === null && $timezoneFields !== []) {
            throw NumberSeriesException::timezoneRequired($timezoneFields);
        }

        $updated = $current;

        foreach ($changed as $data) {
            $key = $data->documentType->key();
            $updated[$key] = $this->replace($company, $actor, $current[$key], $data);
        }

        return ['quote' => $updated['quote'], 'invoice' => $updated['invoice']];
    }

    private function timezoneField(NumberSeriesData $data): string
    {
        $field = DocumentNumberPattern::usesYear($data->pattern)
            ? 'pattern'
            : 'reset_policy';

        return "{$data->documentType->key()}.{$field}";
    }

    private function assertValid(NumberSeriesConfigurationData $configuration): void
    {
        $invalid = [];

        foreach ($configuration->all() as $data) {
            if (
                ! DocumentNumberPattern::accepts($data->pattern)
                || $data->padding < DocumentNumberPattern::MIN_PADDING
                || $data->padding > DocumentNumberPattern::MAX_PADDING
                || ! $data->resetPolicy->acceptsPattern($data->pattern)
            ) {
                $invalid[] = "{$data->documentType->key()}.pattern";
            }
        }

        if ($invalid !== []) {
            throw NumberSeriesException::invalidConfiguration($invalid);
        }
    }

    private function replace(
        Company $company,
        User $actor,
        NumberSeries $current,
        NumberSeriesData $data,
    ): NumberSeries {
        $before = $this->snapshot($current);
        $after = $this->dataSnapshot($data);
        $changedFields = array_keys(array_filter(
            $after,
            fn (mixed $value, string $field): bool => $before[$field] !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        $current->update(['retired_at' => now()]);
        $replacement = NumberSeries::query()->create($after);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.number_series.updated',
            targetType: 'NumberSeries',
            targetId: $replacement->id,
            before: $this->auditPayload($data, $before, $changedFields),
            after: $this->auditPayload($data, $after, $changedFields),
        ));

        return $replacement;
    }

    /**
     * @param  Collection<int, NumberSeries>  $series
     * @return array{quote: NumberSeries, invoice: NumberSeries}
     */
    private function currentByType(Collection $series): array
    {
        $current = [];

        foreach ($series as $item) {
            $current[$item->document_type->key()] = $item;
        }

        if (! isset($current['quote'], $current['invoice']) || count($current) !== 2) {
            throw new LogicException('The active Company number series are incomplete.');
        }

        return ['quote' => $current['quote'], 'invoice' => $current['invoice']];
    }

    /** @return array{document_type: string, format_pattern: string, padding: int, reset_policy: string} */
    private function snapshot(NumberSeries $series): array
    {
        return [
            'document_type' => $series->document_type->value,
            'format_pattern' => $series->format_pattern,
            'padding' => $series->padding,
            'reset_policy' => $series->reset_policy->value,
        ];
    }

    /** @return array{document_type: string, format_pattern: string, padding: int, reset_policy: string} */
    private function dataSnapshot(NumberSeriesData $data): array
    {
        return [
            'document_type' => $data->documentType->value,
            'format_pattern' => $data->pattern,
            'padding' => $data->padding,
            'reset_policy' => $data->resetPolicy->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $changedFields
     */
    private function auditPayload(
        NumberSeriesData $data,
        array $values,
        array $changedFields,
    ): AuditPayload {
        $retained = array_intersect_key(
            $values,
            array_fill_keys(self::AUDIT_VALUE_FIELDS, true),
        );

        return AuditPayload::fromAllowedFields([
            'document_type' => $data->documentType->value,
            'changed_fields' => $changedFields,
            ...$retained,
        ], ['document_type', 'changed_fields', ...self::AUDIT_VALUE_FIELDS]);
    }
}
