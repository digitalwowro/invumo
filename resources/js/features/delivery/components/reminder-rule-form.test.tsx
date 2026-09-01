import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { ReminderRuleForm } from '@/features/delivery/components/reminder-rule-form';
import type { ReminderRuleLabels } from '@/types/reminder';

vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

vi.mock('@inertiajs/react', () => ({
    Form: ({ children }: { children: (state: object) => ReactNode }) => (
        <form>
            {children({ errors: {}, isDirty: false, processing: false })}
        </form>
    ),
    router: { on: vi.fn(() => vi.fn()) },
}));

const labels: ReminderRuleLabels = {
    rules_title: 'Reminder rules',
    rules_description: 'Schedule reminders.',
    relation: 'Timing',
    day_offset: 'Days from due date',
    enabled: 'Enabled',
    add: 'Add reminder',
    remove: 'Remove reminder',
    save: 'Save reminder rules',
    unsaved_warning: 'Leave without saving?',
    empty: 'No reminders.',
};

describe('ReminderRuleForm', () => {
    it('adds bounded editable rules with localized controls', () => {
        render(
            <ReminderRuleForm
                rules={[
                    {
                        id: 'rule-one',
                        relation: 'BEFORE_DUE',
                        dayOffset: 3,
                        enabled: true,
                    },
                ]}
                relationOptions={[
                    { value: 'BEFORE_DUE', label: 'Before due date' },
                    { value: 'AFTER_DUE', label: 'After due date' },
                ]}
                maxRules={2}
                maxDayOffset={3652058}
                saveUrl="/reminders"
                allowRemoval
                labels={labels}
            />,
        );

        expect(screen.getByLabelText('Days from due date')).toHaveValue(3);
        expect(screen.getByLabelText('Enabled')).toBeChecked();
        fireEvent.click(screen.getByRole('button', { name: 'Add reminder' }));
        expect(screen.getAllByLabelText('Days from due date')).toHaveLength(2);
        expect(
            screen.getByRole('button', { name: 'Add reminder' }),
        ).toBeDisabled();
        expect(
            screen.getAllByRole('button', { name: 'Remove reminder' }),
        ).toHaveLength(2);
        expect(
            screen.getByRole('button', { name: 'Save reminder rules' }),
        ).toBeEnabled();
    });

    it('shows the localized empty state', () => {
        render(
            <ReminderRuleForm
                rules={[]}
                relationOptions={[]}
                maxRules={2}
                maxDayOffset={3652058}
                saveUrl="/reminders"
                labels={labels}
            />,
        );

        expect(screen.getByText('No reminders.')).toHaveClass(
            'bg-surface-subtle',
        );
    });
});
