import { Form, Head } from '@inertiajs/react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { PasswordField, TextField } from '@/components/app/form-field';
import { Stack } from '@/components/app/layout';
import { update } from '@/routes/password';
import type { AuthUiTranslations } from '@/types';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
    translations: AuthUiTranslations;
};

export default function ResetPassword({
    token,
    email,
    passwordRules,
    translations,
}: Props) {
    const { page, shared } = translations;

    return (
        <>
            <Head title={page.headTitle} />
            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
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
                                    autoComplete: 'email',
                                    value: email,
                                    readOnly: true,
                                }}
                            />
                            <PasswordField
                                id="password"
                                label={shared.password}
                                error={errors.password}
                                input={{
                                    name: 'password',
                                    autoComplete: 'new-password',
                                    autoFocus: true,
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
                                testId="reset-password-button"
                            >
                                {page.submit}
                            </SubmitButton>
                        </FormActions>
                    </Stack>
                )}
            </Form>
        </>
    );
}
