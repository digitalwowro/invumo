import { Form, Head } from '@inertiajs/react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { PasswordField, TextField } from '@/components/app/form-field';
import { Stack } from '@/components/app/layout';
import TextLink from '@/components/app/text-link';
import { PageSubtitle } from '@/components/app/typography';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { AuthUiTranslations } from '@/types';

type Props = {
    passwordRules: string;
    translations: AuthUiTranslations;
};

export default function Register({ passwordRules, translations }: Props) {
    const { page, shared } = translations;

    return (
        <>
            <Head title={page.headTitle} />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
            >
                {({ processing, errors }) => (
                    <Stack gap="xl">
                        <Stack gap="lg">
                            <TextField
                                id="name"
                                label={shared.name}
                                error={errors.name}
                                input={{
                                    type: 'text',
                                    name: 'name',
                                    required: true,
                                    autoFocus: true,
                                    autoComplete: 'name',
                                    placeholder: shared.fullNamePlaceholder,
                                }}
                            />
                            <TextField
                                id="email"
                                label={shared.email}
                                error={errors.email}
                                input={{
                                    type: 'email',
                                    name: 'email',
                                    required: true,
                                    autoComplete: 'email',
                                    placeholder: shared.emailPlaceholder,
                                }}
                            />
                            <PasswordField
                                id="password"
                                label={shared.password}
                                error={errors.password}
                                input={{
                                    name: 'password',
                                    required: true,
                                    autoComplete: 'new-password',
                                    placeholder: shared.passwordPlaceholder,
                                    passwordrules: passwordRules,
                                    showLabel: shared.showPassword,
                                    hideLabel: shared.hidePassword,
                                }}
                            />
                            <PasswordField
                                id="password_confirmation"
                                label={shared.confirmPassword}
                                error={errors.password_confirmation}
                                input={{
                                    name: 'password_confirmation',
                                    required: true,
                                    autoComplete: 'new-password',
                                    placeholder: shared.passwordPlaceholder,
                                    passwordrules: passwordRules,
                                    showLabel: shared.showPassword,
                                    hideLabel: shared.hidePassword,
                                }}
                            />
                        </Stack>

                        <FormActions align="stretch">
                            <SubmitButton
                                processing={processing}
                                testId="register-user-button"
                            >
                                {page.submit}
                            </SubmitButton>
                        </FormActions>

                        <PageSubtitle>
                            {page.hasAccount}{' '}
                            <TextLink href={login()}>{page.logIn}</TextLink>
                        </PageSubtitle>
                    </Stack>
                )}
            </Form>
        </>
    );
}
