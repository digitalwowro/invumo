<?php

namespace App\Modules\Transactions\Actions;

use App\Foundation\Money\DecimalRules;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Transactions\Data\InvoiceAdjustmentDirection;
use App\Modules\Transactions\Data\InvoiceTransactionData;
use App\Modules\Transactions\Data\InvoiceTransactionFieldLimits;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use App\Modules\Transactions\Data\LockedInvoiceTransactions;
use App\Modules\Transactions\Exceptions\InvoiceTransactionException;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ValidateInvoiceTransactionMutation
{
    public function handle(
        InvoiceTransactionData $data,
        LockedInvoiceTransactions $context,
    ): BigDecimal {
        if (! $data->confirmed) {
            throw InvoiceTransactionException::confirmationRequired();
        }

        if ($context->invoice->lifecycle !== InvoiceLifecycle::Issued
            || $context->document->currency_code === null
            || $context->document->currency_precision === null) {
            throw InvoiceTransactionException::invoiceUnavailable();
        }

        if (($data->kind === InvoiceTransactionKind::Adjustment)
            !== ($data->adjustmentDirection instanceof InvoiceAdjustmentDirection)) {
            throw InvoiceTransactionException::amountInvalid();
        }

        if (($data->kind === InvoiceTransactionKind::Adjustment)
            !== ($data->adjustmentReason !== null)) {
            throw InvoiceTransactionException::amountInvalid();
        }

        $this->assertText($data->paymentMethod, InvoiceTransactionFieldLimits::PAYMENT_METHOD);
        $this->assertText($data->reference, InvoiceTransactionFieldLimits::REFERENCE);
        $this->assertText($data->notes, InvoiceTransactionFieldLimits::NOTES);
        $this->assertText($data->adjustmentReason, InvoiceTransactionFieldLimits::ADJUSTMENT_REASON);

        try {
            $amount = DecimalRules::storedMoney(
                $data->amount,
                $context->document->currency_precision,
            );
        } catch (InvalidArgumentException) {
            throw InvoiceTransactionException::amountInvalid();
        }

        if ($amount->isZero()) {
            throw InvoiceTransactionException::amountInvalid();
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $data->transactionDate, 'UTC');

        if (! $date instanceof CarbonImmutable
            || $date->format('Y-m-d') !== $data->transactionDate
            || $data->transactionDate > $context->companyLocalDate->toDateString()) {
            throw InvoiceTransactionException::futureDate();
        }

        return $amount;
    }

    private function assertText(?string $value, int $maximum): void
    {
        if ($value !== null && (
            trim($value) !== $value
            || mb_strlen($value) < 1
            || mb_strlen($value) > $maximum
        )) {
            throw InvoiceTransactionException::amountInvalid();
        }
    }
}
