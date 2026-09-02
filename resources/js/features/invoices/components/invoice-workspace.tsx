import { Download, Eye, Send } from 'lucide-react';
import { useState } from 'react';
import { ActionLink } from '@/components/app/action-link';
import { DocumentWorkspaceHeader } from '@/components/app/document-workspace-header';
import { DownloadLink } from '@/components/app/download-link';
import { PageFrame } from '@/components/app/page-frame';
import { WorkspaceTabs } from '@/components/app/workspace-tabs';
import { InvoiceStatusBadges } from '@/components/domain/invoice-status-badges';
import { Button } from '@/components/ui/button';
import { Tabs } from '@/components/ui/tabs';
import { InvoiceDeleteDialog } from '@/features/invoices/components/invoice-delete-dialog';
import { InvoiceEditorLifecycleActions } from '@/features/invoices/components/invoice-editor-lifecycle-actions';
import { InvoiceWorkspaceContent } from '@/features/invoices/components/invoice-workspace-content';
import type {
    InvoiceWorkspaceComposedProps,
    InvoiceWorkspaceTab,
} from '@/features/invoices/components/invoice-workspace-types';
import { INVOICE_FORM_ID } from '@/features/invoices/components/invoice-workspace-types';

export function InvoiceWorkspace(props: InvoiceWorkspaceComposedProps) {
    const [dirty, setDirty] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [lineCount, setLineCount] = useState(props.invoice.lines.length);
    const [tab, setTab] = useState<InvoiceWorkspaceTab>(props.initialTab);
    const labels = props.translations;
    const workspace = labels.workspace;
    const moneyPill = isZero(props.transactions.summary.outstanding)
        ? labels.index.settled
        : workspace.amount_due
              .replace(':amount', props.transactions.summary.outstanding)
              .replace(':currency', props.invoice.currencyCode ?? '');

    return (
        <div className="min-h-full bg-page">
            <PageFrame width="full">
                <Tabs
                    value={tab}
                    onValueChange={(value) =>
                        setTab(value as InvoiceWorkspaceTab)
                    }
                    className="gap-0"
                >
                    <DocumentWorkspaceHeader
                        indexUrl={props.indexUrl}
                        indexLabel={labels.index.title}
                        number={props.invoice.number}
                        title={labels.edit.title}
                        description={labels.edit.description}
                        dirty={dirty}
                        dirtyLabel={workspace.unsaved}
                        status={
                            <InvoiceStatusBadges
                                lifecycle={props.invoice.lifecycle}
                                paymentState={props.invoice.paymentState}
                                overdue={props.invoice.isOverdue}
                                labels={labels.index.statuses}
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
                                {props.deletion.url && (
                                    <InvoiceDeleteDialog
                                        url={props.deletion.url}
                                        number={props.invoice.number}
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
                                <InvoiceEditorLifecycleActions
                                    lifecycle={props.invoice.lifecycle}
                                    lifecycleActions={props.lifecycleActions}
                                    issueUrl={props.issueUrl}
                                    editVersion={props.invoice.editVersion}
                                    dirty={dirty}
                                    processing={processing}
                                    saveLabel={labels.edit.save}
                                    issueLabels={labels.issue}
                                    lifecycleLabels={labels.lifecycle}
                                    formId={INVOICE_FORM_ID}
                                    separated={false}
                                    showStateMessage={false}
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
                            <WorkspaceTabs<InvoiceWorkspaceTab>
                                label={workspace.document_facts}
                                testIdPrefix="invoice-workspace"
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
                                        value: 'money',
                                        label: workspace.money_tab,
                                        pill: moneyPill,
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
                    <InvoiceWorkspaceContent
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

function isZero(value: string): boolean {
    return Number(value) === 0;
}
