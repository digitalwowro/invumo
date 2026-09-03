import { useForm } from '@inertiajs/react';
import { Plus, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import {
    CheckboxField,
    TextareaField,
    TextField,
} from '@/components/app/form-field';
import InputError from '@/components/app/input-error';
import { SelectField } from '@/components/app/select-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field, FieldLabel } from '@/components/ui/field';
import type {
    DeliveryAttachmentMode,
    DeliveryComposer,
    DeliveryRecipient,
    DeliveryRecipientRole,
    DocumentDelivery,
    DocumentDeliveryTranslations,
} from '@/types/document-delivery';

type Props = {
    composer: DeliveryComposer;
    limits: DocumentDelivery['limits'];
    labels: DocumentDeliveryTranslations['composer'];
    disabled: boolean;
};

type ComposerForm = {
    delivery_key: string;
    edit_version: number;
    attachment_mode: DeliveryAttachmentMode;
    recipients: DeliveryRecipient[];
    subject: string;
    body: string;
    button_label: string;
    signature: string;
    confirmed_final_quote_state: boolean;
};

export function DocumentDeliveryComposer({
    composer,
    limits,
    labels,
    disabled,
}: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useForm<ComposerForm>({
        delivery_key: composer.deliveryKey,
        edit_version: composer.editVersion,
        attachment_mode: composer.attachmentMode,
        recipients: composer.recipients,
        subject: composer.subject,
        body: composer.body,
        button_label: composer.buttonLabel,
        signature: composer.signature ?? '',
        confirmed_final_quote_state: false,
    });
    const updateRecipient = (
        index: number,
        field: keyof DeliveryRecipient,
        value: string,
    ) => {
        setData(
            'recipients',
            data.recipients.map((recipient, row) =>
                row === index ? { ...recipient, [field]: value } : recipient,
            ),
        );
    };
    const removeRecipient = (index: number) => {
        setData(
            'recipients',
            data.recipients.filter((_, row) => row !== index),
        );
    };
    const addRecipient = () => {
        setData('recipients', [
            ...data.recipients,
            { role: 'CC', name: null, email: '' },
        ]);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild data-testid="delivery-compose">
                <Button type="button" disabled={disabled}>
                    <Send data-icon="inline-start" />
                    {labels.submit}
                </Button>
            </DialogTrigger>
            {disabled && (
                <p className="max-w-xs text-sm text-foreground-muted">
                    {labels.unsaved_warning}
                </p>
            )}
            <DialogContent className="sm:max-w-3xl" closeLabel={labels.close}>
                <DialogHeader>
                    <DialogTitle>{labels.title}</DialogTitle>
                    <DialogDescription>{labels.description}</DialogDescription>
                </DialogHeader>
                <form
                    className="min-h-0 space-y-6 overflow-y-auto pr-1"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(composer.sendUrl, {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                >
                    <TextField
                        label={labels.subject}
                        error={errors.subject}
                        input={{
                            value: data.subject,
                            maxLength: limits.subject,
                            required: true,
                            onChange: (event) =>
                                setData('subject', event.target.value),
                        }}
                    />
                    <TextareaField
                        label={labels.body}
                        error={errors.body}
                        textarea={{
                            value: data.body,
                            maxLength: limits.body,
                            required: true,
                            rows: 8,
                            onChange: (event) =>
                                setData('body', event.target.value),
                        }}
                    />
                    <div className="grid gap-4 md:grid-cols-2">
                        <TextField
                            label={labels.button_label}
                            error={errors.button_label}
                            input={{
                                value: data.button_label,
                                maxLength: limits.buttonLabel,
                                required: true,
                                onChange: (event) =>
                                    setData('button_label', event.target.value),
                            }}
                        />
                        <SelectField
                            name="attachment_mode"
                            label={labels.attachment_mode}
                            value={data.attachment_mode}
                            onValueChange={(value) =>
                                setData(
                                    'attachment_mode',
                                    value as DeliveryAttachmentMode,
                                )
                            }
                            options={Object.entries(labels.modes).map(
                                ([value, label]) => ({ value, label }),
                            )}
                            required
                        />
                    </div>
                    <TextareaField
                        label={labels.signature}
                        error={errors.signature}
                        textarea={{
                            value: data.signature,
                            maxLength: limits.signature,
                            rows: 3,
                            onChange: (event) =>
                                setData('signature', event.target.value),
                        }}
                    />
                    <Field>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <FieldLabel>{labels.recipients}</FieldLabel>
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                disabled={
                                    data.recipients.length >= limits.recipients
                                }
                                onClick={addRecipient}
                            >
                                <Plus data-icon="inline-start" />
                                {labels.add_recipient}
                            </Button>
                        </div>
                        <div className="space-y-3">
                            {data.recipients.map((recipient, index) => (
                                <div
                                    key={index}
                                    className="grid gap-3 rounded-lg border border-border p-3 md:grid-cols-[8rem_minmax(0,1fr)_minmax(0,1.2fr)_auto]"
                                >
                                    <SelectField
                                        name={`recipients.${index}.role`}
                                        label={labels.recipient_role}
                                        value={recipient.role}
                                        onValueChange={(value) =>
                                            updateRecipient(
                                                index,
                                                'role',
                                                value as DeliveryRecipientRole,
                                            )
                                        }
                                        options={(
                                            ['TO', 'CC', 'BCC'] as const
                                        ).map((role) => ({
                                            value: role,
                                            label: labels.roles[role],
                                        }))}
                                    />
                                    <TextField
                                        label={labels.recipient_name}
                                        input={{
                                            value: recipient.name ?? '',
                                            maxLength: 160,
                                            onChange: (event) =>
                                                updateRecipient(
                                                    index,
                                                    'name',
                                                    event.target.value,
                                                ),
                                        }}
                                    />
                                    <TextField
                                        label={labels.recipient_email}
                                        input={{
                                            type: 'email',
                                            value: recipient.email,
                                            maxLength: 254,
                                            required: true,
                                            onChange: (event) =>
                                                updateRecipient(
                                                    index,
                                                    'email',
                                                    event.target.value,
                                                ),
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label={labels.remove_recipient}
                                        onClick={() => removeRecipient(index)}
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                        <InputError message={errors.recipients} />
                    </Field>
                    {composer.requiresFinalStateConfirmation && (
                        <CheckboxField
                            label={labels.final_state_confirm}
                            description={labels.final_state_warning}
                            error={errors.confirmed_final_quote_state}
                            checkbox={{
                                checked: data.confirmed_final_quote_state,
                                onCheckedChange: (checked) =>
                                    setData(
                                        'confirmed_final_quote_state',
                                        checked === true,
                                    ),
                            }}
                        />
                    )}
                    <InputError
                        message={(errors as Record<string, string>).delivery}
                    />
                    <FormActions separated>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {labels.cancel}
                            </Button>
                        </DialogClose>
                        <SubmitButton processing={processing}>
                            {labels.submit}
                        </SubmitButton>
                    </FormActions>
                </form>
            </DialogContent>
        </Dialog>
    );
}
