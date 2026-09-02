import { Form, Head } from '@inertiajs/react';
import CompanyController from '@/actions/App/Modules/Companies/Http/Controllers/CompanyController';
import { SubmitButton } from '@/components/app/form-actions';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import {
    ResourceWorkspace,
    ResourceWorkspaceHeader,
} from '@/components/app/resource-workspace';
import type { CompaniesUiTranslations } from '@/types';

export default function CreateCompany({
    indexUrl,
    translations,
}: {
    indexUrl: string;
    translations: CompaniesUiTranslations;
}) {
    const labels = translations.create;

    return (
        <>
            <Head title={labels.head_title} />
            <ResourceWorkspace>
                <Stack gap="xl">
                    <Form
                        {...CompanyController.store.form()}
                        id="new-company-form"
                    >
                        {({ processing, errors }) => (
                            <Stack gap="xl">
                                <ResourceWorkspaceHeader
                                    breadcrumbs={[
                                        {
                                            title: translations.index.title,
                                            href: indexUrl,
                                        },
                                        {
                                            title: labels.title,
                                            href: indexUrl,
                                        },
                                    ]}
                                    title={labels.title}
                                    description={labels.description}
                                    actions={
                                        <SubmitButton processing={processing}>
                                            {labels.submit}
                                        </SubmitButton>
                                    }
                                />
                                <FormSection
                                    title={labels.section_title}
                                    description={labels.section_description}
                                >
                                    <TextField
                                        id="name"
                                        label={labels.name}
                                        error={errors.name}
                                        input={{
                                            name: 'name',
                                            required: true,
                                            autoComplete: 'organization',
                                            maxLength: 160,
                                            autoFocus: true,
                                            placeholder:
                                                labels.name_placeholder,
                                        }}
                                    />
                                </FormSection>
                            </Stack>
                        )}
                    </Form>
                </Stack>
            </ResourceWorkspace>
        </>
    );
}
