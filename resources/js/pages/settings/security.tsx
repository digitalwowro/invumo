import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { FormActions, SaveButton } from '@/components/app/form-actions';
import { PasswordField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import type { SettingsUiTranslations } from '@/types';

type Props = {
    passwordRules: string;
    translations: SettingsUiTranslations;
};

export default function Security({ passwordRules, translations }: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const { page, shared } = translations;

    return (
        <>
            <Head title={page.headTitle} />

            <Form
                {...SecurityController.update.form()}
                options={{ preserveScroll: true }}
                resetOnError={[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]}
                resetOnSuccess
                onError={(errors) => {
                    if (errors.password) {
                        passwordInput.current?.focus();
                    }

                    if (errors.current_password) {
                        currentPasswordInput.current?.focus();
                    }
                }}
            >
                {({ errors, processing, isDirty }) => (
                    <FormSection
                        title={page.title}
                        description={page.description}
                        actions={
                            <FormActions>
                                <SaveButton
                                    processing={processing}
                                    dirty={isDirty}
                                    testId="update-password-button"
                                >
                                    {shared.save}
                                </SaveButton>
                            </FormActions>
                        }
                    >
                        <PasswordField
                            id="current_password"
                            label={page.currentPassword}
                            error={errors.current_password}
                            input={{
                                ref: currentPasswordInput,
                                name: 'current_password',
                                autoComplete: 'current-password',
                                placeholder: shared.passwordPlaceholder,
                                showLabel: shared.showPassword,
                                hideLabel: shared.hidePassword,
                            }}
                        />
                        <PasswordField
                            id="password"
                            label={page.newPassword}
                            error={errors.password}
                            input={{
                                ref: passwordInput,
                                name: 'password',
                                autoComplete: 'new-password',
                                placeholder: shared.passwordPlaceholder,
                                passwordrules: passwordRules,
                                showLabel: shared.showPassword,
                                hideLabel: shared.hidePassword,
                            }}
                        />
                        <PasswordField
                            id="password_confirmation"
                            label={page.confirmPassword}
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
                    </FormSection>
                )}
            </Form>
        </>
    );
}
