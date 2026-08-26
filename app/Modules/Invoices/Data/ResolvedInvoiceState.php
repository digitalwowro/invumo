<?php

namespace App\Modules\Invoices\Data;

use App\Foundation\Money\DecimalRules;
use Carbon\CarbonImmutable;

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
        ?CarbonImmutable $dueDate,
        CarbonImmutable $companyLocalDate,
    ): self {
        if ($lifecycle === InvoiceLifecycle::Draft) {
            return new self(null, false, InvoiceDisplayStatus::Draft);
        }

        $paid = DecimalRules::moneySource($total)->isZero();
        $overdue = ! $paid && $dueDate !== null && $dueDate->isBefore($companyLocalDate);

        return new self(
            $paid ? InvoicePaymentState::Paid : InvoicePaymentState::Unpaid,
            $overdue,
            match (true) {
                $paid => InvoiceDisplayStatus::Paid,
                $overdue => InvoiceDisplayStatus::Overdue,
                default => InvoiceDisplayStatus::Issued,
            },
        );
    }
}
