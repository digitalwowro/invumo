import { Form, Head } from '@inertiajs/react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import {
    CheckboxField,
    PasswordField,
    TextField,
} from '@/components/app/form-field';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import TextLink from '@/components/app/text-link';
import { PageSubtitle } from '@/components/app/typography';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { AuthUiTranslations } from '@/types';

type Props = {
    status?: string;
    canResetPassword: boolean;
    translations: AuthUiTranslations;
};

export default function Login({
    status,
    canResetPassword,
    translations,
}: Props) {
    const { page, shared } = translations;

    return (
        <>
            <Head title={page.headTitle} />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <Stack gap="xl">
                        <Stack gap="lg">
                            <TextField
                                id="email"
                                label={shared.email}
                                error={errors.email}
                                input={{
                                    type: 'email',
                                    name: 'email',
                                    required: true,
                                    autoFocus: true,
                                    autoComplete: 'email',
                                    placeholder: shared.emailPlaceholder,
                                }}
                            />
                            <PasswordField
                                id="password"
                                label={shared.password}
                                error={errors.password}
                                labelAction={
                                    canResetPassword ? (
                                        <TextLink href={request()}>
                                            {page.forgotPassword}
                                        </TextLink>
                                    ) : undefined
                                }
                                input={{
                                    name: 'password',
                                    required: true,
                                    autoComplete: 'current-password',
                                    placeholder: shared.passwordPlaceholder,
                                    showLabel: shared.showPassword,
                                    hideLabel: shared.hidePassword,
                                }}
                            />
                            <CheckboxField
                                id="remember"
                                label={page.remember}
                                checkbox={{ name: 'remember' }}
                            />
                        </Stack>

                        <FormActions align="stretch">
                            <SubmitButton
                                processing={processing}
                                testId="login-button"
                            >
                                {page.submit}
                            </SubmitButton>
                        </FormActions>

                        <PageSubtitle>
                            {page.noAccount}{' '}
                            <TextLink href={register()}>{page.signUp}</TextLink>
                        </PageSubtitle>

                        {status && <SystemMessage title={status} />}
                    </Stack>
                )}
            </Form>
        </>
    );
}
