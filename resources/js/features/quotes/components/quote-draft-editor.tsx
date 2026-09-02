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
import { DocumentSourceDialogs } from '@/components/domain/documents/document-source-dialogs';
import { DocumentSourceLayout } from '@/components/domain/documents/document-source-layout';
import { documentTaxDefaultChange } from '@/components/domain/documents/document-tax-options';
import { useDocumentEditorReports } from '@/components/domain/documents/use-document-editor-reports';
import { QuoteDetailsSection } from '@/features/quotes/components/quote-details-section';
import type { QuoteDraftEditorProps } from '@/features/quotes/components/quote-draft-editor-props';
import {
    applyCustomerDefaults,
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
} from '@/types/quote';

export function QuoteDraftEditor(props: QuoteDraftEditorProps) {
    const form = useForm(quoteFormData(props.quote));
    const [customer, setCustomer] = useState(customerFromQuote(props.quote));
    const [taxDefault, setTaxDefault] = useState(props.quote.taxDefault);
    const [precision, setPrecision] = useState(props.quote.currencyPrecision);
    const savedSources = useRef({
        customer: customerFromQuote(props.quote),
        taxDefault: props.quote.taxDefault,
        precision: props.quote.currencyPrecision,
    });
    const [customerSelector, setCustomerSelector] = useState(false);
    const [customerCreator, setCustomerCreator] = useState(false);
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
        onDirtyChange: props.onDirtyChange,
        onProcessingChange: props.onProcessingChange,
        onLineCountChange: props.onLineCountChange,
    });

    const changeLines = (change: (lines: QuoteLine[]) => QuoteLine[]) => {
        form.setData((current) => ({
            ...current,
            lines: change(current.lines),
        }));
    };

    const applyCustomer = (selection: QuoteCustomerSelection) => {
        setCustomer(selection);
        setTaxDefault(selection.taxDefault);
        setPrecision(selection.currencyPrecision);
        form.setData((current) => applyCustomerDefaults(current, selection));
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
            changeQuoteDetail(
                current,
                field as Parameters<typeof changeQuoteDetail>[1],
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
                const nextCustomer = customerFromQuote(updated);
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
            <QuoteDetailsSection
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
                    {editorError && (
                        <SystemMessage title={editorError} tone="error" />
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
                        onAdd={blankQuoteLine}
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
                            resetLabels={props.labels}
                            formId={props.formId}
                        />
                    )}
                </Stack>
            </form>
            <DocumentSourceDialogs
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
                onCustomerCreated={(page) => {
                    const created = page.props
                        .inlineCreatedCustomer as QuoteCustomerSelection | null;

                    if (created === null) {
                        return;
                    }

                    setCustomerCreator(false);
                    applyCustomer(created);
                }}
            />
        </>
    );
}
