import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { DocumentTotals } from '@/components/domain/documents/document-draft-lines';
import { QuoteConversionDialog } from '@/features/quotes/components/quote-conversion-dialog';
import type { DocumentAmounts as CalculatedTotals } from '@/lib/money/document-calculation';
import type { QuoteInvoiceAllocation, QuoteTranslations } from '@/types/quote';

type Props = {
    totals: CalculatedTotals | null;
    processing: boolean;
    dirty: boolean;
    currencyCode: string | null;
    conversionUrl: string;
    conversionKey: string;
    allocation: QuoteInvoiceAllocation;
    editorLabels: QuoteTranslations['edit'];
    conversionLabels: QuoteTranslations['conversion'];
};

export function QuoteDraftSummary(props: Props) {
    return (
        <>
            <DocumentTotals labels={props.editorLabels} totals={props.totals} />
            <FormActions separated>
                <QuoteConversionDialog
                    url={props.conversionUrl}
                    creationKey={props.conversionKey}
                    allocation={props.allocation}
                    currencyCode={props.currencyCode}
                    dirty={props.dirty}
                    labels={props.conversionLabels}
                />
                <SubmitButton processing={props.processing} testId="save-quote">
                    {props.editorLabels.save}
                </SubmitButton>
            </FormActions>
        </>
    );
}
