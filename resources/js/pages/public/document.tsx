import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { DownloadLink } from '@/components/app/download-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SecondaryText } from '@/components/app/typography';
import { OutwardDocument } from '@/components/domain/outward-document';
import type { OutwardDocument as OutwardDocumentData } from '@/types/outward-document';
import type { PublicDocumentTranslations } from '@/types/public-document';

type Props = {
    document: OutwardDocumentData;
    pdfUrl: string;
    translations: PublicDocumentTranslations;
};

export default function PublicDocument({
    document,
    pdfUrl,
    translations,
}: Props) {
    const labels = translations.page;

    return (
        <>
            <Head title={`${labels.head_title} ${document.number}`}>
                <meta name="robots" content="noindex,nofollow,noarchive" />
                <meta name="referrer" content="no-referrer" />
            </Head>
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={`${document.kind} ${document.number}`}
                        subtitle={labels.description}
                        actions={
                            <DownloadLink
                                href={pdfUrl}
                                testId="public-pdf-download"
                            >
                                <Download aria-hidden="true" />
                                {labels.download_pdf}
                            </DownloadLink>
                        }
                    />
                    <OutwardDocument document={document} />
                    <SecondaryText>{labels.provided_by}</SecondaryText>
                </Stack>
            </PageFrame>
        </>
    );
}
