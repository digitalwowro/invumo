<?php

namespace App\Modules\Invoices\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Transactions\Data\InvoiceLedger;
use Brick\Math\BigDecimal;

final readonly class InvoiceLifecycleActionsForInvoice
{
    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function props(
        Company $company,
        User $actor,
        string $invoiceId,
        InvoiceLifecycle $lifecycle,
        InvoiceLedger $ledger,
        int $currencyPrecision,
        ?string $currencyCode,
    ): array {
        $canManage = $this->abilities->allows($actor, $company, CompanyAbility::ManageInvoices);
        $canAdjust = $this->abilities->allows($actor, $company, CompanyAbility::ManageAdjustments);
        $netPaid = $ledger->netPaid();
        $refundable = $this->minimum($ledger->refundableCash(), $netPaid);
        $adjustment = $netPaid->minus($refundable);
        $state = $this->state($netPaid, $refundable, $adjustment, $canAdjust);
        $description = $state === 'OWNER_ADMIN_REQUIRED' && $refundable->isZero()
            ? 'description_no_refund'
            : 'description';
        $replacements = [
            'refund' => $this->amount($refundable, $currencyPrecision, $currencyCode),
            'adjustment' => $this->amount($adjustment, $currencyPrecision, $currencyCode),
        ];

        return [
            'cancelUrl' => $canManage && $lifecycle === InvoiceLifecycle::Issued
                ? route('invoices.cancel', [$company, $invoiceId], false)
                : null,
            'reopenUrl' => $canManage && $lifecycle === InvoiceLifecycle::Cancelled
                ? route('invoices.reopen', [$company, $invoiceId], false)
                : null,
            'canCancel' => $lifecycle === InvoiceLifecycle::Issued && $netPaid->isZero(),
            'state' => $state,
            'stateTitle' => __("invoices_ui.lifecycle.states.{$state}.title"),
            'stateDescription' => __("invoices_ui.lifecycle.states.{$state}.{$description}", $replacements),
        ];
    }

    private function state(
        BigDecimal $netPaid,
        BigDecimal $refundable,
        BigDecimal $adjustment,
        bool $canAdjust,
    ): string {
        if ($netPaid->isZero()) {
            return 'READY';
        }

        if ($adjustment->isZero()) {
            return 'REFUND_REQUIRED';
        }

        if (! $canAdjust) {
            return 'OWNER_ADMIN_REQUIRED';
        }

        return $refundable->isZero() ? 'ADJUSTMENT_REQUIRED' : 'REFUND_AND_ADJUSTMENT_REQUIRED';
    }

    private function minimum(BigDecimal $left, BigDecimal $right): BigDecimal
    {
        return $left->compareTo($right) <= 0 ? $left : $right;
    }

    private function amount(BigDecimal $amount, int $precision, ?string $currency): string
    {
        $value = (string) DecimalRules::exactMoney($amount, $precision);

        return trim($value.' '.($currency ?? ''));
    }
}
