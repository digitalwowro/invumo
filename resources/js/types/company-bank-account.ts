import type { DependencyGuard } from '@/types/dependency-guard';

export type BankRoutingField =
    | 'routing_number'
    | 'sort_code'
    | 'bank_code'
    | 'branch_code'
    | 'transit_number'
    | 'institution_number'
    | 'bsb'
    | 'ifsc';

export type BankAccount = {
    id: string;
    label: string;
    bankName: string;
    accountHolder: string;
    accountNumber: string;
    swiftBic: string | null;
    currencyId: string | null;
    currencyCode: string | null;
    localRoutingDetails: Partial<Record<BankRoutingField, string>>;
    isDefault: boolean;
    archived: boolean;
    updateUrl: string | null;
    archiveUrl: string | null;
    restoreUrl: string | null;
    deleteUrl: string;
    deleteGuard: DependencyGuard;
};

export type BankAccountFormData = {
    label: string;
    bank_name: string;
    account_holder: string;
    account_number: string;
    swift_bic: string;
    currency_id: string;
    local_routing_details: Record<BankRoutingField, string>;
    is_default: boolean;
};

export type CompanyBankAccountTranslations = {
    head_title: string;
    title: string;
    description: string;
    create_title: string;
    create_description: string;
    routing_title: string;
    routing_description: string;
    list_title: string;
    list_description: string;
    label_column: string;
    bank_column: string;
    account_column: string;
    currency_column: string;
    default_column: string;
    status_column: string;
    actions_column: string;
    default: string;
    not_default: string;
    active: string;
    archived: string;
    no_currency: string;
    create: string;
    edit: string;
    edit_title: string;
    edit_description: string;
    save: string;
    archive: string;
    archive_title: string;
    archive_description: string;
    confirm_archive: string;
    restore: string;
    restore_title: string;
    restore_description: string;
    confirm_restore: string;
    delete: string;
    delete_title: string;
    delete_description: string;
    confirm_delete: string;
    dependency_warning_title: string;
    delete_dependency_description: string;
    empty_title: string;
    empty_description: string;
    unsaved_warning: string;
    copy_value: string;
    value_copied: string;
    fields: Record<
        | 'label'
        | 'bank_name'
        | 'account_holder'
        | 'account_number'
        | 'swift_bic'
        | 'currency_id'
        | 'is_default',
        string
    >;
    field_descriptions: Record<
        'swift_bic' | 'currency_id' | 'is_default',
        string
    >;
    routing_fields: Record<BankRoutingField, string>;
    feedback: Record<
        'created' | 'updated' | 'archived' | 'restored' | 'deleted',
        string
    >;
    errors: Record<
        | 'archived'
        | 'not_archived'
        | 'currency_unavailable'
        | 'routing_details_invalid'
        | 'dependencies',
        string
    >;
};
