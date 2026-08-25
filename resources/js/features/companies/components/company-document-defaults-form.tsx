import { Form } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { CompanyDocumentContentFields } from '@/features/companies/components/company-document-content-fields';
import { CompanyDocumentPolicyFields } from '@/features/companies/components/company-document-policy-fields';
import type { CompanyOption } from '@/types/company';
import type {
    CompanyDocumentDefaults,
    CompanyDocumentLimits,
    CompanyDocumentDefaultsTranslations,
} from '@/types/company-document-defaults';

type Props = {
    defaults: CompanyDocumentDefaults;
    limits: CompanyDocumentLimits;
    languageOptions: CompanyOption[];
    updateUrl: string;
    labels: CompanyDocumentDefaultsTranslations;
};

export function CompanyDocumentDefaultsForm({
    defaults,
    limits,
    languageOptions,
    updateUrl,
    labels,
}: Props) {
    return (
        <Form
            action={updateUrl}
            method="patch"
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
        >
            {({ errors, isDirty, processing }) => (
                <Stack gap="2xl">
                    <UnsavedChangesGuard
                        active={isDirty && !processing}
                        message={labels.unsaved_warning}
                    />
                    <CompanyDocumentPolicyFields
                        defaults={defaults}
                        limits={limits}
                        languageOptions={languageOptions}
                        errors={errors}
                        labels={labels}
                    />
                    <CompanyDocumentContentFields
                        defaults={defaults}
                        limits={limits}
                        errors={errors}
                        labels={labels}
                        processing={processing}
                    />
                </Stack>
            )}
        </Form>
    );
}
