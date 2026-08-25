import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { Stack } from '@/components/app/layout';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { CompanyNumberSeriesFields } from '@/features/companies/components/company-number-series-fields';
import type { CompanyOption } from '@/types/company';
import type {
    CompanyNumberPreviewContext,
    CompanyNumberSeries,
    CompanyNumberSeriesConfiguration,
    CompanyNumberSeriesLimits,
    CompanyNumberSeriesTranslations,
} from '@/types/company-number-series';

type Props = {
    series: CompanyNumberSeriesConfiguration;
    limits: CompanyNumberSeriesLimits;
    previewContext: CompanyNumberPreviewContext;
    resetPolicyOptions: CompanyOption[];
    updateUrl: string;
    labels: CompanyNumberSeriesTranslations;
};

export function CompanyNumberSeriesForm({
    series,
    limits,
    previewContext,
    resetPolicyOptions,
    updateUrl,
    labels,
}: Props) {
    const [quote, setQuote] = useState<CompanyNumberSeries>(series.quote);
    const [invoice, setInvoice] = useState<CompanyNumberSeries>(series.invoice);

    return (
        <Form
            action={updateUrl}
            method="patch"
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
        >
            {({ errors, isDirty, processing }) => (
                <Stack gap="2xl">
                    <UnsavedChangesGuard
                        active={isDirty && !processing}
                        message={labels.unsaved_warning}
                    />
                    <CompanyNumberSeriesFields
                        seriesKey="quote"
                        value={quote}
                        onChange={setQuote}
                        limits={limits}
                        previewContext={previewContext}
                        resetPolicyOptions={resetPolicyOptions}
                        errors={errors}
                        labels={labels}
                    />
                    <CompanyNumberSeriesFields
                        seriesKey="invoice"
                        value={invoice}
                        onChange={setInvoice}
                        limits={limits}
                        previewContext={previewContext}
                        resetPolicyOptions={resetPolicyOptions}
                        errors={errors}
                        labels={labels}
                    />
                    <FormActions>
                        <SubmitButton processing={processing}>
                            {labels.save}
                        </SubmitButton>
                    </FormActions>
                </Stack>
            )}
        </Form>
    );
}
