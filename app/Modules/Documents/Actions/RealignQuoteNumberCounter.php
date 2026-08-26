<?php

namespace App\Modules\Documents\Actions;

use App\Foundation\Documents\DocumentNumberPattern;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\NumberSeriesDocumentType;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\NumberSeries;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\NumberCounterRealignmentData;
use App\Modules\Documents\Exceptions\NumberCounterException;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentNumberEvent;
use App\Modules\Documents\Models\NumberCounter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RealignQuoteNumberCounter
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $counterId,
        NumberCounterRealignmentData $data,
    ): NumberCounter {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): NumberCounter => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): NumberCounter => $this->realign($company, $actor, $counterId, $data),
            ),
        );
    }

    private function realign(
        Company $company,
        User $actor,
        string $counterId,
        NumberCounterRealignmentData $data,
    ): NumberCounter {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageNumberCounters);
        $settings = CompanySetting::query()->orderBy('id')->lockForUpdate()->firstOrFail();
        $series = NumberSeries::query()
            ->where('document_type', NumberSeriesDocumentType::Quote)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $counter = NumberCounter::query()
            ->whereKey($counterId)
            ->whereIn('number_series_id', $series->modelKeys())
            ->lockForUpdate()
            ->first();

        if (! $counter instanceof NumberCounter) {
            throw NumberCounterException::unavailable();
        }

        $counterSeries = $series->firstWhere('id', $counter->number_series_id);

        if (! $counterSeries instanceof NumberSeries || $settings->timezone === null) {
            throw NumberCounterException::unavailable();
        }

        if ($counter->next_value === $data->nextValue) {
            return $counter;
        }

        $year = $counter->period_key === 'ALL'
            ? Date::now($settings->timezone)->year
            : (int) $counter->period_key;
        $rendered = DocumentNumberPattern::render(
            $counterSeries->format_pattern,
            $counterSeries->padding,
            $data->nextValue,
            $year,
        );
        $hasHistory = Document::query()
            ->where('kind', DocumentKind::Quote)
            ->where('rendered_number', $rendered)
            ->exists() || DocumentNumberEvent::query()
            ->where('document_kind', DocumentKind::Quote)
            ->where('rendered_number', $rendered)
            ->exists();
        $movesBackward = $data->nextValue < $counter->next_value;

        if (($movesBackward || $hasHistory) && ! $data->confirmedReuse) {
            throw NumberCounterException::confirmationRequired();
        }

        $previous = $counter->next_value;
        $counter->update(['next_value' => $data->nextValue]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.quote_number_counter.realigned',
            targetType: 'NumberCounter',
            targetId: $counter->id,
            reason: $data->reason,
            before: AuditPayload::fromAllowedFields([
                'next_value' => $previous,
            ], ['next_value']),
            after: AuditPayload::fromAllowedFields([
                'next_value' => $data->nextValue,
                'reuse_warning' => $movesBackward || $hasHistory,
            ], ['next_value', 'reuse_warning']),
        ));

        return $counter->refresh();
    }
}
