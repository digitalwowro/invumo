import { Check, X } from 'lucide-react';
import { FormActions } from '@/components/app/form-actions';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { SystemMessage } from '@/components/app/system-message';
import { Button } from '@/components/ui/button';
import type {
    PublicDocumentTranslations,
    PublicQuoteDecisionState,
} from '@/types/public-document';

type Props = {
    decision: PublicQuoteDecisionState;
    labels: PublicDocumentTranslations['decision'];
    errors: Record<string, string>;
};

export function PublicQuoteDecision({ decision, labels, errors }: Props) {
    if (decision.state !== 'AVAILABLE') {
        const accepted = decision.state === 'ACCEPTED';
        const rejected = decision.state === 'REJECTED';

        return (
            <SystemMessage
                title={
                    accepted
                        ? labels.accepted_title
                        : rejected
                          ? labels.rejected_title
                          : labels.unavailable_title
                }
                description={
                    accepted
                        ? labels.accepted_description
                        : rejected
                          ? labels.rejected_description
                          : labels.unavailable_description
                }
                tone={accepted ? 'money' : rejected ? 'info' : 'neutral'}
            />
        );
    }

    return (
        <FormSection title={labels.title} description={labels.description}>
            {errors.decision && (
                <SystemMessage title={errors.decision} tone="error" />
            )}
            <form
                action={decision.submitUrl ?? ''}
                method="post"
                className="space-y-6"
            >
                <input type="hidden" name="_token" value={decision.csrfToken} />
                <input
                    type="hidden"
                    name="idempotency_key"
                    value={decision.idempotencyKey ?? ''}
                />
                <input type="hidden" name="locale" value={decision.locale} />
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label={labels.customer_name}
                        error={errors.customer_name}
                        input={{
                            name: 'customer_name',
                            defaultValue: decision.customerName,
                            autoComplete: 'name',
                            required: true,
                        }}
                    />
                    <TextField
                        label={labels.customer_email}
                        error={errors.customer_email}
                        input={{
                            name: 'customer_email',
                            type: 'email',
                            defaultValue: decision.customerEmail,
                            autoComplete: 'email',
                            required: true,
                        }}
                    />
                </div>
                <FormActions align="stretch" separated>
                    <Button
                        type="submit"
                        name="decision"
                        value="ACCEPTED"
                        data-test="public-quote-accept"
                    >
                        <Check aria-hidden="true" />
                        {labels.accept}
                    </Button>
                    <Button
                        type="submit"
                        name="decision"
                        value="REJECTED"
                        variant="destructive"
                        data-test="public-quote-reject"
                    >
                        <X aria-hidden="true" />
                        {labels.reject}
                    </Button>
                </FormActions>
            </form>
        </FormSection>
    );
}
