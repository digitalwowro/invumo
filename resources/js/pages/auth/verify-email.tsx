import { Form, Head } from '@inertiajs/react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import TextLink from '@/components/app/text-link';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import type { AuthUiTranslations } from '@/types';

type Props = {
    status?: string;
    translations: AuthUiTranslations;
};

export default function VerifyEmail({ status, translations }: Props) {
    const { page } = translations;

    return (
        <>
            <Head title={page.headTitle} />
            <Stack gap="xl">
                {status === 'verification-link-sent' && (
                    <SystemMessage title={page.sent} />
                )}

                <Form {...send.form()}>
                    {({ processing }) => (
                        <FormActions align="stretch">
                            <SubmitButton processing={processing}>
                                {page.resend}
                            </SubmitButton>
                        </FormActions>
                    )}
                </Form>

                <TextLink href={logout()} as="button">
                    {page.logOut}
                </TextLink>
            </Stack>
        </>
    );
}
