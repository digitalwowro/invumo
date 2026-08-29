import { Head, usePage } from '@inertiajs/react';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SystemMessage } from '@/components/app/system-message';
import { CompanyReminderFailures } from '@/features/delivery/components/company-reminder-failures';
import { ReminderRuleForm } from '@/features/delivery/components/reminder-rule-form';
import type { CompaniesUiTranslations } from '@/types/company';
import type {
    CompanyReminderFailure,
    ReminderRelationOption,
    ReminderRule,
} from '@/types/reminder';

type Props = {
    rules: ReminderRule[];
    relationOptions: ReminderRelationOption[];
    limits: { rules: number; dayOffset: number };
    saveUrl: string;
    failures: CompanyReminderFailure[];
    locale: string;
    timezone: string;
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyReminders({
    rules,
    relationOptions,
    limits,
    saveUrl,
    failures,
    locale,
    timezone,
    status,
    translations,
}: Props) {
    const { i18n, errors } = usePage().props;
    const labels = translations.settings.reminders;

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                <SectionHeader
                    title={labels.title}
                    description={labels.description}
                />
                {status && <SystemMessage title={status} tone="money" />}
                {errors.reminder && (
                    <SystemMessage title={errors.reminder} tone="error" />
                )}
                <FormSection
                    title={labels.rules_title}
                    description={labels.rules_description}
                >
                    <ReminderRuleForm
                        rules={rules}
                        relationOptions={relationOptions}
                        maxRules={limits.rules}
                        maxDayOffset={limits.dayOffset}
                        saveUrl={saveUrl}
                        allowRemoval
                        labels={labels}
                    />
                </FormSection>
                <SystemMessage title={labels.history_note} tone="neutral" />
                <Stack gap="lg">
                    <SectionHeader
                        title={labels.failures_title}
                        description={labels.failures_description}
                    />
                    <CompanyReminderFailures
                        failures={failures}
                        locale={locale}
                        timezone={timezone}
                        closeLabel={i18n.common.accessibility.close_navigation}
                        labels={labels}
                    />
                </Stack>
            </Stack>
        </>
    );
}
