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
import { DocumentSourceDialogs } from '@/components/domain/documents/document-source-dialogs';
import { QuoteDetailsSection } from '@/features/quotes/components/quote-details-section';
import type { QuoteDraftEditorProps } from '@/features/quotes/components/quote-draft-editor-props';
import {
    applyCustomerDefaults,
    applyProductDefaults,
    blankQuoteLine,
    changeQuoteDetail,
    customerFromQuote,
    quoteFormData,
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
    const { onDirtyChange } = props;
    const form = useForm(quoteFormData(props.quote));
    const [customer, setCustomer] = useState(customerFromQuote(props.quote));
    const [precision, setPrecision] = useState(props.quote.currencyPrecision);
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
            applyProductDefaults(lines, index, defaults, customer.taxDefault),
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
            edit_version: data.editVersion,
            customer_id: data.customerId,
            customer_confirmation_token: data.customerConfirmationToken,
            currency_code: data.currencyCode,
            document_language: data.documentLanguage,
            issue_date: data.issueDate || null,
            validity_days:
                data.validityDays === '' ? null : Number(data.validityDays),
            valid_until: data.validUntil || null,
            customer_reference: data.customerReference || null,
            bank_account_id: data.bankAccountId,
            terms_and_conditions: data.termsAndConditions,
            notes: data.notes,
            lines: data.lines.map((line) => ({
                id: line.id,
                product_service_id: line.productServiceId,
                description: line.description,
                item_price: line.itemPrice,
                quantity: line.quantity,
                unit: line.unit,
                period_unit: line.periodUnit,
                period_quantity: line.periodQuantity,
                discount_percentage: line.discountPercentage,
                tax_name: line.taxName,
                tax_percentage: line.taxPercentage,
                tax_preset_id: line.taxPresetId,
            })),
        }));
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
                    <DocumentDefaultsSection
                        customer={customer}
                        currencyCode={form.data.currencyCode}
                        documentLanguage={form.data.documentLanguage}
                        bankAccountId={form.data.bankAccountId}
                        bankAccountLabel={
                            props.quote.bankAccount?.label ?? null
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
                        onAdd={blankQuoteLine}
                        onSelectProduct={(index) => {
                            setLineIndex(index);
                            setProductSelector(true);
                        }}
                    />
                    <QuoteDraftSummary
                        totals={totals}
                        processing={form.processing}
                        dirty={form.isDirty}
                        currencyCode={props.quote.currencyCode}
                        conversionUrl={props.conversion.url}
                        conversionKey={props.conversion.creationKey}
                        allocation={props.conversion.allocation}
                        editorLabels={props.labels}
                        conversionLabels={props.conversionLabels}
                    />
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
