import { Form, Head } from '@inertiajs/react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { TextField } from '@/components/app/form-field';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import TextLink from '@/components/app/text-link';
import { PageSubtitle } from '@/components/app/typography';
import { login } from '@/routes';
import { email } from '@/routes/password';
import type { AuthUiTranslations } from '@/types';

type Props = {
    status?: string;
    translations: AuthUiTranslations;
};

export default function ForgotPassword({ status, translations }: Props) {
    const { page, shared } = translations;

    return (
        <>
            <Head title={page.headTitle} />
            <Stack gap="xl">
                {status && <SystemMessage title={status} />}

                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <Stack gap="xl">
                            <TextField
                                id="email"
                                label={shared.email}
                                error={errors.email}
                                input={{
                                    type: 'email',
                                    name: 'email',
                                    autoComplete: 'email',
                                    autoFocus: true,
                                    placeholder: shared.emailPlaceholder,
                                }}
                            />
                            <FormActions align="stretch">
                                <SubmitButton processing={processing}>
                                    {page.submit}
                                </SubmitButton>
                            </FormActions>
                        </Stack>
                    )}
                </Form>

                <PageSubtitle>
                    {page.returnPrompt}{' '}
                    <TextLink href={login()}>{page.logIn}</TextLink>
                </PageSubtitle>
            </Stack>
        </>
    );
}
