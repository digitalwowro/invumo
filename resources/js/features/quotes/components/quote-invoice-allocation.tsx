import { ActionLink } from '@/components/app/action-link';
import { FormSection } from '@/components/app/form-section';
import { Grid, Stack } from '@/components/app/layout';
import { MoneyValue } from '@/components/domain/money-value';
import { StatusBadge } from '@/components/domain/status-badge';
import { QuoteInvoiceUnlinkDialog } from '@/features/quotes/components/quote-invoice-unlink-dialog';
import type { QuoteInvoiceAllocation, QuoteTranslations } from '@/types/quote';

type Props = {
    allocation: QuoteInvoiceAllocation;
    currencyCode: string | null;
    labels: Pick<QuoteTranslations, 'allocation' | 'unlink'>;
};

export function QuoteInvoiceAllocationSection({
    allocation,
    currencyCode,
    labels,
}: Props) {
    const money = (value: string) => `${value} ${currencyCode ?? ''}`;

    return (
        <FormSection
            title={labels.allocation.title}
            description={labels.allocation.description}
        >
            <Grid columns={3} gap="lg">
                {(['quoted', 'invoiced', 'remaining'] as const).map((field) => (
                    <Stack key={field} gap="xs">
                        <span className="text-sm text-foreground-muted">
                            {labels.allocation[field]}
                        </span>
                        <MoneyValue
                            value={money(allocation[field])}
                            emphasis="strong"
                            tone={
                                field === 'remaining' &&
                                allocation.remaining.startsWith('-')
                                    ? 'danger'
                                    : 'default'
                            }
                        />
                    </Stack>
                ))}
            </Grid>
            {allocation.invoices.length === 0 ? (
                <p className="text-sm text-foreground-muted">
                    {labels.allocation.empty}
                </p>
            ) : (
                <Stack gap="sm">
                    {allocation.invoices.map((invoice) => (
                        <div
                            key={invoice.id}
                            className="flex min-w-0 flex-wrap items-center justify-between gap-3 border-t border-divider pt-3"
                        >
                            <div className="flex min-w-0 flex-wrap items-center gap-3">
                                <ActionLink
                                    href={invoice.editUrl}
                                    variant="ghost"
                                >
                                    {invoice.number}
                                </ActionLink>
                                <StatusBadge
                                    status={
                                        invoice.lifecycle.toLowerCase() as
                                            'draft' | 'issued'
                                    }
                                    label={labels.allocation[invoice.lifecycle]}
                                />
                                <MoneyValue value={money(invoice.total)} />
                            </div>
                            {invoice.canUnlink && (
                                <QuoteInvoiceUnlinkDialog
                                    url={invoice.unlinkUrl}
                                    invoiceNumber={invoice.number}
                                    labels={labels.unlink}
                                />
                            )}
                        </div>
                    ))}
                </Stack>
            )}
        </FormSection>
    );
}
