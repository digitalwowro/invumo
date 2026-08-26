<?php

namespace Tests\Unit\Modules\Invoices;

use App\Modules\Invoices\Data\InvoiceDisplayStatus;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Data\InvoicePaymentState;
use App\Modules\Invoices\Data\ResolvedInvoiceState;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResolveInvoiceStateTest extends TestCase
{
    #[DataProvider('states')]
    public function test_it_derives_payment_and_overdue_state(
        InvoiceLifecycle $lifecycle,
        string $total,
        ?string $dueDate,
        ?InvoicePaymentState $paymentState,
        bool $isOverdue,
        InvoiceDisplayStatus $displayStatus,
    ): void {
        $resolved = ResolvedInvoiceState::resolve(
            $lifecycle,
            $total,
            $dueDate === null ? null : new CarbonImmutable($dueDate),
            new CarbonImmutable('2026-08-26'),
        );

        self::assertSame($paymentState, $resolved->paymentState);
        self::assertSame($isOverdue, $resolved->isOverdue);
        self::assertSame($displayStatus, $resolved->displayStatus);
    }

    /** @return iterable<string, array{InvoiceLifecycle, string, ?string, ?InvoicePaymentState, bool, InvoiceDisplayStatus}> */
    public static function states(): iterable
    {
        yield 'Draft ignores payment and due date' => [
            InvoiceLifecycle::Draft, '100', '2026-08-25', null, false,
            InvoiceDisplayStatus::Draft,
        ];
        yield 'Issued zero total is Paid even after due date' => [
            InvoiceLifecycle::Issued, '0.00000000', '2026-08-25',
            InvoicePaymentState::Paid, false, InvoiceDisplayStatus::Paid,
        ];
        yield 'Issued outstanding total is Overdue after due date' => [
            InvoiceLifecycle::Issued, '10.00', '2026-08-25',
            InvoicePaymentState::Unpaid, true, InvoiceDisplayStatus::Overdue,
        ];
        yield 'Issued outstanding total is not Overdue on due date' => [
            InvoiceLifecycle::Issued, '10.00', '2026-08-26',
            InvoicePaymentState::Unpaid, false, InvoiceDisplayStatus::Issued,
        ];
    }
}
