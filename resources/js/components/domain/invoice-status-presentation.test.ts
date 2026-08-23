import { describe, expect, it } from 'vitest';
import { resolveInvoiceDisplayStatuses } from '@/components/domain/invoice-status-presentation';

describe('resolveInvoiceDisplayStatuses', () => {
    it.each([
        ['draft', 'unpaid', false, ['draft']],
        ['cancelled', 'partial', true, ['cancelled']],
        ['issued', 'paid', true, ['paid']],
        ['issued', 'partial', false, ['partial']],
        ['issued', 'partial', true, ['partial', 'overdue']],
        ['issued', 'unpaid', false, ['issued']],
        ['issued', 'unpaid', true, ['overdue']],
    ] as const)(
        'maps %s/%s overdue=%s to the approved presentation',
        (lifecycle, payment, overdue, expected) => {
            expect(
                resolveInvoiceDisplayStatuses({
                    lifecycle,
                    payment,
                    overdue,
                }),
            ).toEqual(expected);
        },
    );
});
