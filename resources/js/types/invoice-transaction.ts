export type InvoiceTransactionKind = 'PAYMENT' | 'REFUND' | 'ADJUSTMENT';
export type InvoiceAdjustmentDirection = 'INCREASE_PAID' | 'DECREASE_PAID';

export type InvoiceTransactionRow = {
    id: string;
    kind: InvoiceTransactionKind;
    adjustmentDirection: InvoiceAdjustmentDirection | null;
    amount: string;
    currencyCode: string;
    transactionDate: string;
    paymentMethod: string | null;
    reference: string | null;
    notes: string | null;
    adjustmentReason: string | null;
    editVersion: number;
    updateUrl: string | null;
    deleteUrl: string | null;
};

export type InvoiceTransactions = {
    summary: {
        invoiceTotal: string;
        netPaid: string;
        outstanding: string;
        refundableCash: string;
    };
    items: InvoiceTransactionRow[];
    storeUrl: string | null;
    abilities: { manage: boolean; adjust: boolean };
    actions: { payment: boolean; refund: boolean; adjustment: boolean };
    today: string;
    limits: {
        paymentMethod: number;
        reference: number;
        notes: number;
        adjustmentReason: number;
    };
};

export type InvoiceTransactionTranslations = {
    title: string;
    description: string;
    draft_notice: string;
    cancelled_notice: string;
    unsaved_notice: string;
    balance_notice: string;
    summary: Record<
        'invoice_total' | 'net_paid' | 'outstanding' | 'refundable_cash',
        string
    >;
    add_payment: string;
    add_refund: string;
    add_adjustment: string;
    create_title: string;
    create_description: string;
    edit: string;
    edit_title: string;
    edit_description: string;
    delete: string;
    delete_title: string;
    delete_description: string;
    save: string;
    confirm_delete: string;
    cancel: string;
    close: string;
    empty_title: string;
    empty_description: string;
    columns: Record<'type' | 'date' | 'amount' | 'details' | 'actions', string>;
    fields: Record<
        | 'kind'
        | 'adjustment_direction'
        | 'amount'
        | 'transaction_date'
        | 'payment_method'
        | 'reference'
        | 'notes'
        | 'adjustment_reason',
        string
    >;
    kinds: Record<InvoiceTransactionKind, string>;
    directions: Record<InvoiceAdjustmentDirection, string>;
    not_available: string;
    loading: string;
    no_results_title: string;
    no_results_description: string;
    error_title: string;
    error_description: string;
};
