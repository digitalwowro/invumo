<?php

namespace Tests\Unit\Modules\Transactions;

use App\Modules\Transactions\Data\InvoiceAdjustmentDirection;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use App\Modules\Transactions\Exceptions\InvoiceTransactionException;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InvoiceLedgerTest extends TestCase
{
    /** @param list<array{InvoiceTransactionKind, InvoiceAdjustmentDirection|null, string}> $rows */
    #[DataProvider('ledgerProvider')]
    public function test_ledger_derives_exact_balances(
        array $rows,
        string $netPaid,
        string $cash,
        string $outstanding,
    ): void {
        $ledger = InvoiceLedger::fromTransactions(array_map(
            fn (array $row): InvoiceTransaction => $this->transaction(...$row),
            $rows,
        ));

        $this->assertSame($netPaid, (string) $ledger->netPaid());
        $this->assertSame($cash, (string) $ledger->refundableCash());
        $this->assertSame($outstanding, (string) $ledger->outstanding('100'));
    }

    /** @return iterable<string, array{list<array{InvoiceTransactionKind, InvoiceAdjustmentDirection|null, string}>, string, string, string}> */
    public static function ledgerProvider(): iterable
    {
        yield 'partial payment' => [
            [[InvoiceTransactionKind::Payment, null, '40']], '40', '40', '60',
        ];
        yield 'refund and non-cash adjustments' => [[
            [InvoiceTransactionKind::Payment, null, '70'],
            [InvoiceTransactionKind::Refund, null, '20'],
            [InvoiceTransactionKind::Adjustment, InvoiceAdjustmentDirection::IncreasePaid, '10'],
            [InvoiceTransactionKind::Adjustment, InvoiceAdjustmentDirection::DecreasePaid, '5'],
        ], '55', '50', '45'];
    }

    public function test_operation_limits_reject_overpayment_and_non_cash_refunds(): void
    {
        $ledger = InvoiceLedger::fromTransactions([
            $this->transaction(InvoiceTransactionKind::Payment, null, '40'),
            $this->transaction(
                InvoiceTransactionKind::Adjustment,
                InvoiceAdjustmentDirection::IncreasePaid,
                '20',
            ),
        ]);

        $ledger->assertCanApply(
            InvoiceTransactionKind::Payment,
            null,
            BigDecimal::of('40'),
            '100',
        );
        $this->expectException(InvoiceTransactionException::class);
        $ledger->assertCanApply(
            InvoiceTransactionKind::Refund,
            null,
            BigDecimal::of('41'),
            '100',
        );
    }

    private function transaction(
        InvoiceTransactionKind $kind,
        ?InvoiceAdjustmentDirection $direction,
        string $amount,
    ): InvoiceTransaction {
        $transaction = new InvoiceTransaction;
        $transaction->setAttribute('kind', $kind);
        $transaction->setAttribute('adjustment_direction', $direction);
        $transaction->setAttribute('amount', $amount);

        return $transaction;
    }
}
