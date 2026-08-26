<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Money\DecimalRules;
use App\Foundation\Money\DocumentCalculator;
use App\Foundation\Money\LineAmounts;
use App\Foundation\Money\LineCalculationInput;
use App\Foundation\Money\LineCalculator;
use App\Foundation\Money\PeriodUnit;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Quotes\Data\QuoteDraftData;
use App\Modules\Quotes\Exceptions\QuoteDraftException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class UpdateQuoteDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LineCalculator $lineCalculator,
        private DocumentCalculator $documentCalculator,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $documentId, QuoteDraftData $data): Document
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->update($company, $actor, $documentId, $data),
                3,
            ),
        );
    }

    private function update(Company $company, User $actor, string $documentId, QuoteDraftData $data): Document
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageQuotes);
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Quote)
            ->lockForUpdate()
            ->firstOrFail();

        if ($document->edit_version !== $data->editVersion) {
            throw QuoteDraftException::stale();
        }

        /** @var Collection<int, DocumentLine> $persisted */
        $persisted = DocumentLine::query()
            ->where('document_id', $document->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $submittedIds = array_values(array_filter(array_map(
            fn (DocumentLineData $line): ?string => $line->id,
            $data->lines,
        )));

        if (count($submittedIds) !== count(array_unique($submittedIds))
            || array_diff($submittedIds, $persisted->modelKeys()) !== []) {
            throw QuoteDraftException::lineSetInvalid();
        }

        $connection = DB::connection(config('database.tenant_connection'));
        $connection->statement('SET CONSTRAINTS document_lines_company_document_position_unique DEFERRED');
        $amounts = [];
        $retainedIds = [];

        foreach ($data->lines as $index => $lineData) {
            $line = $lineData->id === null
                ? new DocumentLine
                : $persisted->firstWhere('id', $lineData->id);

            if (! $line instanceof DocumentLine) {
                throw QuoteDraftException::lineSetInvalid();
            }

            [$attributes, $calculation] = $this->lineAttributes($lineData, $document->currency_precision);
            $line->fill(['document_id' => $document->id, 'position' => $index + 1, ...$attributes]);
            $line->save();
            $retainedIds[] = $line->id;

            if ($calculation instanceof LineAmounts) {
                $amounts[] = $calculation;
            }
        }

        DocumentLine::query()
            ->where('document_id', $document->id)
            ->whereNotIn('id', $retainedIds)
            ->delete();

        $connection->statement('SET CONSTRAINTS document_lines_company_document_position_unique IMMEDIATE');
        $totals = $document->currency_precision === null
            ? ['grand_subtotal' => '0', 'tax_amount' => '0', 'final_total' => '0']
            : $this->documentCalculator->calculate($amounts, $document->currency_precision)->toArray();

        $document->update([
            'subtotal' => $totals['grand_subtotal'],
            'tax_total' => $totals['tax_amount'],
            'total' => $totals['final_total'],
            'edit_version' => $document->edit_version + 1,
            'content_version' => $document->content_version + 1,
        ]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.quote.draft_updated',
            targetType: 'Quote',
            targetId: $document->id,
            after: AuditPayload::fromAllowedFields([
                'line_count' => count($data->lines),
                'complete_line_count' => count($amounts),
                'edit_version' => $document->edit_version,
            ], ['line_count', 'complete_line_count', 'edit_version']),
        ));

        return $document->refresh();
    }

    /** @return array{array<string, mixed>, LineAmounts|null} */
    private function lineAttributes(DocumentLineData $data, ?int $precision): array
    {
        try {
            if ($data->itemPrice !== null) {
                DecimalRules::moneySource($data->itemPrice);
            }
            if ($data->quantity !== null) {
                DecimalRules::quantity($data->quantity);
            }
            if ($data->periodQuantity !== null) {
                DecimalRules::quantity($data->periodQuantity);
            }
            DecimalRules::percentage($data->discountPercentage, true);
            DecimalRules::percentage($data->taxPercentage);

            if (($data->periodUnit === PeriodUnit::None && $data->periodQuantity !== null)
                || ! $this->validText($data->description, DocumentFieldLimits::DESCRIPTION, false)
                || ! $this->validText($data->unit, DocumentFieldLimits::UNIT)
                || ! $this->validText($data->taxName, DocumentFieldLimits::TAX_NAME)) {
                throw new InvalidArgumentException;
            }
        } catch (InvalidArgumentException) {
            throw QuoteDraftException::lineInvalid();
        }

        $complete = $precision !== null
            && $data->itemPrice !== null
            && $data->quantity !== null
            && ($data->periodUnit === PeriodUnit::None || $data->periodQuantity !== null);

        $calculation = $complete ? $this->lineCalculator->calculate(new LineCalculationInput(
            unitPrice: (string) $data->itemPrice,
            quantity: (string) $data->quantity,
            periodUnit: $data->periodUnit,
            periodQuantity: $data->periodQuantity,
            discountPercentage: $data->discountPercentage,
            taxPercentage: $data->taxPercentage,
            currencyPrecision: $precision,
        )) : null;

        return [[
            'description' => $data->description,
            'item_price' => $data->itemPrice,
            'quantity' => $data->quantity,
            'unit' => $data->unit,
            'period_unit' => $data->periodUnit,
            'period_quantity' => $data->periodQuantity,
            'discount_percentage' => $data->discountPercentage,
            'tax_name' => $data->taxName,
            'tax_percentage' => $data->taxPercentage,
            ...$this->nullableAmounts($calculation),
        ], $calculation];
    }

    /** @return array<string, string|null> */
    private function nullableAmounts(?LineAmounts $amounts): array
    {
        return $amounts?->toArray() ?? [
            'items_subtotal' => null,
            'items_total' => null,
            'discount_amount' => null,
            'grand_subtotal' => null,
            'tax_amount' => null,
            'final_line_total' => null,
        ];
    }

    private function validText(?string $value, int $maximum, bool $trimmed = true): bool
    {
        return $value === null || (
            mb_strlen($value) >= 1
            && mb_strlen($value) <= $maximum
            && (! $trimmed || trim($value) === $value)
        );
    }
}
