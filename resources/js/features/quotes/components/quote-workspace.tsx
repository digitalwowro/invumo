import { Download, Eye, Send } from 'lucide-react';
import { useState } from 'react';
import { ActionLink } from '@/components/app/action-link';
import { DocumentWorkspaceHeader } from '@/components/app/document-workspace-header';
import { DownloadLink } from '@/components/app/download-link';
import { PageFrame } from '@/components/app/page-frame';
import { WorkspaceTabs } from '@/components/app/workspace-tabs';
import { StatusBadge } from '@/components/domain/status-badge';
import { Button } from '@/components/ui/button';
import { Tabs } from '@/components/ui/tabs';
import { QuoteDeleteDialog } from '@/features/quotes/components/quote-delete-dialog';
import { QuoteDraftSummary } from '@/features/quotes/components/quote-draft-summary';
import { QuoteLifecycleDialog } from '@/features/quotes/components/quote-lifecycle-dialog';
import { QuoteWorkspaceContent } from '@/features/quotes/components/quote-workspace-content';
import { QUOTE_FORM_ID } from '@/features/quotes/components/quote-workspace-types';
import type {
    QuoteWorkspaceComposedProps,
    QuoteWorkspaceTab,
} from '@/features/quotes/components/quote-workspace-types';
import type { Status } from '@/types/status';

export function QuoteWorkspace(props: QuoteWorkspaceComposedProps) {
    const [dirty, setDirty] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [lineCount, setLineCount] = useState(props.quote.lines.length);
    const [tab, setTab] = useState<QuoteWorkspaceTab>('build');
    const labels = props.translations;
    const workspace = labels.workspace;
    const invoiceCount = props.invoiceAllocation.invoices.length;

    return (
        <div className="min-h-full bg-page">
            <PageFrame width="full">
                <Tabs
                    value={tab}
                    onValueChange={(value) =>
                        setTab(value as QuoteWorkspaceTab)
                    }
                    className="gap-0"
                >
                    <DocumentWorkspaceHeader
                        indexUrl={props.indexUrl}
                        indexLabel={labels.index.title}
                        number={props.quote.number}
                        title={labels.edit.title}
                        description={labels.edit.description}
                        dirty={dirty}
                        dirtyLabel={workspace.unsaved}
                        status={
                            <StatusBadge
                                status={
                                    props.quote.status.toLowerCase() as Status
                                }
                                label={
                                    labels.index.statuses[props.quote.status]
                                }
                            />
                        }
                        secondaryActions={
                            <>
                                <ActionLink
                                    href={props.representationUrl}
                                    variant="secondary"
                                >
                                    <Eye aria-hidden="true" />
                                    {labels.representation.view}
                                </ActionLink>
                                <DownloadLink
                                    href={props.pdfUrl}
                                    testId="pdf-download"
                                >
                                    <Download aria-hidden="true" />
                                    {labels.representation.download_pdf}
                                </DownloadLink>
                                {props.quoteAbilities.correctLifecycle && (
                                    <QuoteLifecycleDialog
                                        lifecycle={props.quote.lifecycle}
                                        url={props.lifecycleUrl}
                                        labels={labels.lifecycle}
                                    />
                                )}
                                {props.quoteAbilities.delete &&
                                    props.deletion.url && (
                                        <QuoteDeleteDialog
                                            url={props.deletion.url}
                                            highRisk={props.deletion.highRisk}
                                            stateVersion={
                                                props.deletion.stateVersion
                                            }
                                            guard={props.deletion.guard}
                                            labels={labels.deletion}
                                        />
                                    )}
                            </>
                        }
                        primaryActions={
                            <>
                                <QuoteDraftSummary
                                    processing={processing}
                                    dirty={dirty}
                                    currencyCode={props.quote.currencyCode}
                                    conversionUrl={props.conversionUrl}
                                    conversionKey={props.conversionKey}
                                    allocation={props.invoiceAllocation}
                                    saveLabel={labels.edit.save}
                                    conversionLabels={labels.conversion}
                                    formId={QUOTE_FORM_ID}
                                    separated={false}
                                    resetLabels={labels.edit}
                                />
                                <Button
                                    type="button"
                                    className="bg-money-fill text-money-fill-foreground hover:bg-money-fill/90"
                                    disabled={dirty}
                                    title={
                                        dirty
                                            ? workspace.send_requires_save
                                            : undefined
                                    }
                                    onClick={() => setTab('sharing')}
                                >
                                    <Send aria-hidden="true" />
                                    {workspace.send_email}
                                </Button>
                            </>
                        }
                        tabs={
                            <WorkspaceTabs<QuoteWorkspaceTab>
                                label={workspace.document_facts}
                                testIdPrefix="quote-workspace"
                                items={[
                                    {
                                        value: 'build',
                                        label: workspace.build_tab,
                                        pill:
                                            lineCount === 1
                                                ? workspace.line_count_one
                                                : workspace.line_count.replace(
                                                      ':count',
                                                      String(lineCount),
                                                  ),
                                    },
                                    {
                                        value: 'invoices',
                                        label: workspace.invoices_tab,
                                        pill:
                                            invoiceCount === 1
                                                ? workspace.invoice_count_one
                                                : workspace.invoice_count.replace(
                                                      ':count',
                                                      String(invoiceCount),
                                                  ),
                                    },
                                    {
                                        value: 'sharing',
                                        label: workspace.sharing_tab,
                                        pill: props.publicDocumentTranslations
                                            .management.statuses[
                                            props.publicLink.status
                                        ],
                                    },
                                ]}
                            />
                        }
                    />
                    <QuoteWorkspaceContent
                        {...props}
                        tab={tab}
                        dirty={dirty}
                        setDirty={setDirty}
                        setProcessing={setProcessing}
                        setLineCount={setLineCount}
                        setTab={setTab}
                    />
                </Tabs>
            </PageFrame>
        </div>
    );
}
