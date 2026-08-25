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
    confirm_archive: 'Archive tax preset',
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
    },
    errors: {
        percentage_invalid: 'Invalid.',
        archived: 'Archived.',
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
                    },
                    {
                        id: 'archived',
                        name: 'Old VAT',
                        percentage: '18',
                        isDefault: false,
                        archived: true,
                        updateUrl: null,
                        archiveUrl: null,
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('region', { name: 'Saved tax presets' }),
        ).toHaveClass('max-w-full', 'overflow-x-auto');
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
