import { ReceiptText } from 'lucide-react';
import { Stack } from '@/components/app/layout';
import { MetricStrip } from '@/components/app/metric-strip';
import type { MetricStripItem } from '@/components/app/metric-strip';
import { SectionHeader } from '@/components/app/section-header';
import { MoneyValue } from '@/components/domain/money-value';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { RecentInvoiceTable } from '@/features/dashboard/components/recent-invoice-table';
import type {
    DashboardCurrencyGroup,
    DashboardData,
    DashboardTranslations,
} from '@/types/dashboard';

type Props = {
    dashboard: DashboardData;
    labels: DashboardTranslations;
};

export function DashboardContent({ dashboard, labels }: Props) {
    return (
        <Stack gap="2xl">
            {dashboard.currencyGroups.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <ReceiptText aria-hidden="true" />
                        </EmptyMedia>
                        <EmptyTitle>{labels.activity.empty_title}</EmptyTitle>
                        <EmptyDescription>
                            {labels.activity.empty_description}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                dashboard.currencyGroups.map((group) => (
                    <CurrencyMetrics
                        key={group.currencyCode}
                        group={group}
                        labels={labels}
                    />
                ))
            )}

            <section className="min-w-0">
                <Stack gap="lg">
                    <SectionHeader
                        title={labels.recent.title}
                        description={labels.recent.description}
                    />
                    <RecentInvoiceTable
                        invoices={dashboard.recentInvoices}
                        labels={labels}
                    />
                </Stack>
            </section>
        </Stack>
    );
}

function CurrencyMetrics({
    group,
    labels,
}: {
    group: DashboardCurrencyGroup;
    labels: DashboardTranslations;
}) {
    const value = (amount: string, tone: 'default' | 'positive' | 'danger') => (
        <MoneyValue
            value={`${amount} ${group.currencyCode}`}
            emphasis="strong"
            tone={tone}
        />
    );
    const items: MetricStripItem[] = [
        {
            key: 'unpaid',
            label: labels.metrics.unpaid_invoices,
            value: group.unpaidCount,
        },
        {
            key: 'overdue',
            label: labels.metrics.overdue_invoices,
            value: group.overdueCount,
            detail: (
                <span className="text-xs text-foreground-muted">
                    {labels.metrics.overdue_balance}:{' '}
                    {value(group.overdueTotal, 'danger')}
                </span>
            ),
        },
        {
            key: 'paid',
            label: labels.metrics.paid_this_month,
            value: value(group.paidThisMonth, 'positive'),
        },
        {
            key: 'outstanding',
            label: labels.metrics.outstanding_total,
            value: value(group.outstandingTotal, 'default'),
        },
    ];

    return (
        <section className="min-w-0">
            <Stack gap="lg">
                <SectionHeader
                    title={group.currencyCode}
                    description={labels.currency.description}
                />
                <MetricStrip
                    ariaLabel={`${group.currencyCode} ${labels.currency.description}`}
                    items={items}
                />
            </Stack>
        </section>
    );
}
