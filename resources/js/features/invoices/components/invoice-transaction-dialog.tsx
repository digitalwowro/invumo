import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormDialog } from '@/components/app/form-dialog';
import type {
    DialogTriggerSize,
    DialogTriggerVariant,
} from '@/components/app/form-dialog';
import { TextareaField, TextField } from '@/components/app/form-field';
import { Grid, Stack } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { SystemMessage } from '@/components/app/system-message';
import { FieldGroup } from '@/components/ui/field';
import type {
    InvoiceTransactionKind,
    InvoiceTransactionRow,
    InvoiceTransactions,
    InvoiceTransactionTranslations,
} from '@/types/invoice-transaction';

type TransactionForm = {
    kind: InvoiceTransactionKind;
    adjustment_direction: '' | 'INCREASE_PAID' | 'DECREASE_PAID';
    amount: string;
    transaction_date: string;
    payment_method: string;
    reference: string;
    notes: string;
    adjustment_reason: string;
    mutation_key: string;
    edit_version: number | null;
    confirmed: boolean;
};

type Props = {
    url: string;
    labels: InvoiceTransactionTranslations;
    transaction?: InvoiceTransactionRow;
    createKind?: InvoiceTransactionKind;
    today: string;
    limits: InvoiceTransactions['limits'];
    canAdjust: boolean;
    disabled?: boolean;
    disabledDescription?: string;
    triggerVariant?: DialogTriggerVariant;
    triggerSize?: DialogTriggerSize;
};

function initialData(props: Props): TransactionForm {
    const transaction = props.transaction;

    return {
        kind: transaction?.kind ?? props.createKind ?? 'PAYMENT',
        adjustment_direction:
            transaction?.adjustmentDirection ??
            (props.createKind === 'ADJUSTMENT' ? 'INCREASE_PAID' : ''),
        amount: transaction?.amount ?? '',
        transaction_date: transaction?.transactionDate ?? props.today,
        payment_method: transaction?.paymentMethod ?? '',
        reference: transaction?.reference ?? '',
        notes: transaction?.notes ?? '',
        adjustment_reason: transaction?.adjustmentReason ?? '',
        mutation_key: crypto.randomUUID(),
        edit_version: transaction?.editVersion ?? null,
        confirmed: true,
    };
}

export function InvoiceTransactionDialog(props: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm<TransactionForm>(initialData(props));
    const editing = Boolean(props.transaction);
    const errors = form.errors as Record<string, string>;
    const openChange = (next: boolean) => {
        if (next) {
            const initial = initialData(props);
            form.setDefaults(initial);
            form.setData(initial);
            form.clearErrors();
        }

        setOpen(next);
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.patch(props.url, options);
        } else {
            form.post(props.url, options);
        }
    };
    const kindOptions = (['PAYMENT', 'REFUND', 'ADJUSTMENT'] as const)
        .filter((kind) => kind !== 'ADJUSTMENT' || props.canAdjust)
        .map((kind) => ({ value: kind, label: props.labels.kinds[kind] }));
    const triggerLabel = editing
        ? props.labels.edit
        : props.labels[
              props.createKind === 'REFUND'
                  ? 'add_refund'
                  : props.createKind === 'ADJUSTMENT'
                    ? 'add_adjustment'
                    : 'add_payment'
          ];

    return (
        <FormDialog
            open={open}
            onOpenChange={openChange}
            triggerLabel={triggerLabel}
            title={
                editing ? props.labels.edit_title : props.labels.create_title
            }
            description={
                editing
                    ? props.labels.edit_description
                    : props.labels.create_description
            }
            cancelLabel={props.labels.cancel}
            confirmLabel={props.labels.save}
            closeLabel={props.labels.close}
            formId={`invoice-transaction-${props.transaction?.id ?? props.createKind}`}
            processing={form.processing}
            triggerDisabled={props.disabled}
            triggerDisabledDescription={props.disabledDescription}
            triggerVariant={props.triggerVariant}
            triggerSize={props.triggerSize}
        >
            <form
                id={`invoice-transaction-${props.transaction?.id ?? props.createKind}`}
                onSubmit={submit}
            >
                <Stack gap="lg">
                    {errors.transaction && (
                        <SystemMessage
                            title={errors.transaction}
                            tone="error"
                        />
                    )}
                    {props.transaction?.receipt?.mayHaveBeenSent && (
                        <SystemMessage
                            title={props.labels.receipt.warning}
                            tone="warning"
                        />
                    )}
                    <FieldGroup>
                        {editing && (
                            <SelectField
                                name="kind"
                                label={props.labels.fields.kind}
                                value={form.data.kind}
                                options={kindOptions}
                                onValueChange={(value) =>
                                    form.setData((current) => ({
                                        ...current,
                                        kind: value as InvoiceTransactionKind,
                                        adjustment_direction:
                                            value === 'ADJUSTMENT'
                                                ? current.adjustment_direction ||
                                                  'INCREASE_PAID'
                                                : '',
                                        adjustment_reason:
                                            value === 'ADJUSTMENT'
                                                ? current.adjustment_reason
                                                : '',
                                    }))
                                }
                            />
                        )}
                        {form.data.kind === 'ADJUSTMENT' && (
                            <SelectField
                                name="adjustment_direction"
                                label={props.labels.fields.adjustment_direction}
                                value={form.data.adjustment_direction}
                                error={errors.adjustment_direction}
                                options={Object.entries(
                                    props.labels.directions,
                                ).map(([value, label]) => ({ value, label }))}
                                onValueChange={(value) =>
                                    form.setData(
                                        'adjustment_direction',
                                        value as TransactionForm['adjustment_direction'],
                                    )
                                }
                                required
                            />
                        )}
                        <Grid columns={2} gap="lg">
                            <TextField
                                label={props.labels.fields.amount}
                                error={errors.amount}
                                input={{
                                    name: 'amount',
                                    value: form.data.amount,
                                    onChange: (event) =>
                                        form.setData(
                                            'amount',
                                            event.target.value,
                                        ),
                                    inputMode: 'decimal',
                                    required: true,
                                    'data-test': 'transaction-amount',
                                }}
                            />
                            <TextField
                                label={props.labels.fields.transaction_date}
                                error={errors.transaction_date}
                                input={{
                                    name: 'transaction_date',
                                    type: 'date',
                                    value: form.data.transaction_date,
                                    max: props.today,
                                    onChange: (event) =>
                                        form.setData(
                                            'transaction_date',
                                            event.target.value,
                                        ),
                                    required: true,
                                }}
                            />
                        </Grid>
                        <Grid columns={2} gap="lg">
                            <TextField
                                label={props.labels.fields.payment_method}
                                error={errors.payment_method}
                                input={{
                                    name: 'payment_method',
                                    value: form.data.payment_method,
                                    maxLength: props.limits.paymentMethod,
                                    onChange: (event) =>
                                        form.setData(
                                            'payment_method',
                                            event.target.value,
                                        ),
                                }}
                            />
                            <TextField
                                label={props.labels.fields.reference}
                                error={errors.reference}
                                input={{
                                    name: 'reference',
                                    value: form.data.reference,
                                    maxLength: props.limits.reference,
                                    onChange: (event) =>
                                        form.setData(
                                            'reference',
                                            event.target.value,
                                        ),
                                }}
                            />
                        </Grid>
                        {form.data.kind === 'ADJUSTMENT' && (
                            <TextareaField
                                label={props.labels.fields.adjustment_reason}
                                error={errors.adjustment_reason}
                                textarea={{
                                    name: 'adjustment_reason',
                                    value: form.data.adjustment_reason,
                                    maxLength: props.limits.adjustmentReason,
                                    onChange: (event) =>
                                        form.setData(
                                            'adjustment_reason',
                                            event.target.value,
                                        ),
                                    required: true,
                                }}
                            />
                        )}
                        <TextareaField
                            label={props.labels.fields.notes}
                            error={errors.notes}
                            textarea={{
                                name: 'notes',
                                value: form.data.notes,
                                maxLength: props.limits.notes,
                                onChange: (event) =>
                                    form.setData('notes', event.target.value),
                            }}
                        />
                    </FieldGroup>
                </Stack>
            </form>
        </FormDialog>
    );
}
