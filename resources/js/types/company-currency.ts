import type { DependencyGuard } from '@/types/dependency-guard';

export type CompanyCurrency = {
    id: string;
    code: string;
    precision: number;
    isDefault: boolean;
    active: boolean;
    updateUrl: string | null;
    defaultUrl: string | null;
    deactivateUrl: string | null;
    restoreUrl: string | null;
    deactivateGuard: DependencyGuard;
};

export type CompanyCurrencyTranslations = {
    head_title: string;
    title: string;
    description: string;
    create_title: string;
    create_description: string;
    list_title: string;
    list_description: string;
    code_column: string;
    precision_column: string;
    default_column: string;
    status_column: string;
    actions_column: string;
    default: string;
    not_default: string;
    active: string;
    inactive: string;
    add: string;
    edit: string;
    edit_title: string;
    edit_description: string;
    save: string;
    set_default: string;
    set_default_title: string;
    set_default_description: string;
    confirm_default: string;
    deactivate: string;
    deactivate_title: string;
    deactivate_description: string;
    confirm_deactivate: string;
    restore: string;
    restore_title: string;
    restore_description: string;
    confirm_restore: string;
    dependency_warning_title: string;
    default_dependency_description: string;
    source_dependency_description: string;
    empty_title: string;
    empty_description: string;
    no_options: string;
    code_placeholder: string;
    unsaved_warning: string;
    fields: Record<
        'currency_code' | 'currency_precision' | 'is_default',
        string
    >;
    field_descriptions: Record<'currency_precision' | 'is_default', string>;
    feedback: Record<
        'created' | 'updated' | 'defaulted' | 'deactivated' | 'restored',
        string
    >;
    errors: Record<
        | 'duplicate'
        | 'inactive'
        | 'active'
        | 'default_dependency'
        | 'source_dependencies'
        | 'precision_dependency',
        string
    >;
};
