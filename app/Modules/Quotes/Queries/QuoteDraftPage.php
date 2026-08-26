<?php

namespace App\Modules\Quotes\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

final readonly class QuoteDraftPage
{
    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function create(Company $company, User $actor): array
    {
        $this->authorize($company, $actor);

        return [
            'storeUrl' => route('quotes.store', $company, false),
            'creationKey' => (string) Str::uuid7(),
        ];
    }

    /** @return array<string, mixed> */
    public function edit(Company $company, User $actor, string $documentId): array
    {
        $this->authorize($company, $actor);
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Quote)
            ->firstOrFail();
        $lines = DocumentLine::query()
            ->where('document_id', $document->id)
            ->orderBy('position')
            ->get();

        return [
            'quote' => [
                'id' => $document->id,
                'number' => $document->rendered_number,
                'issueDate' => $document->issue_date?->toDateString(),
                'currencyCode' => $document->currency_code,
                'currencyPrecision' => $document->currency_precision,
                'editVersion' => $document->edit_version,
                'subtotal' => $this->money($document->subtotal, $document->currency_precision),
                'taxTotal' => $this->money($document->tax_total, $document->currency_precision),
                'total' => $this->money($document->total, $document->currency_precision),
                'lines' => $lines->map(fn (DocumentLine $line): array => [
                    'id' => $line->id,
                    'description' => $line->description,
                    'itemPrice' => $line->item_price,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'periodUnit' => $line->period_unit->value,
                    'periodQuantity' => $line->period_quantity,
                    'discountPercentage' => $line->discount_percentage,
                    'taxName' => $line->tax_name,
                    'taxPercentage' => $line->tax_percentage,
                    'finalLineTotal' => $this->money($line->final_line_total, $document->currency_precision),
                ])->values(),
            ],
            'updateUrl' => route('quotes.update', [$company, $document], false),
            'limits' => [
                'description' => DocumentFieldLimits::DESCRIPTION,
                'unit' => DocumentFieldLimits::UNIT,
                'taxName' => DocumentFieldLimits::TAX_NAME,
            ],
        ];
    }

    private function authorize(Company $company, User $actor): void
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewQuotes)) {
            throw new AuthorizationException;
        }
    }

    /** @param int<0, 8>|null $precision */
    private function money(?string $value, ?int $precision): ?string
    {
        if ($value === null || $precision === null) {
            return $value;
        }

        return (string) DecimalRules::moneySource($value)->toScale($precision);
    }
}
