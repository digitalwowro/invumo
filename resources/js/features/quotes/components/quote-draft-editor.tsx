import { useForm } from '@inertiajs/react';
import { useState } from 'react';
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
import { DocumentSourceDialogs } from '@/components/domain/documents/document-source-dialogs';
import { useDocumentEditorReports } from '@/components/domain/documents/use-document-editor-reports';
import { QuoteDetailsSection } from '@/features/quotes/components/quote-details-section';
import type { QuoteDraftEditorProps } from '@/features/quotes/components/quote-draft-editor-props';
import {
    applyCustomerDefaults,
    applyProductDefaults,
    blankQuoteLine,
    changeQuoteDetail,
    customerFromQuote,
    quoteFormData,
    quoteRequestData,
} from '@/features/quotes/components/quote-draft-form-data';
import { QuoteDraftSummary } from '@/features/quotes/components/quote-draft-summary';
import { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import type {
    QuoteCustomerSelection,
    QuoteDraft,
    QuoteLine,
    QuoteProductDefaults,
} from '@/types/quote';

export function QuoteDraftEditor(props: QuoteDraftEditorProps) {
    const { onDirtyChange, onProcessingChange, onLineCountChange } = props;
    const form = useForm(quoteFormData(props.quote));
    const [customer, setCustomer] = useState(customerFromQuote(props.quote));
    const [precision, setPrecision] = useState(props.quote.currencyPrecision);
    const [customerSelector, setCustomerSelector] = useState(false);
    const [customerCreator, setCustomerCreator] = useState(false);
    const [productSelector, setProductSelector] = useState(false);
    const [productCreator, setProductCreator] = useState(false);
    const [lineIndex, setLineIndex] = useState<number | null>(null);
    const errors = form.errors as Record<string, string>;
    const editorError =
        errors.lines ??
        errors.edit_version ??
        errors.customer_id ??
        errors.quote;
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

    const changeLines = (change: (lines: QuoteLine[]) => QuoteLine[]) => {
        form.setData((current) => ({
            ...current,
            lines: change(current.lines),
        }));
    };

    const applyCustomer = (selection: QuoteCustomerSelection) => {
        setCustomer(selection);
        setPrecision(selection.currencyPrecision);
        form.setData((current) => applyCustomerDefaults(current, selection));
    };

    const applyProduct = (index: number, defaults: QuoteProductDefaults) => {
        changeLines((lines) =>
            applyProductDefaults(
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
            changeQuoteDetail(
                current,
                field as Parameters<typeof changeQuoteDetail>[1],
                value,
            ),
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...quoteRequestData(data),
            ...(props.creation ? { creation_key: props.creation.key } : {}),
        }));

        if (props.creation) {
            form.post(props.creation.url, { preserveScroll: true });

            return;
        }

        form.patch(props.updateUrl, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updated = page.props.quote as QuoteDraft;
                const next = quoteFormData(updated);
                form.setData(next);
                form.setDefaults(next);
                setCustomer(customerFromQuote(updated));
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
                    {editorError && (
                        <SystemMessage title={editorError} tone="error" />
                    )}
                    <DocumentCustomerControls
                        customer={customer}
                        labels={props.labels}
                        onSelect={() => setCustomerSelector(true)}
                    />
                    <QuoteDetailsSection
                        issueDate={form.data.issueDate}
                        validityDays={form.data.validityDays}
                        validUntil={form.data.validUntil}
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
                        onAdd={blankQuoteLine}
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
                            props.quote.bankAccount?.label ?? null
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
                    {props.showActions !== false && props.conversion && (
                        <QuoteDraftSummary
                            processing={form.processing}
                            dirty={form.isDirty}
                            currencyCode={props.quote.currencyCode}
                            conversionUrl={props.conversion.url}
                            conversionKey={props.conversion.creationKey}
                            allocation={props.conversion.allocation}
                            saveLabel={props.labels.save}
                            conversionLabels={props.conversionLabels}
                        />
                    )}
                </Stack>
            </form>
            <DocumentSourceDialogs
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
                onCustomerCreated={(page) => {
                    const created = page.props
                        .inlineCreatedCustomer as QuoteCustomerSelection | null;

                    if (created === null) {
                        return;
                    }

                    setCustomerCreator(false);
                    applyCustomer(created);
                }}
                onProductSelected={(defaults) =>
                    lineIndex !== null && applyProduct(lineIndex, defaults)
                }
                onProductCreated={(page) => {
                    const created = page.props
                        .inlineCreatedProduct as QuoteProductDefaults | null;

                    if (created === null || lineIndex === null) {
                        return;
                    }

                    applyProduct(lineIndex, created);
                    setProductCreator(false);
                }}
            />
        </>
    );
}
