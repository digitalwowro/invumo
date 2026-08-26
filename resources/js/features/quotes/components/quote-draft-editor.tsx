import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { QuoteCustomerControls } from '@/features/quotes/components/quote-customer-controls';
import { QuoteDefaultsSection } from '@/features/quotes/components/quote-defaults-section';
import {
    customerFromQuote,
    quoteFormData,
} from '@/features/quotes/components/quote-draft-form-data';
import {
    calculateQuoteLine,
    completeLine,
    QuoteTotals,
} from '@/features/quotes/components/quote-draft-lines';
import { QuoteLineEditor } from '@/features/quotes/components/quote-line-editor';
import { QuoteSourceDialogs } from '@/features/quotes/components/quote-source-dialogs';
import { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type {
    QuoteCatalogFormOptions,
    QuoteCurrencyOption,
    QuoteCustomerFormOptions,
    QuoteCustomerSelection,
    QuoteDraft,
    QuoteLimits,
    QuoteLine,
    QuoteProductDefaults,
    QuoteSourceOption,
    QuoteSourceUrls,
    QuoteTranslations,
} from '@/types/quote';

type Props = {
    quote: QuoteDraft;
    limits: QuoteLimits;
    updateUrl: string;
    sourceUrls: QuoteSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineProductStoreUrl: string;
    inlineCreatedCustomer: QuoteCustomerSelection | null;
    inlineCreatedProduct: QuoteProductDefaults | null;
    sourceAbilities: { createCustomer: boolean; createProduct: boolean };
    currencyOptions: QuoteCurrencyOption[];
    languageOptions: QuoteSourceOption[];
    bankAccountOptions: QuoteSourceOption[];
    customerForm: QuoteCustomerFormOptions;
    catalogForm: QuoteCatalogFormOptions;
    labels: QuoteTranslations['edit'];
    customerLabels: CustomerTranslations;
    catalogLabels: CatalogTranslations;
};

export function QuoteDraftEditor(props: Props) {
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
        calculateQuoteLine(line, precision),
    );
    const totals =
        precision === null
            ? null
            : calculateDocumentAmounts(
                  calculated.filter(completeLine),
                  precision,
              );

    const changeLines = (change: (lines: QuoteLine[]) => QuoteLine[]) => {
        form.setData((current) => ({
            ...current,
            lines: change(current.lines),
        }));
    };

    const applyCustomer = (selection: QuoteCustomerSelection) => {
        setCustomer(selection);
        setPrecision(selection.currencyPrecision);
        form.setData((current) => ({
            ...current,
            customerId: selection.customerId,
            customerConfirmationToken: selection.confirmationToken,
            currencyCode: selection.currencyCode,
            documentLanguage: selection.documentLanguage,
        }));
    };

    const applyProduct = (index: number, defaults: QuoteProductDefaults) => {
        changeLines((lines) =>
            lines.map((line, itemIndex) =>
                itemIndex === index
                    ? {
                          ...line,
                          productServiceId: defaults.sourceProductServiceId,
                          description: defaults.description,
                          itemPrice: defaults.unitPrice ?? '',
                          unit: defaults.unit ?? '',
                          periodUnit: defaults.periodUnit,
                          taxName:
                              defaults.tax?.name ??
                              customer.taxDefault?.name ??
                              '',
                          taxPercentage:
                              defaults.tax?.percentage ??
                              customer.taxDefault?.percentage ??
                              '0',
                          taxPresetId:
                              defaults.tax?.sourceTaxPresetId ??
                              customer.taxDefault?.id ??
                              null,
                          priceStatus: defaults.priceStatus,
                      }
                    : line,
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

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            edit_version: data.editVersion,
            customer_id: data.customerId,
            customer_confirmation_token: data.customerConfirmationToken,
            currency_code: data.currencyCode,
            document_language: data.documentLanguage,
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
                    <QuoteCustomerControls
                        customer={customer}
                        labels={props.labels}
                        onSelect={() => setCustomerSelector(true)}
                    />
                    <QuoteDefaultsSection
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
                    <QuoteLineEditor
                        lines={form.data.lines}
                        calculated={calculated}
                        taxDefault={customer.taxDefault}
                        limits={props.limits}
                        labels={props.labels}
                        errors={errors}
                        onChange={changeLines}
                        onSelectProduct={(index) => {
                            setLineIndex(index);
                            setProductSelector(true);
                        }}
                    />
                    <QuoteTotals labels={props.labels} totals={totals} />
                    <FormActions separated>
                        <SubmitButton
                            processing={form.processing}
                            testId="save-quote"
                        >
                            {props.labels.save}
                        </SubmitButton>
                    </FormActions>
                </Stack>
            </form>
            <QuoteSourceDialogs
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
