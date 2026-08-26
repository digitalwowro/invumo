<?php

namespace App\Modules\Invoices\Data;

use App\Foundation\Money\DecimalRules;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ResolvedInvoiceState
{
    public function __construct(
        public ?InvoicePaymentState $paymentState,
        public bool $isOverdue,
        public InvoiceDisplayStatus $displayStatus,
    ) {}

    public static function resolve(
        InvoiceLifecycle $lifecycle,
        string $total,
        string $netPaid,
        ?CarbonImmutable $dueDate,
        CarbonImmutable $companyLocalDate,
    ): self {
        if ($lifecycle === InvoiceLifecycle::Draft) {
            return new self(null, false, InvoiceDisplayStatus::Draft);
        }

        $invoiceTotal = DecimalRules::moneySource($total);
        $resolvedNetPaid = DecimalRules::moneySource($netPaid);

        if ($invoiceTotal->isNegative()
            || $resolvedNetPaid->isNegative()
            || $resolvedNetPaid->compareTo($invoiceTotal) > 0) {
            throw new InvalidArgumentException('Invoice payment amounts violate the complete-ledger bounds.');
        }

        $outstanding = $invoiceTotal->minus($resolvedNetPaid);
        $paymentState = match (true) {
            $invoiceTotal->isZero(), $outstanding->isZero() => InvoicePaymentState::Paid,
            $resolvedNetPaid->isZero() => InvoicePaymentState::Unpaid,
            default => InvoicePaymentState::PartiallyPaid,
        };
        $overdue = ! $outstanding->isZero()
            && $dueDate !== null
            && $dueDate->isBefore($companyLocalDate);

        return new self(
            $paymentState,
            $overdue,
            match (true) {
                $paymentState === InvoicePaymentState::Paid => InvoiceDisplayStatus::Paid,
                $overdue => InvoiceDisplayStatus::Overdue,
                $paymentState === InvoicePaymentState::PartiallyPaid => InvoiceDisplayStatus::PartiallyPaid,
                default => InvoiceDisplayStatus::Issued,
            },
        );
    }

    public static function withoutFinancialRows(
        InvoiceLifecycle $lifecycle,
        string $total,
        ?CarbonImmutable $dueDate,
        CarbonImmutable $companyLocalDate,
    ): self {
        return self::resolve($lifecycle, $total, '0', $dueDate, $companyLocalDate);
    }
}
