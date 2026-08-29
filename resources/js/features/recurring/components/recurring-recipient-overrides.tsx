import { ChoiceField } from '@/components/app/choice-field';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid, Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SelectField } from '@/components/app/select-field';
import { Surface } from '@/components/app/surface';
import { Button } from '@/components/ui/button';
import { interpolate } from '@/lib/translations';
import type { CustomerTranslations } from '@/types/customer';
import type {
    RecurringInheritance,
    RecurringInheritanceTranslations,
    RecurringRecipient,
} from '@/types/recurring';

type Props = {
    value: RecurringInheritance;
    labels: RecurringInheritanceTranslations;
    customerLabels: CustomerTranslations;
    nameLimit: number;
    emailLimit: number;
    errors: Record<string, string>;
    onChange: (value: RecurringInheritance) => void;
};

export function RecurringRecipientOverrides(props: Props) {
    const explicit = props.value.recipientsMode === 'EXPLICIT';
    const change = (recipients: RecurringRecipient[]) =>
        props.onChange({ ...props.value, recipients });

    return (
        <FormSection
            title={props.labels.recipients_title}
            description={props.labels.recipients_description}
        >
            <ChoiceField
                name="inheritance.recipients_mode"
                label={props.labels.recipients_mode}
                defaultValue={props.value.recipientsMode}
                required
                options={[
                    { value: 'INHERIT', label: props.labels.inherit },
                    { value: 'EXPLICIT', label: props.labels.explicit },
                ]}
                onValueChange={(value) =>
                    props.onChange({
                        ...props.value,
                        recipientsMode: value as 'INHERIT' | 'EXPLICIT',
                    })
                }
            />
            {explicit && (
                <Stack gap="lg">
                    <SectionHeader
                        title={props.labels.recipients_title}
                        action={
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() =>
                                    change([
                                        ...props.value.recipients,
                                        {
                                            key: crypto.randomUUID(),
                                            role: 'TO',
                                            contactId: null,
                                            name: '',
                                            email: '',
                                        },
                                    ])
                                }
                            >
                                {props.labels.add_recipient}
                            </Button>
                        }
                    />
                    {props.value.recipients.map((recipient, index) => (
                        <Surface key={recipient.key} as="div">
                            <Stack gap="lg">
                                <SectionHeader
                                    title={interpolate(props.labels.recipient, {
                                        number: index + 1,
                                    })}
                                    action={
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            onClick={() =>
                                                change(
                                                    props.value.recipients.filter(
                                                        (_, current) =>
                                                            current !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            {props.labels.remove_recipient}
                                        </Button>
                                    }
                                />
                                <Grid columns={3} gap="lg">
                                    <SelectField
                                        name={`inheritance.recipients.${index}.role`}
                                        label={props.labels.role}
                                        value={recipient.role}
                                        options={Object.entries(
                                            props.customerLabels.delivery.roles,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        }))}
                                        error={error(props, index, 'role')}
                                        onValueChange={(value) =>
                                            changeRow(
                                                props,
                                                index,
                                                'role',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        label={props.labels.name}
                                        error={error(props, index, 'name')}
                                        input={{
                                            value: recipient.name,
                                            maxLength: props.nameLimit,
                                            onChange: (event) =>
                                                changeRow(
                                                    props,
                                                    index,
                                                    'name',
                                                    event.target.value,
                                                ),
                                        }}
                                    />
                                    <TextField
                                        label={props.labels.email}
                                        error={error(props, index, 'email')}
                                        input={{
                                            type: 'email',
                                            value: recipient.email,
                                            maxLength: props.emailLimit,
                                            required: true,
                                            onChange: (event) =>
                                                changeRow(
                                                    props,
                                                    index,
                                                    'email',
                                                    event.target.value,
                                                ),
                                        }}
                                    />
                                </Grid>
                            </Stack>
                        </Surface>
                    ))}
                </Stack>
            )}
        </FormSection>
    );
}

function changeRow(
    props: Props,
    index: number,
    field: 'role' | 'name' | 'email',
    value: string,
) {
    props.onChange({
        ...props.value,
        recipients: props.value.recipients.map((recipient, current) =>
            current === index
                ? {
                      ...recipient,
                      [field]: value,
                      contactId: field === 'role' ? recipient.contactId : null,
                  }
                : recipient,
        ) as RecurringRecipient[],
    });
}

function error(props: Props, index: number, field: string) {
    return props.errors[`inheritance.recipients.${index}.${field}`];
}
