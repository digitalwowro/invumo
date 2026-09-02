import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { DocumentCustomerControls } from '@/components/domain/documents/document-customer-controls';
import { DocumentDefaultsSection } from '@/components/domain/documents/document-defaults-section';
import {
    calculateDocumentLine,
    completeLine,
} from '@/components/domain/documents/document-draft-lines';
import { DocumentLineEditor } from '@/components/domain/documents/document-line-editor';
import { DocumentSourceLayout } from '@/components/domain/documents/document-source-layout';
import { documentTaxDefaultChange } from '@/components/domain/documents/document-tax-options';
import { useDocumentEditorReports } from '@/components/domain/documents/use-document-editor-reports';
import { InvoiceDetailsSection } from '@/features/invoices/components/invoice-details-section';
import type { InvoiceDraftEditorProps } from '@/features/invoices/components/invoice-draft-editor-props';
import {
    applyInvoiceCustomerDefaults,
    blankInvoiceLine,
    changeInvoiceDetail,
    customerFromInvoice,
    invoiceFormData,
    invoiceRequestData,
} from '@/features/invoices/components/invoice-draft-form-data';
import { InvoiceEditorLifecycleActions } from '@/features/invoices/components/invoice-editor-lifecycle-actions';
import { InvoiceSourceDialogs } from '@/features/invoices/components/invoice-source-dialogs';
import { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import type {
    InvoiceCustomerSelection,
    InvoiceDraft,
    InvoiceLine,
} from '@/types/invoice';

export function InvoiceDraftEditor(props: InvoiceDraftEditorProps) {
    const { onDirtyChange, onProcessingChange, onLineCountChange } = props;
    const form = useForm(invoiceFormData(props.invoice));
    const [customer, setCustomer] = useState(
        customerFromInvoice(props.invoice),
    );
    const [taxDefault, setTaxDefault] = useState(props.invoice.taxDefault);
    const [precision, setPrecision] = useState(props.invoice.currencyPrecision);
    const savedSources = useRef({
        customer: customerFromInvoice(props.invoice),
        taxDefault: props.invoice.taxDefault,
        precision: props.invoice.currencyPrecision,
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

    useDocumentEditorReports({
        dirty: form.isDirty,
        processing: form.processing,
        lineCount: form.data.lines.length,
        onDirtyChange,
        onProcessingChange,
        onLineCountChange,
    });

    const changeLines = (change: (lines: InvoiceLine[]) => InvoiceLine[]) => {
        form.setData((current) => ({
            ...current,
            lines: change(current.lines),
        }));
    };

    const applyCustomer = (selection: InvoiceCustomerSelection) => {
        setCustomer(selection);
        setTaxDefault(selection.taxDefault);
        setPrecision(selection.currencyPrecision);
        form.setData((current) =>
            applyInvoiceCustomerDefaults(current, selection),
        );
    };

    const changeTaxDefault = (value: string) => {
        const change = documentTaxDefaultChange(
            value,
            props.catalogForm.taxPresetOptions,
            taxDefault,
        );
        setTaxDefault(change.taxDefault);
        form.setData(change.update);
    };

    const changeDefault = (field: string, value: string | null) => {
        form.setData((current) => ({
            ...current,
            [field]: value,
            defaultsCustomized:
                current.defaultsCustomized ||
                current[field as keyof typeof current] !== value,
        }));

        if (field === 'currencyCode') {
            setPrecision(
                props.currencyOptions.find((option) => option.value === value)
                    ?.precision ?? null,
            );
        }
    };

    const changeDetail = (field: string, value: string) => {
        form.setData((current) =>
            changeInvoiceDetail(
                current,
                field as Parameters<typeof changeInvoiceDetail>[1],
                value,
            ),
        );
    };

    const resetDraft = () => {
        form.reset();
        form.clearErrors();
        setCustomer(savedSources.current.customer);
        setTaxDefault(savedSources.current.taxDefault);
        setPrecision(savedSources.current.precision);
        setCustomerSelector(false);
        setCustomerCreator(false);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...invoiceRequestData(data),
            ...(props.creation ? { creation_key: props.creation.key } : {}),
        }));

        if (props.creation) {
            form.post(props.creation.url, { preserveScroll: true });

            return;
        }

        form.patch(props.updateUrl, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updated = page.props.invoice as InvoiceDraft;
                const next = invoiceFormData(updated);
                form.setData(next);
                form.setDefaults(next);
                const nextCustomer = customerFromInvoice(updated);
                savedSources.current = {
                    customer: nextCustomer,
                    taxDefault: updated.taxDefault,
                    precision: updated.currencyPrecision,
                };
                setCustomer(nextCustomer);
                setTaxDefault(updated.taxDefault);
                setPrecision(updated.currencyPrecision);
            },
        });
    };
    const sourceSections = (
        <>
            <DocumentCustomerControls
                customer={customer}
                labels={props.labels}
                onSelect={() => setCustomerSelector(true)}
            />
            <InvoiceDetailsSection
                {...form.data}
                currencyOptions={props.currencyOptions}
                taxDefault={taxDefault}
                taxPresetOptions={props.catalogForm.taxPresetOptions}
                limits={props.limits}
                labels={props.labels}
                errors={errors}
                onChange={changeDetail}
                onDefaultChange={changeDefault}
                onTaxDefaultChange={changeTaxDefault}
            />
        </>
    );

    return (
        <>
            <form
                id={props.formId}
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
                        errors.customer_id ||
                        errors.invoice) && (
                        <SystemMessage
                            title={
                                errors.lines ??
                                errors.edit_version ??
                                errors.customer_id ??
                                errors.invoice
                            }
                            tone="error"
                        />
                    )}
                    <DocumentSourceLayout
                        aside={props.workspaceAside?.({
                            calculated,
                            totals,
                            lines: form.data.lines,
                            currencyCode: form.data.currencyCode,
                            currencyPrecision: precision,
                            dirty: form.isDirty,
                        })}
                    >
                        {sourceSections}
                    </DocumentSourceLayout>
                    <DocumentLineEditor
                        lines={form.data.lines}
                        calculated={calculated}
                        totals={totals}
                        taxDefault={taxDefault}
                        taxPresetOptions={props.catalogForm.taxPresetOptions}
                        currencyCode={form.data.currencyCode}
                        currencyPrecision={precision}
                        productSearchUrl={props.sourceUrls.productSearch}
                        limits={props.limits}
                        labels={props.labels}
                        errors={errors}
                        onChange={changeLines}
                        onAdd={blankInvoiceLine}
                    />
                    <DocumentDefaultsSection
                        {...form.data}
                        isCustomized={form.data.defaultsCustomized}
                        languageOptions={props.languageOptions}
                        bankAccountOptions={props.bankAccountOptions}
                        termsLimit={props.limits.termsAndConditions}
                        notesLimit={props.limits.notes}
                        labels={props.labels}
                        errors={errors}
                        onChange={changeDefault}
                    />
                    {props.showActions !== false &&
                        props.lifecycleActions &&
                        props.issueUrl && (
                            <InvoiceEditorLifecycleActions
                                lifecycle={props.invoice.lifecycle}
                                lifecycleActions={props.lifecycleActions}
                                issueUrl={props.issueUrl}
                                editVersion={form.data.editVersion}
                                dirty={form.isDirty}
                                processing={form.processing}
                                saveLabel={props.labels.save}
                                issueLabels={props.issueLabels}
                                lifecycleLabels={props.lifecycleLabels}
                                resetLabels={props.labels}
                                formId={props.formId}
                            />
                        )}
                </Stack>
            </form>
            <InvoiceSourceDialogs
                customerOpen={customerSelector}
                customerCreatorOpen={customerCreator}
                sourceUrls={props.sourceUrls}
                inlineCustomerStoreUrl={props.inlineCustomerStoreUrl}
                customerForm={props.customerForm}
                abilities={props.sourceAbilities}
                labels={props.labels}
                customerLabels={props.customerLabels}
                onCustomerOpenChange={setCustomerSelector}
                onCustomerCreatorOpenChange={setCustomerCreator}
                onCustomerSelected={applyCustomer}
            />
        </>
    );
}
