import { Head } from '@inertiajs/react';
import { Download, Pencil } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { OutwardDocument } from '@/components/domain/outward-document';
import type { InvoiceTranslations } from '@/types/invoice';
import type { OutwardDocument as OutwardDocumentData } from '@/types/outward-document';

type Props = {
    document: OutwardDocumentData;
    editUrl: string | null;
    indexUrl: string;
    pdfUrl: string;
    translations: InvoiceTranslations;
};

export default function ViewInvoice(props: Props) {
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
                                <ActionLink
                                    href={props.pdfUrl}
                                    variant="secondary"
                                >
                                    <Download aria-hidden="true" />
                                    {labels.download_pdf}
                                </ActionLink>
                                {props.editUrl && (
                                    <ActionLink href={props.editUrl}>
                                        <Pencil aria-hidden="true" />
                                        {labels.edit}
                                    </ActionLink>
                                )}
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
