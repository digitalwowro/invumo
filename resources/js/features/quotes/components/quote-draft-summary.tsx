import { DiscardChangesDialog } from '@/components/app/discard-changes-dialog';
import { FormActions, SaveButton } from '@/components/app/form-actions';
import { QuoteConversionDialog } from '@/features/quotes/components/quote-conversion-dialog';
import type { DocumentResetLabels } from '@/types/document';
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
    resetLabels?: DocumentResetLabels;
};

export function QuoteDraftSummary(props: Props) {
    return (
        <FormActions separated={props.separated ?? true}>
            {props.formId && props.resetLabels ? (
                <DiscardChangesDialog
                    dirty={props.dirty}
                    processing={props.processing}
                    form={props.formId}
                    mode="discard"
                    labels={props.resetLabels}
                />
            ) : null}
            <SaveButton
                processing={props.processing}
                dirty={props.dirty}
                testId="save-quote"
                form={props.formId}
            >
                {props.saveLabel}
            </SaveButton>
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
