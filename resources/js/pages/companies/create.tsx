import { Form, Head } from '@inertiajs/react';
import CompanyController from '@/actions/App/Modules/Companies/Http/Controllers/CompanyController';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import type { CompaniesUiTranslations } from '@/types';

export default function CreateCompany({
    translations,
}: {
    translations: CompaniesUiTranslations;
}) {
    const labels = translations.create;

    return (
        <>
            <Head title={labels.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={labels.title}
                        subtitle={labels.description}
                    />
                    <Form {...CompanyController.store.form()}>
                        {({ processing, errors }) => (
                            <FormSection
                                title={labels.section_title}
                                description={labels.section_description}
                                actions={
                                    <FormActions>
                                        <SubmitButton processing={processing}>
                                            {labels.submit}
                                        </SubmitButton>
                                    </FormActions>
                                }
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
                                        placeholder: labels.name_placeholder,
                                    }}
                                />
                            </FormSection>
                        )}
                    </Form>
                </Stack>
            </PageFrame>
        </>
    );
}
