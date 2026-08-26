import { Form, Head } from '@inertiajs/react';
import { FilePlus2 } from 'lucide-react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import type { InvoiceTranslations } from '@/types/invoice';

type Props = {
    storeUrl: string;
    creationKey: string;
    translations: InvoiceTranslations;
};

export default function CreateInvoice(props: Props) {
    return (
        <>
            <Head title={props.translations.create.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={props.translations.create.title}
                        subtitle={props.translations.create.description}
                    />
                    <Form action={props.storeUrl} method="post">
                        {({ errors, processing }) => (
                            <FormSection
                                title={props.translations.create.section_title}
                                description={
                                    props.translations.create
                                        .section_description
                                }
                                actions={
                                    <FormActions>
                                        <SubmitButton processing={processing}>
                                            <FilePlus2 aria-hidden="true" />
                                            {props.translations.create.submit}
                                        </SubmitButton>
                                    </FormActions>
                                }
                            >
                                <input
                                    type="hidden"
                                    name="creation_key"
                                    value={props.creationKey}
                                />
                                {errors.invoice && (
                                    <SystemMessage
                                        title={errors.invoice}
                                        tone="error"
                                    />
                                )}
                            </FormSection>
                        )}
                    </Form>
                </Stack>
            </PageFrame>
        </>
    );
}
