import { Head } from '@inertiajs/react';
import { Download, Pencil } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { DownloadLink } from '@/components/app/download-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { OutwardDocument } from '@/components/domain/outward-document';
import type { OutwardDocument as OutwardDocumentData } from '@/types/outward-document';
import type { QuoteTranslations } from '@/types/quote';

type Props = {
    document: OutwardDocumentData;
    editUrl: string;
    indexUrl: string;
    pdfUrl: string;
    translations: QuoteTranslations;
};

export default function ViewQuote(props: Props) {
    const labels = props.translations.representation;

    return (
        <>
            <Head title={`${labels.head_title} ${props.document.number}`} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={`${labels.title} ${props.document.number}`}
                        subtitle={labels.description}
                        actions={
                            <>
                                <DownloadLink
                                    href={props.pdfUrl}
                                    testId="pdf-download"
                                >
                                    <Download aria-hidden="true" />
                                    {labels.download_pdf}
                                </DownloadLink>
                                <ActionLink href={props.editUrl}>
                                    <Pencil aria-hidden="true" />
                                    {labels.edit}
                                </ActionLink>
                            </>
                        }
                    />
                    <OutwardDocument document={props.document} />
                    <ActionLink href={props.indexUrl} variant="ghost">
                        {labels.back}
                    </ActionLink>
                </Stack>
            </PageFrame>
        </>
    );
}
