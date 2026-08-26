import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { DocumentCustomerControls } from '@/components/domain/documents/document-customer-controls';
import { DocumentDefaultsSection } from '@/components/domain/documents/document-defaults-section';
import {
    calculateDocumentLine,
    completeLine,
    DocumentTotals,
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
import { InvoiceIssueDialog } from '@/features/invoices/components/invoice-issue-dialog';
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
    customerLabels: CustomerTranslations;
    catalogLabels: CatalogTranslations;
};

export function InvoiceDraftEditor(props: Props) {
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
            ),
        );
    };

    const changeDefault = (field: string, value: string | null) => {
        form.setData(field as keyof typeof form.data, value as never);

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
            <form onSubmit={submit}>
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
                    <DocumentDefaultsSection
                        customer={customer}
                        currencyCode={form.data.currencyCode}
                        documentLanguage={form.data.documentLanguage}
                        bankAccountId={form.data.bankAccountId}
                        bankAccountLabel={
                            props.invoice.bankAccount?.label ?? null
                        }
                        termsAndConditions={form.data.termsAndConditions}
                        notes={form.data.notes}
                        currencyOptions={props.currencyOptions}
                        languageOptions={props.languageOptions}
                        bankAccountOptions={props.bankAccountOptions}
                        termsLimit={props.limits.termsAndConditions}
                        notesLimit={props.limits.notes}
                        labels={props.labels}
                        errors={errors}
                        onChange={changeDefault}
                    />
                    <DocumentLineEditor
                        lines={form.data.lines}
                        calculated={calculated}
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
                    <DocumentTotals labels={props.labels} totals={totals} />
                    <FormActions separated>
                        <SubmitButton
                            processing={form.processing}
                            testId="save-invoice"
                        >
                            {props.labels.save}
                        </SubmitButton>
                        {props.invoice.lifecycle === 'DRAFT' && (
                            <InvoiceIssueDialog
                                key={form.data.editVersion}
                                url={props.issueUrl}
                                editVersion={form.data.editVersion}
                                labels={props.issueLabels}
                                disabled={form.isDirty || form.processing}
                            />
                        )}
                    </FormActions>
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
