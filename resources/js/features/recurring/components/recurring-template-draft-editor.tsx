import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { DiscardChangesDialog } from '@/components/app/discard-changes-dialog';
import { FormActions, SaveButton } from '@/components/app/form-actions';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid, Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { DocumentCustomerControls } from '@/components/domain/documents/document-customer-controls';
import {
    calculateDocumentLine,
    completeLine,
} from '@/components/domain/documents/document-draft-lines';
import { DocumentLineEditor } from '@/components/domain/documents/document-line-editor';
import { DocumentSourceDialogs } from '@/components/domain/documents/document-source-dialogs';
import {
    applyRecurringCustomer,
    blankRecurringLine,
    recurringTemplateFormData,
    recurringTemplatePayload,
} from '@/features/recurring/components/recurring-template-form-data';
import { RecurringTemplateOverrideSections } from '@/features/recurring/components/recurring-template-override-sections';
import { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import type { CustomerTranslations } from '@/types/customer';
import type {
    DocumentCustomerSelection,
    DocumentLineDraft,
} from '@/types/document';
import type {
    RecurringSourceProps,
    RecurringInheritanceProps,
    RecurringTemplateDraft,
    RecurringTemplateLimits,
} from '@/types/recurring';
import type { RecurringTranslations } from '@/types/recurring-translations';

type Props = RecurringSourceProps &
    RecurringInheritanceProps & {
        template: RecurringTemplateDraft;
        limits: RecurringTemplateLimits;
        updateUrl: string;
        labels: RecurringTranslations['editor'];
        customerLabels: CustomerTranslations;
    };

const FORM_ID = 'recurring-template-draft-editor';

export function RecurringTemplateDraftEditor(props: Props) {
    const form = useForm(
        recurringTemplateFormData(props.template, props.inheritance),
    );
    const [customer, setCustomer] = useState(props.template.customer);
    const [precision, setPrecision] = useState(
        props.template.currencyPrecision,
    );
    const savedSources = useRef({
        customer: props.template.customer,
        precision: props.template.currencyPrecision,
    });
    const [customerSelector, setCustomerSelector] = useState(false);
    const [customerCreator, setCustomerCreator] = useState(false);
    const errors = form.errors as Record<string, string>;
    const calculated = form.data.lines.map((line) =>
        calculateDocumentLine(line, precision),
    );
    const totals =
        precision === null
            ? null
            : calculateDocumentAmounts(
                  calculated.filter(completeLine),
                  precision,
              );

    const changeLines = (
        change: (lines: DocumentLineDraft[]) => DocumentLineDraft[],
    ) => {
        form.setData((current) => ({
            ...current,
            lines: change(current.lines),
        }));
    };

    const applyCustomer = (selection: DocumentCustomerSelection) => {
        const next = applyRecurringCustomer(form.data, selection);
        setCustomer(selection);
        setPrecision(next.inheritance.currencyPrecision);
        form.setData(next);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform(recurringTemplatePayload);
        form.patch(props.updateUrl, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updated = page.props.template as RecurringTemplateDraft;
                const updatedInheritance = page.props
                    .inheritance as RecurringInheritanceProps['inheritance'];
                const next = recurringTemplateFormData(
                    updated,
                    updatedInheritance,
                );
                form.setData(next);
                form.setDefaults(next);
                savedSources.current = {
                    customer: updated.customer,
                    precision: updatedInheritance.currencyPrecision,
                };
                setCustomer(updated.customer);
                setPrecision(updatedInheritance.currencyPrecision);
            },
        });
    };

    const resetDraft = () => {
        form.reset();
        form.clearErrors();
        setCustomer(savedSources.current.customer);
        setPrecision(savedSources.current.precision);
        setCustomerSelector(false);
        setCustomerCreator(false);
    };

    return (
        <>
            <form
                id={FORM_ID}
                onSubmit={submit}
                onReset={(event) => {
                    event.preventDefault();
                    resetDraft();
                }}
            >
                <Stack gap="xl">
                    <UnsavedChangesGuard
                        active={
                            form.isDirty && !form.processing && !customerCreator
                        }
                        message={props.labels.unsaved_warning}
                    />
                    {(errors.lines ||
                        errors.edit_version ||
                        errors.customer_id) && (
                        <SystemMessage
                            title={
                                errors.lines ??
                                errors.edit_version ??
                                errors.customer_id
                            }
                            tone="error"
                        />
                    )}
                    <FormSection
                        title={props.labels.identity_section}
                        description={props.labels.identity_description}
                    >
                        <Grid columns={2} gap="lg">
                            <TextField
                                label={props.labels.internal_name}
                                description={
                                    props.labels.internal_name_description
                                }
                                error={errors.internal_name}
                                input={{
                                    value: form.data.internalName,
                                    maxLength: props.limits.internalName,
                                    onChange: (event) =>
                                        form.setData(
                                            'internalName',
                                            event.target.value,
                                        ),
                                }}
                            />
                            <TextField
                                label={props.labels.customer_reference}
                                description={
                                    props.labels.customer_reference_description
                                }
                                error={errors.customer_reference}
                                input={{
                                    value: form.data.customerReference,
                                    maxLength: props.limits.customerReference,
                                    onChange: (event) =>
                                        form.setData(
                                            'customerReference',
                                            event.target.value,
                                        ),
                                }}
                            />
                        </Grid>
                    </FormSection>
                    <DocumentCustomerControls
                        customer={customer}
                        labels={props.labels}
                        onSelect={() => setCustomerSelector(true)}
                    />
                    <RecurringTemplateOverrideSections
                        {...props}
                        value={form.data.inheritance}
                        errors={errors}
                        onChange={(inheritance) => {
                            form.setData('inheritance', inheritance);
                            setPrecision(inheritance.currencyPrecision);
                        }}
                    />
                    {precision === null && (
                        <SystemMessage
                            title={props.labels.currency_required}
                            tone="warning"
                        />
                    )}
                    <DocumentLineEditor
                        lines={form.data.lines}
                        calculated={calculated}
                        totals={totals}
                        taxDefault={customer.taxDefault}
                        taxPresetOptions={props.catalogForm.taxPresetOptions}
                        currencyCode={customer.currencyCode}
                        currencyPrecision={precision}
                        productSearchUrl={props.sourceUrls.productSearch}
                        limits={props.limits}
                        labels={props.labels}
                        errors={errors}
                        onChange={changeLines}
                        onAdd={blankRecurringLine}
                    />
                    <FormActions separated>
                        <DiscardChangesDialog
                            dirty={form.isDirty}
                            processing={form.processing}
                            form={FORM_ID}
                            mode="discard"
                            labels={props.labels}
                        />
                        <SaveButton
                            processing={form.processing}
                            dirty={form.isDirty}
                            testId="save-recurring-template"
                        >
                            {props.labels.save}
                        </SaveButton>
                    </FormActions>
                </Stack>
            </form>
            <DocumentSourceDialogs
                customerOpen={customerSelector}
                customerCreatorOpen={customerCreator}
                sourceUrls={props.sourceUrls}
                inlineCustomerStoreUrl={props.inlineCustomerStoreUrl}
                customerForm={props.customerForm}
                abilities={props.sourceAbilities}
                allowCompanyDefaults={false}
                labels={props.labels}
                customerLabels={props.customerLabels}
                onCustomerOpenChange={setCustomerSelector}
                onCustomerCreatorOpenChange={setCustomerCreator}
                onCustomerSelected={applyCustomer}
                onCustomerCreated={(page) => {
                    const created = page.props
                        .inlineCreatedCustomer as DocumentCustomerSelection | null;

                    if (created !== null) {
                        setCustomerCreator(false);
                        applyCustomer(created);
                    }
                }}
            />
        </>
    );
}
