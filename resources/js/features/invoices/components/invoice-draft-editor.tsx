import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
import { InvoiceDetailsSection } from '@/features/invoices/components/invoice-details-section';
import {
    applyInvoiceCustomerDefaults,
    applyInvoiceProductDefaults,
    blankInvoiceLine,
    changeInvoiceDetail,
    customerFromInvoice,
    invoiceFormData,
    invoiceRequestData,
} from '@/features/invoices/components/invoice-draft-form-data';
import { InvoiceEditorLifecycleActions } from '@/features/invoices/components/invoice-editor-lifecycle-actions';
import { InvoiceSourceDialogs } from '@/features/invoices/components/invoice-source-dialogs';
import { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type {
    InvoiceCatalogFormOptions,
    InvoiceCurrencyOption,
    InvoiceCustomerFormOptions,
    InvoiceCustomerSelection,
    InvoiceDraft,
    InvoiceLimits,
    InvoiceLifecycleActions,
    InvoiceLine,
    InvoiceProductDefaults,
    InvoiceSourceOption,
    InvoiceSourceUrls,
    InvoiceTranslations,
} from '@/types/invoice';

type Props = {
    invoice: InvoiceDraft;
    limits: InvoiceLimits;
    updateUrl: string;
    issueUrl: string;
    lifecycleActions: InvoiceLifecycleActions;
    sourceUrls: InvoiceSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineProductStoreUrl: string;
    inlineCreatedCustomer: InvoiceCustomerSelection | null;
    inlineCreatedProduct: InvoiceProductDefaults | null;
    sourceAbilities: { createCustomer: boolean; createProduct: boolean };
    currencyOptions: InvoiceCurrencyOption[];
    languageOptions: InvoiceSourceOption[];
    bankAccountOptions: InvoiceSourceOption[];
    customerForm: InvoiceCustomerFormOptions;
    catalogForm: InvoiceCatalogFormOptions;
    labels: InvoiceTranslations['edit'];
    issueLabels: InvoiceTranslations['issue'];
    lifecycleLabels: InvoiceTranslations['lifecycle'];
    customerLabels: CustomerTranslations;
    catalogLabels: CatalogTranslations;
    onDirtyChange?: (dirty: boolean) => void;
    onProcessingChange?: (processing: boolean) => void;
    onLineCountChange?: (count: number) => void;
    formId?: string;
    showActions?: boolean;
};

export function InvoiceDraftEditor(props: Props) {
    const { onDirtyChange, onProcessingChange, onLineCountChange } = props;
    const form = useForm(invoiceFormData(props.invoice));
    const [customer, setCustomer] = useState(
        customerFromInvoice(props.invoice),
    );
    const [precision, setPrecision] = useState(props.invoice.currencyPrecision);
    const [customerSelector, setCustomerSelector] = useState(false);
    const [customerCreator, setCustomerCreator] = useState(false);
    const [productSelector, setProductSelector] = useState(false);
    const [productCreator, setProductCreator] = useState(false);
    const [lineIndex, setLineIndex] = useState<number | null>(null);
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

    useEffect(() => {
        onDirtyChange?.(form.isDirty);
    }, [form.isDirty, onDirtyChange]);

    useEffect(() => {
        onProcessingChange?.(form.processing);
    }, [form.processing, onProcessingChange]);

    useEffect(() => {
        onLineCountChange?.(form.data.lines.length);
    }, [form.data.lines.length, onLineCountChange]);

    const changeLines = (change: (lines: InvoiceLine[]) => InvoiceLine[]) => {
        form.setData((current) => ({
            ...current,
            lines: change(current.lines),
        }));
    };

    const applyCustomer = (selection: InvoiceCustomerSelection) => {
        setCustomer(selection);
        setPrecision(selection.currencyPrecision);
        form.setData((current) =>
            applyInvoiceCustomerDefaults(current, selection),
        );
    };

    const applyProduct = (index: number, defaults: InvoiceProductDefaults) => {
        changeLines((lines) =>
            applyInvoiceProductDefaults(
                lines,
                index,
                defaults,
                customer.taxDefault,
                precision,
            ),
        );
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

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform(invoiceRequestData);
        form.patch(props.updateUrl, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updated = page.props.invoice as InvoiceDraft;
                const next = invoiceFormData(updated);
                form.setData(next);
                form.setDefaults(next);
                setCustomer(customerFromInvoice(updated));
                setPrecision(updated.currencyPrecision);
            },
        });
    };

    return (
        <>
            <form id={props.formId} onSubmit={submit}>
                <Stack gap="xl">
                    <UnsavedChangesGuard
                        active={
                            form.isDirty &&
                            !form.processing &&
                            !customerCreator &&
                            !productCreator
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
                    <DocumentCustomerControls
                        customer={customer}
                        labels={props.labels}
                        onSelect={() => setCustomerSelector(true)}
                    />
                    <InvoiceDetailsSection
                        issueDate={form.data.issueDate}
                        paymentTermDays={form.data.paymentTermDays}
                        dueDate={form.data.dueDate}
                        customerReference={form.data.customerReference}
                        limits={props.limits}
                        labels={props.labels}
                        errors={errors}
                        onChange={changeDetail}
                    />
                    <DocumentLineEditor
                        lines={form.data.lines}
                        calculated={calculated}
                        totals={totals}
                        taxDefault={customer.taxDefault}
                        limits={props.limits}
                        labels={props.labels}
                        errors={errors}
                        onChange={changeLines}
                        onAdd={blankInvoiceLine}
                        onSelectProduct={(index) => {
                            setLineIndex(index);
                            setProductSelector(true);
                        }}
                    />
                    <DocumentDefaultsSection
                        currencyCode={form.data.currencyCode}
                        documentLanguage={form.data.documentLanguage}
                        bankAccountId={form.data.bankAccountId}
                        bankAccountLabel={
                            props.invoice.bankAccount?.label ?? null
                        }
                        taxDefault={customer.taxDefault}
                        recipientCount={customer.recipientCount}
                        emailAttachmentMode={customer.emailAttachmentMode}
                        termsAndConditions={form.data.termsAndConditions}
                        notes={form.data.notes}
                        isCustomized={form.data.defaultsCustomized}
                        currencyOptions={props.currencyOptions}
                        languageOptions={props.languageOptions}
                        bankAccountOptions={props.bankAccountOptions}
                        termsLimit={props.limits.termsAndConditions}
                        notesLimit={props.limits.notes}
                        labels={props.labels}
                        errors={errors}
                        onChange={changeDefault}
                    />
                    {props.showActions !== false && (
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
                        />
                    )}
                </Stack>
            </form>
            <InvoiceSourceDialogs
                customerOpen={customerSelector}
                customerCreatorOpen={customerCreator}
                productOpen={productSelector}
                productCreatorOpen={productCreator}
                currencyCode={form.data.currencyCode}
                sourceUrls={props.sourceUrls}
                inlineCustomerStoreUrl={props.inlineCustomerStoreUrl}
                inlineProductStoreUrl={props.inlineProductStoreUrl}
                customerForm={props.customerForm}
                catalogForm={props.catalogForm}
                abilities={props.sourceAbilities}
                labels={props.labels}
                customerLabels={props.customerLabels}
                catalogLabels={props.catalogLabels}
                onCustomerOpenChange={setCustomerSelector}
                onCustomerCreatorOpenChange={setCustomerCreator}
                onProductOpenChange={setProductSelector}
                onProductCreatorOpenChange={setProductCreator}
                onCustomerSelected={applyCustomer}
                onProductSelected={(defaults) =>
                    lineIndex !== null && applyProduct(lineIndex, defaults)
                }
            />
        </>
    );
}
