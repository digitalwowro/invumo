import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { PasswordField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { DestructiveFormDialog } from '@/components/app/responsive-dialog';
import { SystemMessage } from '@/components/app/system-message';
import type { SettingsUiTranslations } from '@/types';

type DeleteUserProps = {
    translations: SettingsUiTranslations;
    formId: string;
};

export default function DeleteUser({ translations, formId }: DeleteUserProps) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const { page, shared } = translations;

    return (
        <Form
            {...ProfileController.destroy.form()}
            id={formId}
            options={{ preserveScroll: true }}
            onError={() => passwordInput.current?.focus()}
            resetOnSuccess
        >
            {({ resetAndClearErrors, processing, errors }) => (
                <FormSection
                    title={page.deleteTitle}
                    description={page.deleteDescription}
                >
                    <SystemMessage
                        tone="error"
                        title={page.warningTitle}
                        description={page.warningDescription}
                    />
                    <DestructiveFormDialog
                        triggerLabel={page.deleteTrigger}
                        title={page.deleteDialogTitle}
                        description={page.deleteDialogDescription}
                        cancelLabel={shared.cancel}
                        confirmLabel={page.deleteConfirm}
                        closeLabel={page.closeDialog}
                        formId={formId}
                        processing={processing}
                        onDismiss={resetAndClearErrors}
                    >
                        <PasswordField
                            id="delete-account-password"
                            label={shared.password}
                            error={errors.password}
                            input={{
                                ref: passwordInput,
                                form: formId,
                                name: 'password',
                                autoComplete: 'current-password',
                                placeholder: shared.passwordPlaceholder,
                                showLabel: shared.showPassword,
                                hideLabel: shared.hidePassword,
                            }}
                        />
                    </DestructiveFormDialog>
                </FormSection>
            )}
        </Form>
    );
}
