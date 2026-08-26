<?php

namespace App\Modules\Documents\Numbering;

use App\Foundation\Documents\DocumentNumberPattern;
use App\Modules\Companies\Data\NumberSeriesDocumentType;
use App\Modules\Companies\Data\NumberSeriesResetPolicy;
use App\Modules\Companies\Models\NumberSeries;
use App\Modules\Documents\Contracts\AllocatesDocumentNumbers;
use App\Modules\Documents\Data\AllocatedDocumentNumber;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\DocumentNumberAllocationException;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\NumberCounter;
use Illuminate\Support\Str;

final readonly class LockedDocumentNumberAllocator implements AllocatesDocumentNumbers
{
    public function next(DocumentKind $kind, int $companyLocalYear): AllocatedDocumentNumber
    {
        $series = NumberSeries::query()
            ->where('document_type', $this->seriesType($kind))
            ->whereNull('retired_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $series instanceof NumberSeries) {
            throw DocumentNumberAllocationException::seriesUnavailable();
        }

        $periodKey = $series->reset_policy === NumberSeriesResetPolicy::Annual
            ? sprintf('%04d', $companyLocalYear)
            : 'ALL';

        NumberCounter::query()->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'company_id' => $series->company_id,
            'number_series_id' => $series->id,
            'period_key' => $periodKey,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = NumberCounter::query()
            ->where('number_series_id', $series->id)
            ->where('period_key', $periodKey)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence = $counter->next_value;

        while (true) {
            $rendered = DocumentNumberPattern::render(
                $series->format_pattern,
                $series->padding,
                $sequence,
                $companyLocalYear,
            );

            if (! Document::query()->where('kind', $kind)->where('rendered_number', $rendered)->exists()) {
                break;
            }

            if ($sequence === PHP_INT_MAX) {
                throw DocumentNumberAllocationException::exhausted();
            }

            $sequence++;
        }

        if ($sequence === PHP_INT_MAX) {
            throw DocumentNumberAllocationException::exhausted();
        }

        $counter->update(['next_value' => $sequence + 1]);

        return new AllocatedDocumentNumber($rendered, $series->id, $periodKey, $sequence);
    }

    private function seriesType(DocumentKind $kind): NumberSeriesDocumentType
    {
        return match ($kind) {
            DocumentKind::Quote => NumberSeriesDocumentType::Quote,
            DocumentKind::Invoice => NumberSeriesDocumentType::Invoice,
        };
    }
}
