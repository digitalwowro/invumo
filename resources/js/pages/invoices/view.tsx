import { Head } from '@inertiajs/react';
import { Download, FolderOpen } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { DownloadLink } from '@/components/app/download-link';
import { Stack } from '@/components/app/layout';
import {
    ResourceWorkspace,
    ResourceWorkspaceHeader,
} from '@/components/app/resource-workspace';
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
            <ResourceWorkspace>
                <Stack gap="2xl">
                    <ResourceWorkspaceHeader
                        breadcrumbs={[
                            {
                                title: props.translations.index.title,
                                href: props.indexUrl,
                            },
                            {
                                title: props.document.number,
                                href: props.indexUrl,
                            },
                        ]}
                        title={`${labels.title} ${props.document.number}`}
                        description={labels.description}
                        actions={
                            <>
                                <DownloadLink
                                    href={props.pdfUrl}
                                    testId="pdf-download"
                                >
                                    <Download aria-hidden="true" />
                                    {labels.download_pdf}
                                </DownloadLink>
                                {props.editUrl && (
                                    <ActionLink href={props.editUrl}>
                                        <FolderOpen aria-hidden="true" />
                                        {labels.edit}
                                    </ActionLink>
                                )}
                            </>
                        }
                    />
                    <OutwardDocument document={props.document} />
                </Stack>
            </ResourceWorkspace>
        </>
    );
}
