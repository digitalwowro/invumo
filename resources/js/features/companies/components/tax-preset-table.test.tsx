import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { TaxPresetTable } from '@/features/companies/components/tax-preset-table';
import type { CompanyTaxPresetTranslations } from '@/types/company-tax';

vi.mock('@inertiajs/react', () => ({
    router: { patch: vi.fn() },
}));

const labels: CompanyTaxPresetTranslations = {
    head_title: 'Tax presets',
    title: 'Tax presets',
    description: 'Description',
    create_title: 'Add a tax preset',
    create_description: 'Create description',
    list_title: 'Saved tax presets',
    list_description: 'List description',
    name_column: 'Name',
    percentage_column: 'Percentage',
    default_column: 'Default',
    status_column: 'Status',
    actions_column: 'Actions',
    default: 'Default',
    not_default: 'Not default',
    active: 'Active',
    archived: 'Archived',
    create: 'Add tax preset',
    edit: 'Edit',
    edit_title: 'Edit tax preset',
    edit_description: 'Edit description',
    save: 'Save tax preset',
    archive: 'Archive',
    archive_title: 'Archive tax preset?',
    archive_description: 'Archive :name.',
    confirm_archive: 'Archive',
    restore: 'Restore',
    restore_title: 'Restore tax preset?',
    restore_description: 'Restore :name.',
    confirm_restore: 'Restore',
    delete: 'Delete',
    delete_title: 'Delete tax preset?',
    delete_description: 'Delete :name.',
    confirm_delete: 'Delete',
    dependency_warning_title: 'Blocked',
    archive_dependency_description: 'Archive blocked.',
    delete_dependency_description: 'Delete blocked.',
    empty_title: 'No tax presets yet',
    empty_description: 'Add one.',
    unsaved_warning: 'Leave?',
    fields: {
        name: 'Tax name',
        percentage: 'Percentage',
        is_default: 'Use as default',
    },
    field_descriptions: {
        percentage: 'Up to six decimals.',
        is_default: 'Future default.',
    },
    feedback: {
        created: 'Created.',
        updated: 'Updated.',
        archived: 'Archived.',
        restored: 'Restored.',
        deleted: 'Deleted.',
    },
    errors: {
        percentage_invalid: 'Invalid.',
        archived: 'Archived.',
        not_archived: 'Active.',
        default_dependency: 'Referenced.',
        dependencies: 'Referenced.',
    },
};

describe('TaxPresetTable', () => {
    it('contains exact values and exposes actions only for active presets', () => {
        render(
            <TaxPresetTable
                labels={labels}
                cancelLabel="Cancel"
                closeLabel="Close"
                presets={[
                    {
                        id: 'active',
                        name: 'Standard VAT',
                        percentage: '19.125',
                        isDefault: true,
                        archived: false,
                        updateUrl: '/taxes/active',
                        archiveUrl: '/taxes/active/archive',
                        restoreUrl: null,
                        deleteUrl: '/taxes/active',
                        archiveGuard: { blocked: false, description: null },
                        deleteGuard: { blocked: false, description: null },
                    },
                    {
                        id: 'archived',
                        name: 'Old VAT',
                        percentage: '18',
                        isDefault: false,
                        archived: true,
                        updateUrl: null,
                        archiveUrl: null,
                        restoreUrl: '/taxes/archived/restore',
                        deleteUrl: '/taxes/archived',
                        archiveGuard: { blocked: false, description: null },
                        deleteGuard: { blocked: false, description: null },
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('region', { name: 'Saved tax presets' }),
        ).toHaveClass('max-w-full', 'overflow-x-auto');
        expect(
            screen.getByRole('table', { name: 'Saved tax presets' }),
        ).toHaveClass('table-auto');
        expect(
            screen.getByRole('columnheader', { name: 'Name' }),
        ).not.toHaveClass('w-px');
        expect(
            screen.getByRole('columnheader', { name: 'Actions' }),
        ).toHaveClass('w-px', 'whitespace-nowrap');
        expect(screen.getByText('19.125%')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Edit' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Archive' }),
        ).toBeInTheDocument();
        expect(screen.getAllByText('Archived')).toHaveLength(1);
    });

    it('renders the localized empty state', () => {
        render(
            <TaxPresetTable
                presets={[]}
                labels={labels}
                cancelLabel="Cancel"
                closeLabel="Close"
            />,
        );

        expect(screen.getByText('No tax presets yet')).toBeInTheDocument();
        expect(screen.getByText('Add one.')).toBeInTheDocument();
    });
});
