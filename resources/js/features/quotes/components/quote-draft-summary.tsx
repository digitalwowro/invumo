import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { QuoteConversionDialog } from '@/features/quotes/components/quote-conversion-dialog';
import type { QuoteInvoiceAllocation, QuoteTranslations } from '@/types/quote';

type Props = {
    processing: boolean;
    dirty: boolean;
    currencyCode: string | null;
    conversionUrl: string;
    conversionKey: string;
    allocation: QuoteInvoiceAllocation;
    saveLabel: string;
    conversionLabels: QuoteTranslations['conversion'];
    formId?: string;
    separated?: boolean;
};

export function QuoteDraftSummary(props: Props) {
    return (
        <FormActions separated={props.separated ?? true}>
            <SubmitButton
                processing={props.processing}
                testId="save-quote"
                form={props.formId}
            >
                {props.saveLabel}
            </SubmitButton>
            <QuoteConversionDialog
                url={props.conversionUrl}
                creationKey={props.conversionKey}
                allocation={props.allocation}
                currencyCode={props.currencyCode}
                dirty={props.dirty}
                labels={props.conversionLabels}
            />
        </FormActions>
    );
}
