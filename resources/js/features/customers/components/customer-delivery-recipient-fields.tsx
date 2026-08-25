import { ChoiceField } from '@/components/app/choice-field';
import { TextField } from '@/components/app/form-field';
import { Grid, Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SelectField } from '@/components/app/select-field';
import { Surface } from '@/components/app/surface';
import { Button } from '@/components/ui/button';
import { interpolate } from '@/lib/translations';
import type { CustomerOption } from '@/types/customer';
import type {
    CustomerDeliveryRecipientForm,
    CustomerDeliveryTranslations,
    CustomerFieldLimits,
    DeliveryRecipientRole,
} from '@/types/customer';

type Props = {
    recipients: CustomerDeliveryRecipientForm[];
    contactOptions: CustomerOption[];
    roleOptions: CustomerOption[];
    limits: CustomerFieldLimits;
    labels: CustomerDeliveryTranslations;
    errors: Record<string, string>;
    disabled: boolean;
    onChange: (recipients: CustomerDeliveryRecipientForm[]) => void;
};

let recipientSequence = 0;

export function CustomerDeliveryRecipientFields({
    recipients,
    contactOptions,
    roleOptions,
    limits,
    labels,
    errors,
    disabled,
    onChange,
}: Props) {
    const change = (index: number, next: CustomerDeliveryRecipientForm) =>
        onChange(
            recipients.map((row, rowIndex) =>
                rowIndex === index ? next : row,
            ),
        );

    const add = () => {
        recipientSequence += 1;
        onChange([
            ...recipients,
            {
                key: `new-${recipientSequence}`,
                role: 'TO',
                source: contactOptions.length > 0 ? 'contact' : 'explicit',
                contact_id: '',
                explicit_name: '',
                explicit_email: '',
            },
        ]);
    };

    return (
        <Stack gap="lg">
            <SectionHeader
                title={labels.recipients_title}
                description={labels.recipients_description}
                action={
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={disabled}
                        onClick={add}
                    >
                        {labels.add_recipient}
                    </Button>
                }
            />
            {recipients.map((recipient, index) => (
                <Surface key={recipient.key} as="div">
                    <Stack gap="lg">
                        <SectionHeader
                            title={interpolate(labels.recipient_number, {
                                number: index + 1,
                            })}
                            action={
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={disabled}
                                    onClick={() =>
                                        onChange(
                                            recipients.filter(
                                                (_, rowIndex) =>
                                                    rowIndex !== index,
                                            ),
                                        )
                                    }
                                >
                                    {labels.remove_recipient}
                                </Button>
                            }
                        />
                        <Grid columns={2} gap="lg">
                            <SelectField
                                name={`recipients.${index}.role`}
                                label={labels.fields.role}
                                error={errors[`recipients.${index}.role`]}
                                value={recipient.role}
                                disabled={disabled}
                                options={roleOptions}
                                onValueChange={(value) =>
                                    change(index, {
                                        ...recipient,
                                        role: value as DeliveryRecipientRole,
                                    })
                                }
                            />
                            <ChoiceField
                                name={`recipients.${index}.source`}
                                label={labels.fields.source}
                                error={errors[`recipients.${index}.source`]}
                                defaultValue={recipient.source}
                                disabled={disabled}
                                required
                                options={[
                                    {
                                        value: 'contact',
                                        label: labels.contact_source,
                                    },
                                    {
                                        value: 'explicit',
                                        label: labels.explicit_source,
                                    },
                                ]}
                                onValueChange={(value) =>
                                    change(index, {
                                        ...recipient,
                                        source: value as 'contact' | 'explicit',
                                        contact_id: '',
                                        explicit_name: '',
                                        explicit_email: '',
                                    })
                                }
                            />
                        </Grid>
                        {recipient.source === 'contact' ? (
                            <SelectField
                                name={`recipients.${index}.contact_id`}
                                label={labels.fields.contact_id}
                                placeholder={labels.select_contact}
                                error={errors[`recipients.${index}.contact_id`]}
                                value={recipient.contact_id}
                                disabled={disabled}
                                options={contactOptions}
                                onValueChange={(value) =>
                                    change(index, {
                                        ...recipient,
                                        contact_id: value,
                                    })
                                }
                            />
                        ) : (
                            <Grid columns={2} gap="lg">
                                <TextField
                                    label={labels.fields.explicit_name}
                                    error={
                                        errors[
                                            `recipients.${index}.explicit_name`
                                        ]
                                    }
                                    input={{
                                        value: recipient.explicit_name,
                                        maxLength: limits.name,
                                        disabled,
                                        onChange: (event) =>
                                            change(index, {
                                                ...recipient,
                                                explicit_name:
                                                    event.target.value,
                                            }),
                                    }}
                                />
                                <TextField
                                    label={labels.fields.explicit_email}
                                    error={
                                        errors[
                                            `recipients.${index}.explicit_email`
                                        ]
                                    }
                                    input={{
                                        type: 'email',
                                        value: recipient.explicit_email,
                                        required: true,
                                        maxLength: limits.email,
                                        disabled,
                                        onChange: (event) =>
                                            change(index, {
                                                ...recipient,
                                                explicit_email:
                                                    event.target.value,
                                            }),
                                    }}
                                />
                            </Grid>
                        )}
                    </Stack>
                </Surface>
            ))}
        </Stack>
    );
}
