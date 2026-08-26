import { Form, Head } from '@inertiajs/react';
import { FilePlus2 } from 'lucide-react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import type { QuoteTranslations } from '@/types/quote';

type Props = {
    storeUrl: string;
    creationKey: string;
    translations: QuoteTranslations;
};

export default function CreateQuote({
    storeUrl,
    creationKey,
    translations,
}: Props) {
    return (
        <>
            <Head title={translations.create.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={translations.create.title}
                        subtitle={translations.create.description}
                    />
                    <Form action={storeUrl} method="post">
                        {({ errors, processing }) => (
                            <FormSection
                                title={translations.create.section_title}
                                description={
                                    translations.create.section_description
                                }
                                actions={
                                    <FormActions>
                                        <SubmitButton processing={processing}>
                                            <FilePlus2 aria-hidden="true" />
                                            {translations.create.submit}
                                        </SubmitButton>
                                    </FormActions>
                                }
                            >
                                <input
                                    type="hidden"
                                    name="creation_key"
                                    value={creationKey}
                                />
                                {errors.quote && (
                                    <SystemMessage
                                        title={errors.quote}
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
