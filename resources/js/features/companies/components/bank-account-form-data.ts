import type {
    BankAccount,
    BankAccountFormData,
    BankRoutingField,
} from '@/types/company-bank-account';

function routingValues(
    fields: BankRoutingField[],
    values: Partial<Record<BankRoutingField, string>> = {},
) {
    return Object.fromEntries(
        fields.map((field) => [field, values[field] ?? '']),
    ) as Record<BankRoutingField, string>;
}

export function emptyBankAccountFormData(
    fields: BankRoutingField[],
): BankAccountFormData {
    return {
        label: '',
        bank_name: '',
        account_holder: '',
        account_number: '',
        swift_bic: '',
        currency_id: '',
        local_routing_details: routingValues(fields),
        is_default: false,
    };
}

export function bankAccountFormData(
    account: BankAccount,
    fields: BankRoutingField[],
): BankAccountFormData {
    return {
        label: account.label,
        bank_name: account.bankName,
        account_holder: account.accountHolder,
        account_number: account.accountNumber,
        swift_bic: account.swiftBic,
        currency_id: account.currencyId ?? '',
        local_routing_details: routingValues(
            fields,
            account.localRoutingDetails,
        ),
        is_default: account.isDefault,
    };
}
