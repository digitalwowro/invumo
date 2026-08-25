import { describe, expect, it } from 'vitest';
import {
    bankAccountFormData,
    emptyBankAccountFormData,
} from '@/features/companies/components/bank-account-form-data';
import type { BankRoutingField } from '@/types/company-bank-account';

const routingFields: BankRoutingField[] = [
    'routing_number',
    'sort_code',
    'bank_code',
    'branch_code',
    'transit_number',
    'institution_number',
    'bsb',
    'ifsc',
];

describe('bank account form data', () => {
    it('creates a complete empty allowlisted routing object', () => {
        const data = emptyBankAccountFormData(routingFields);

        expect(Object.keys(data.local_routing_details)).toEqual(routingFields);
        expect(Object.values(data.local_routing_details)).toEqual(
            Array.from({ length: 8 }, () => ''),
        );
    });

    it('maps persisted values without admitting unknown routing fields', () => {
        const data = bankAccountFormData(
            {
                id: 'bank',
                label: 'Main',
                bankName: 'Bank',
                accountHolder: 'Holder',
                accountNumber: 'ACCOUNT',
                swiftBic: 'AAAAROBUXXX',
                currencyId: null,
                currencyCode: null,
                localRoutingDetails: { routing_number: '123' },
                isDefault: true,
                archived: false,
                updateUrl: '/bank',
                archiveUrl: '/bank/archive',
            },
            routingFields,
        );

        expect(data.local_routing_details.routing_number).toBe('123');
        expect(data.local_routing_details.ifsc).toBe('');
        expect(data.is_default).toBe(true);
    });
});
