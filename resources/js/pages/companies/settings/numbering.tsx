import { Head } from '@inertiajs/react';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SystemMessage } from '@/components/app/system-message';
import { CompanyNumberSeriesForm } from '@/features/companies/components/company-number-series-form';
import { QuoteCounterForm } from '@/features/companies/components/quote-counter-form';
import type { CompaniesUiTranslations, CompanyOption } from '@/types/company';
import type {
    CompanyNumberPreviewContext,
    CompanyNumberSeriesConfiguration,
    CompanyNumberSeriesLimits,
    QuoteNumberCounter,
} from '@/types/company-number-series';

type Props = {
    numberSeries: CompanyNumberSeriesConfiguration;
    numberSeriesLimits: CompanyNumberSeriesLimits;
    previewContext: CompanyNumberPreviewContext;
    resetPolicyOptions: CompanyOption[];
    updateUrl: string;
    quoteCounter: QuoteNumberCounter | null;
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyNumbering({
    numberSeries,
    numberSeriesLimits,
    previewContext,
    resetPolicyOptions,
    updateUrl,
    quoteCounter,
    status,
    translations,
}: Props) {
    const labels = translations.settings.numbering;

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                <SectionHeader
                    title={labels.title}
                    description={labels.description}
                />
                {status && <SystemMessage title={status} tone="money" />}
                <CompanyNumberSeriesForm
                    key={`${numberSeries.quote.id}:${numberSeries.invoice.id}`}
                    series={numberSeries}
                    limits={numberSeriesLimits}
                    previewContext={previewContext}
                    resetPolicyOptions={resetPolicyOptions}
                    updateUrl={updateUrl}
                    labels={labels}
                />
                {quoteCounter ? (
                    <QuoteCounterForm counter={quoteCounter} labels={labels} />
                ) : (
                    <FormSection
                        title={labels.counter_title}
                        description={labels.counter_empty}
                    >
                        <span />
                    </FormSection>
                )}
            </Stack>
        </>
    );
}
