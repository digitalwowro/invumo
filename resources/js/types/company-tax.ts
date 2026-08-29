import type { DependencyGuard } from '@/types/dependency-guard';

export type TaxPreset = {
    id: string;
    name: string;
    percentage: string;
    isDefault: boolean;
    archived: boolean;
    updateUrl: string | null;
    archiveUrl: string | null;
    restoreUrl: string | null;
    deleteUrl: string;
    archiveGuard: DependencyGuard;
    deleteGuard: DependencyGuard;
};

export type CompanyTaxPresetTranslations = {
    head_title: string;
    title: string;
    description: string;
    create_title: string;
    create_description: string;
    list_title: string;
    list_description: string;
    name_column: string;
    percentage_column: string;
    default_column: string;
    status_column: string;
    actions_column: string;
    default: string;
    not_default: string;
    active: string;
    archived: string;
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
    archive_dependency_description: string;
    delete_dependency_description: string;
    empty_title: string;
    empty_description: string;
    unsaved_warning: string;
    fields: Record<'name' | 'percentage' | 'is_default', string>;
    field_descriptions: Record<'percentage' | 'is_default', string>;
    feedback: Record<
        'created' | 'updated' | 'archived' | 'restored' | 'deleted',
        string
    >;
    errors: Record<
        | 'percentage_invalid'
        | 'archived'
        | 'not_archived'
        | 'default_dependency'
        | 'dependencies',
        string
    >;
};
