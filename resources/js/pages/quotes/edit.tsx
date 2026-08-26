import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { QuoteDraftEditor } from '@/features/quotes/components/quote-draft-editor';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type {
    QuoteCatalogFormOptions,
    QuoteCurrencyOption,
    QuoteCustomerFormOptions,
    QuoteCustomerSelection,
    QuoteDraft,
    QuoteLimits,
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
    status?: string;
    translations: QuoteTranslations;
    customerTranslations: CustomerTranslations;
    catalogTranslations: CatalogTranslations;
};

export default function EditQuote({
    quote,
    limits,
    updateUrl,
    status,
    translations,
    customerTranslations,
    catalogTranslations,
    ...sourceProps
}: Props) {
    return (
        <>
            <Head title={`${translations.edit.head_title} ${quote.number}`} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={`${translations.edit.title} ${quote.number}`}
                        subtitle={translations.edit.description}
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    <SystemMessage
                        title={
                            quote.currencyCode === null
                                ? translations.edit.currency_required
                                : `${quote.currencyCode} · ${quote.issueDate ?? ''}`
                        }
                        tone={
                            quote.currencyCode === null ? 'warning' : 'neutral'
                        }
                    />
                    <QuoteDraftEditor
                        quote={quote}
                        limits={limits}
                        updateUrl={updateUrl}
                        labels={translations.edit}
                        customerLabels={customerTranslations}
                        catalogLabels={catalogTranslations}
                        {...sourceProps}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
