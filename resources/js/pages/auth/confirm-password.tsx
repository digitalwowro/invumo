import { Form, Head } from '@inertiajs/react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { PasswordField } from '@/components/app/form-field';
import { Stack } from '@/components/app/layout';
import { store } from '@/routes/password/confirm';
import type { AuthUiTranslations } from '@/types';

export default function ConfirmPassword({
    translations,
}: {
    translations: AuthUiTranslations;
}) {
    const { page, shared } = translations;

    return (
        <>
            <Head title={page.headTitle} />
            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <Stack gap="xl">
                        <PasswordField
                            id="password"
                            label={shared.password}
                            error={errors.password}
                            input={{
                                name: 'password',
                                autoComplete: 'current-password',
                                autoFocus: true,
                                placeholder: shared.passwordPlaceholder,
                                showLabel: shared.showPassword,
                                hideLabel: shared.hidePassword,
                            }}
                        />
                        <FormActions align="stretch">
                            <SubmitButton
                                processing={processing}
                                testId="confirm-password-button"
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
