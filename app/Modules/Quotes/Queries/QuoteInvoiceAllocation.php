<?php

namespace App\Modules\Quotes\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Quotes\Data\QuoteDisplayStatus;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection;

final readonly class QuoteInvoiceAllocation
{
    public function __construct(private CompanyAbilityCheck $abilities) {}

    /**
     * @param  int<0, 8>  $precision
     * @return array<string, mixed>
     */
    public function for(
        Company $company,
        User $actor,
        string $quoteId,
        string $quoteTotal,
        int $precision,
        QuoteDisplayStatus $status,
    ): array {
        $links = QuoteInvoiceLink::query()
            ->with(['invoiceDocument', 'invoice', 'invoiceDelivery'])
            ->where('quote_invoice_links.quote_id', $quoteId)
            ->whereHas('invoice', fn ($query) => $query->where('lifecycle', '!=', 'CANCELLED'))
            ->orderBy('id')
            ->get();
        $invoiced = $this->sum($links);
        $quoted = DecimalRules::moneySource($quoteTotal);
        $remaining = $quoted->minus($invoiced);
        $projected = $remaining->minus($quoted);
        $canUnlink = $this->abilities->allows($actor, $company, CompanyAbility::UnlinkQuoteInvoice);
        $canConvert = $this->abilities->allows($actor, $company, CompanyAbility::ManageQuotes)
            && $this->abilities->allows($actor, $company, CompanyAbility::ManageInvoices);
        $canOverride = $this->abilities->allows($actor, $company, CompanyAbility::OverrideQuoteConversion);

        return [
            'quoted' => (string) $quoted->toScale($precision),
            'invoiced' => (string) $invoiced->toScale($precision),
            'remaining' => (string) $remaining->toScale($precision),
            'projectedRemaining' => (string) $projected->toScale($precision),
            'willOverAllocate' => $projected->isNegative(),
            'conversionMode' => match (true) {
                ! $canConvert, $status === QuoteDisplayStatus::Rejected => 'blocked',
                $status === QuoteDisplayStatus::Accepted => 'normal',
                $canOverride => 'override',
                default => 'blocked',
            },
            'invoices' => $links->sortBy(
                fn (QuoteInvoiceLink $link): string => $link->invoiceDocument->rendered_number,
            )->map(fn (QuoteInvoiceLink $link): array => [
                'id' => $link->invoice_id,
                'number' => $link->invoiceDocument->rendered_number,
                'total' => (string) DecimalRules::moneySource($link->invoiceDocument->total)->toScale($precision),
                'lifecycle' => $link->invoice->lifecycle->value,
                'editUrl' => route('invoices.edit', [$company, $link->invoice_id], false),
                'unlinkUrl' => route('quotes.invoices.unlink', [$company, $quoteId, $link->invoice_id], false),
                'canUnlink' => $canUnlink
                    && $link->invoice->lifecycle->value === 'DRAFT'
                    && ! $link->invoiceDelivery->public_access_enabled,
            ])->values()->all(),
        ];
    }

    /** @param Collection<int, QuoteInvoiceLink> $links */
    private function sum(Collection $links): BigDecimal
    {
        $total = DecimalRules::moneySource('0');

        foreach ($links as $link) {
            $total = $total->plus(DecimalRules::moneySource($link->invoiceDocument->total));
        }

        return $total;
    }
}
