import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ChoiceField } from '@/components/app/choice-field';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { FormSection } from '@/components/app/form-section';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import {
    customerDeliveryPayload,
    customerDeliveryRecipientForms,
} from '@/features/customers/components/customer-delivery-form-data';
import { CustomerDeliveryRecipientFields } from '@/features/customers/components/customer-delivery-recipient-fields';
import { interpolate } from '@/lib/translations';
import type {
    CustomerDeliveryRecipient,
    CustomerDeliveryTranslations,
    CustomerFieldLimits,
    CustomerOption,
} from '@/types/customer';

type Props = {
    updateUrl: string | null;
    emailAttachmentMode: string | null;
    companyEmailAttachmentMode: 'SECURE_LINK_ONLY' | 'ATTACH_PDF';
    recipients: CustomerDeliveryRecipient[];
    contactOptions: CustomerOption[];
    roleOptions: CustomerOption[];
    modeOptions: CustomerOption[];
    limits: CustomerFieldLimits;
    labels: CustomerDeliveryTranslations;
};

export function CustomerDeliveryForm({
    updateUrl,
    emailAttachmentMode,
    companyEmailAttachmentMode,
    recipients: storedRecipients,
    contactOptions,
    roleOptions,
    modeOptions,
    limits,
    labels,
}: Props) {
    const initialMode = emailAttachmentMode ?? 'INHERIT';
    const initialRecipients = () =>
        customerDeliveryRecipientForms(storedRecipients);
    const [mode, setMode] = useState(initialMode);
    const [recipients, setRecipients] = useState(initialRecipients);
    const [savedSignature, setSavedSignature] = useState(() =>
        JSON.stringify({ mode: initialMode, recipients: initialRecipients() }),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const signature = JSON.stringify({ mode, recipients });
    const isDirty = signature !== savedSignature;
    const disabled = updateUrl === null;
    const companyModeLabel = labels.modes[companyEmailAttachmentMode];
    const choices = [
        {
            value: 'INHERIT',
            label: interpolate(labels.inherit_mode, {
                mode: companyModeLabel,
            }),
        },
        ...modeOptions,
    ];

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!updateUrl) {
            return;
        }

        router.patch(updateUrl, customerDeliveryPayload(mode, recipients), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess: () => {
                setSavedSignature(signature);
                setErrors({});
            },
        });
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title={labels.title}
                description={labels.description}
                actions={
                    updateUrl && (
                        <FormActions>
                            <SubmitButton
                                processing={processing}
                                disabled={!isDirty}
                            >
                                {labels.save}
                            </SubmitButton>
                        </FormActions>
                    )
                }
            >
                <UnsavedChangesGuard
                    active={!disabled && isDirty && !processing}
                    message={labels.unsaved_warning}
                />
                {errors.delivery && (
                    <SystemMessage title={errors.delivery} tone="error" />
                )}
                <ChoiceField
                    name="email_attachment_mode"
                    label={labels.mode_label}
                    description={labels.mode_description}
                    error={errors.email_attachment_mode}
                    defaultValue={mode}
                    disabled={disabled}
                    required
                    options={choices}
                    onValueChange={setMode}
                />
                <CustomerDeliveryRecipientFields
                    recipients={recipients}
                    contactOptions={contactOptions}
                    roleOptions={roleOptions}
                    limits={limits}
                    labels={labels}
                    errors={errors}
                    disabled={disabled}
                    onChange={setRecipients}
                />
            </FormSection>
        </form>
    );
}
