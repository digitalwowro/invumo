import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { QuoteDraftEditor } from '@/features/quotes/components/quote-draft-editor';
import type { QuoteDraft, QuoteLimits, QuoteTranslations } from '@/types/quote';

type Props = {
    quote: QuoteDraft;
    limits: QuoteLimits;
    updateUrl: string;
    status?: string;
    translations: QuoteTranslations;
};

export default function EditQuote({
    quote,
    limits,
    updateUrl,
    status,
    translations,
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
                        key={`${quote.id}:${quote.editVersion}`}
                        quote={quote}
                        limits={limits}
                        updateUrl={updateUrl}
                        labels={translations.edit}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
