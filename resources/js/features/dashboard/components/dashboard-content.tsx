import { Link } from '@inertiajs/react';
import { ReceiptText } from 'lucide-react';
import { useState } from 'react';
import { ContentSection } from '@/components/app/content-section';
import { Stack } from '@/components/app/layout';
import { MetricStrip } from '@/components/app/metric-strip';
import type { MetricStripItem } from '@/components/app/metric-strip';
import { MoneyValue } from '@/components/domain/money-value';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { DashboardActivityGrid } from '@/features/dashboard/components/dashboard-activity-grid';
import { DashboardBalanceOverview } from '@/features/dashboard/components/dashboard-balance-overview';
import { DashboardCurrencySwitcher } from '@/features/dashboard/components/dashboard-currency-switcher';
import { DashboardHealthGrid } from '@/features/dashboard/components/dashboard-health-grid';
import { RecentInvoiceTable } from '@/features/dashboard/components/recent-invoice-table';
import { interpolate } from '@/lib/translations';
import type {
    DashboardCurrencyGroup,
    DashboardData,
    DashboardTranslations,
} from '@/types/dashboard';

type RecentScope = 'all' | 'unpaid' | 'drafts';

type Props = {
    dashboard: DashboardData;
    labels: DashboardTranslations;
};

export function DashboardContent({ dashboard, labels }: Props) {
    const [currency, setCurrency] = useState(
        dashboard.currencyGroups[0]?.currencyCode ?? '',
    );
    const [recentScope, setRecentScope] = useState<RecentScope>('all');
    const group =
        dashboard.currencyGroups.find(
            (candidate) => candidate.currencyCode === currency,
        ) ?? dashboard.currencyGroups[0];

    if (!group) {
        return (
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
        );
    }

    return (
        <Stack gap="xl">
            <DashboardCurrencySwitcher
                groups={dashboard.currencyGroups}
                value={group.currencyCode}
                onValueChange={setCurrency}
                labels={labels}
            />
            <DashboardBalanceOverview
                group={group}
                labels={labels}
                monthLabel={dashboard.monthLabel}
                expectedThroughDate={dashboard.expectedThroughDate}
            />
            <DashboardMetricStrip group={group} labels={labels} />
            <DashboardActivityGrid
                group={group}
                labels={labels}
                invoicesUrl={dashboard.invoicesUrl}
                recurringUrl={dashboard.recurringUrl}
            />
            <ContentSection
                title={labels.recent.title}
                description={interpolate(labels.recent.description, {
                    currency: group.currencyCode,
                })}
                headerActions={
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={recentScope}
                        aria-label={labels.recent.title}
                        onValueChange={(value) =>
                            value && setRecentScope(value as RecentScope)
                        }
                        className="bg-background"
                    >
                        {(['all', 'unpaid', 'drafts'] as const).map((scope) => (
                            <ToggleGroupItem
                                key={scope}
                                value={scope}
                                className="text-xs data-[state=on]:bg-foreground data-[state=on]:text-foreground-inverse"
                            >
                                {labels.recent.scopes[scope]}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                }
                footer={
                    <Button asChild variant="ghost" size="compact">
                        <Link href={dashboard.invoicesUrl}>
                            {labels.recent.view_all}
                        </Link>
                    </Button>
                }
            >
                <RecentInvoiceTable
                    invoices={group.recentInvoices[recentScope]}
                    labels={labels}
                />
            </ContentSection>
            <DashboardHealthGrid
                group={group}
                labels={labels}
                invoicesUrl={dashboard.invoicesUrl}
            />
        </Stack>
    );
}

function DashboardMetricStrip({
    group,
    labels,
}: {
    group: DashboardCurrencyGroup;
    labels: DashboardTranslations;
}) {
    const amount = (value: string, tone: 'default' | 'positive' | 'danger') => (
        <MoneyValue
            value={`${value} ${group.currencyCode}`}
            emphasis="strong"
            tone={tone}
        />
    );
    const items: MetricStripItem[] = [
        {
            key: 'unpaid',
            label: labels.metrics.unpaid_invoices,
            value: group.unpaidCount,
            detail: amount(group.outstandingTotal, 'default'),
        },
        {
            key: 'overdue',
            label: labels.metrics.overdue_invoices,
            value: group.overdueCount,
            detail: amount(group.overdueTotal, 'danger'),
        },
        {
            key: 'paid',
            label: labels.metrics.paid_this_month,
            value: amount(group.paidThisMonth, 'positive'),
            detail: (
                <span className="text-xs text-foreground-muted">
                    {interpolate(labels.metrics.payments_received, {
                        count: group.paidThisMonthCount,
                    })}
                </span>
            ),
        },
        {
            key: 'drafts',
            label: labels.metrics.drafts,
            value: group.draftCount,
            detail: (
                <span className="font-data text-xs text-foreground-muted tabular-nums">
                    {interpolate(labels.metrics.unbilled, {
                        amount: `${group.draftTotal} ${group.currencyCode}`,
                    })}
                </span>
            ),
        },
    ];

    return (
        <MetricStrip
            ariaLabel={`${group.currencyCode} ${labels.currency.description}`}
            items={items}
        />
    );
}
