import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { CompanyCurrencyTable } from '@/features/companies/components/company-currency-table';
import type { CompanyCurrencyTranslations } from '@/types/company-currency';

vi.mock('@inertiajs/react', () => ({
    router: { patch: vi.fn() },
}));

const labels: CompanyCurrencyTranslations = {
    head_title: 'Company currencies',
    title: 'Company currencies',
    description: 'Description',
    create_title: 'Add a currency',
    create_description: 'Create description',
    list_title: 'Configured currencies',
    list_description: 'List description',
    code_column: 'Currency',
    precision_column: 'Decimal places',
    default_column: 'Default',
    status_column: 'Status',
    actions_column: 'Actions',
    default: 'Default',
    not_default: 'Not default',
    active: 'Active',
    inactive: 'Inactive',
    add: 'Add currency',
    edit: 'Edit',
    edit_title: 'Edit currency precision',
    edit_description: 'Edit description',
    save: 'Save currency',
    set_default: 'Set as default',
    set_default_title: 'Change default?',
    set_default_description: 'Set :code as default.',
    confirm_default: 'Set as default',
    deactivate: 'Deactivate',
    deactivate_title: 'Deactivate currency?',
    deactivate_description: 'Deactivate :code.',
    confirm_deactivate: 'Deactivate',
    restore: 'Restore',
    restore_title: 'Restore currency?',
    restore_description: 'Restore :code.',
    confirm_restore: 'Restore',
    dependency_warning_title: 'This action is blocked',
    default_dependency_description: 'Choose another default.',
    source_dependency_description: 'Change dependencies.',
    empty_title: 'No currencies yet',
    empty_description: 'Add one.',
    no_options: 'No options',
    code_placeholder: 'Select a currency',
    unsaved_warning: 'Leave?',
    fields: {
        currency_code: 'Currency code',
        currency_precision: 'Decimal places',
        is_default: 'Use as default',
    },
    field_descriptions: {
        currency_precision: 'Choose zero through eight.',
        is_default: 'Future default.',
    },
    feedback: {
        created: 'Created.',
        updated: 'Updated.',
        defaulted: 'Defaulted.',
        deactivated: 'Deactivated.',
        restored: 'Restored.',
    },
    errors: {
        duplicate: 'Duplicate.',
        inactive: 'Inactive.',
        active: 'Active.',
        default_dependency: 'Default.',
        source_dependencies: 'Referenced.',
        precision_dependency: 'Precision.',
    },
};

describe('CompanyCurrencyTable', () => {
    it('shows lifecycle actions that match each currency state', () => {
        render(
            <CompanyCurrencyTable
                labels={labels}
                cancelLabel="Cancel"
                closeLabel="Close"
                currencies={[
                    {
                        id: 'ron',
                        code: 'RON',
                        precision: 2,
                        isDefault: true,
                        active: true,
                        updateUrl: '/currencies/ron',
                        defaultUrl: null,
                        deactivateUrl: '/currencies/ron/deactivate',
                        restoreUrl: null,
                        deactivateGuard: {
                            blocked: true,
                            description: 'Choose another default.',
                        },
                    },
                    {
                        id: 'eur',
                        code: 'EUR',
                        precision: 4,
                        isDefault: false,
                        active: true,
                        updateUrl: '/currencies/eur',
                        defaultUrl: '/currencies/eur/default',
                        deactivateUrl: '/currencies/eur/deactivate',
                        restoreUrl: null,
                        deactivateGuard: {
                            blocked: false,
                            description: null,
                        },
                    },
                    {
                        id: 'usd',
                        code: 'USD',
                        precision: 2,
                        isDefault: false,
                        active: false,
                        updateUrl: null,
                        defaultUrl: null,
                        deactivateUrl: null,
                        restoreUrl: '/currencies/usd/restore',
                        deactivateGuard: {
                            blocked: false,
                            description: null,
                        },
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('table', { name: 'Configured currencies' }),
        ).toBeInTheDocument();
        expect(screen.getByText('RON')).toBeInTheDocument();
        expect(screen.getByText('EUR')).toBeInTheDocument();
        expect(screen.getByText('USD')).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: 'Edit' })).toHaveLength(2);
        expect(
            screen.getByRole('button', { name: 'Set as default' }),
        ).toBeInTheDocument();
        expect(
            screen.getAllByRole('button', { name: 'Deactivate' }),
        ).toHaveLength(2);
        expect(
            screen.getByRole('button', { name: 'Restore' }),
        ).toBeInTheDocument();
    });

    it('explains why the default currency cannot be deactivated', async () => {
        const user = userEvent.setup();
        render(
            <CompanyCurrencyTable
                labels={labels}
                cancelLabel="Cancel"
                closeLabel="Close"
                currencies={[
                    {
                        id: 'ron',
                        code: 'RON',
                        precision: 2,
                        isDefault: true,
                        active: true,
                        updateUrl: '/currencies/ron',
                        defaultUrl: null,
                        deactivateUrl: '/currencies/ron/deactivate',
                        restoreUrl: null,
                        deactivateGuard: {
                            blocked: true,
                            description: 'Choose another default.',
                        },
                    },
                ]}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Deactivate' }));

        expect(screen.getByText('This action is blocked')).toBeInTheDocument();
        expect(screen.getByText('Choose another default.')).toBeInTheDocument();
        expect(
            screen
                .getAllByRole('button', { name: 'Deactivate' })
                .some((button) => button.hasAttribute('disabled')),
        ).toBe(true);
    });
});
