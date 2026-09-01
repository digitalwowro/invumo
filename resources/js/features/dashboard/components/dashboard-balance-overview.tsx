import { MetaLabel } from '@/components/app/typography';
import { interpolate } from '@/lib/translations';
import { cn } from '@/lib/utils';
import type {
    DashboardAgingBucket,
    DashboardCurrencyGroup,
    DashboardTranslations,
} from '@/types/dashboard';

const bucketClasses: Record<DashboardAgingBucket['key'], string> = {
    not_due: 'bg-foreground-subtle',
    days_1_30: 'bg-warning-fill',
    days_31_60: 'bg-danger-on-ink',
    days_60_plus: 'bg-danger-fill',
};

type Props = {
    group: DashboardCurrencyGroup;
    labels: DashboardTranslations;
    monthLabel: string;
    expectedThroughDate: string;
};

export function DashboardBalanceOverview({
    group,
    labels,
    monthLabel,
    expectedThroughDate,
}: Props) {
    const outstanding = Number(group.outstandingTotal);

    return (
        <section className="flex min-w-0 flex-col gap-5 rounded-xl bg-sidebar p-5 text-foreground-inverse sm:p-6">
            <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_auto_auto]">
                <OverviewAmount
                    label={labels.overview.outstanding_total}
                    currency={group.currencyCode}
                    amount={group.outstandingTotal}
                    detail={interpolate(labels.overview.outstanding_note, {
                        unpaid: group.unpaidCount,
                        overdue: group.overdueCount,
                    })}
                    large
                />
                <OverviewAmount
                    label={labels.overview.expected_next_30}
                    amount={group.expectedNext30Total}
                    detail={interpolate(labels.overview.expected_note, {
                        count: group.expectedNext30Count,
                        date: expectedThroughDate,
                    })}
                />
                <OverviewAmount
                    label={labels.overview.collected_this_month}
                    amount={group.paidThisMonth}
                    detail={interpolate(labels.overview.collected_note, {
                        amount: `${group.issuedThisMonthTotal} ${group.currencyCode}`,
                        month: monthLabel,
                    })}
                    positive
                />
            </div>

            <div className="flex min-w-0 flex-col gap-3">
                <div
                    role="img"
                    aria-label={labels.aging.aria_label}
                    className="flex h-2.5 overflow-hidden rounded-full bg-sidebar-border"
                >
                    {group.aging.map((bucket) => (
                        <span
                            key={bucket.key}
                            className={bucketClasses[bucket.key]}
                            style={{
                                width:
                                    outstanding > 0
                                        ? `${(Number(bucket.total) / outstanding) * 100}%`
                                        : '0%',
                            }}
                        />
                    ))}
                </div>
                <dl className="grid min-w-0 grid-cols-2 gap-3 lg:grid-cols-4">
                    {group.aging.map((bucket) => (
                        <div key={bucket.key} className="min-w-0">
                            <dt className="flex items-center gap-2">
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'size-1.5 rounded-sm',
                                        bucketClasses[bucket.key],
                                    )}
                                />
                                <MetaLabel>
                                    {labels.aging[bucket.key]}
                                </MetaLabel>
                            </dt>
                            <dd className="mt-1 flex min-w-0 flex-col">
                                <span className="font-data truncate text-sm font-bold tabular-nums">
                                    {bucket.total} {group.currencyCode}
                                </span>
                                <span className="font-data text-[11px] text-sidebar-muted">
                                    {bucket.count}{' '}
                                    {bucket.count === 1
                                        ? labels.aging.invoice
                                        : labels.aging.invoices}
                                </span>
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </section>
    );
}

function OverviewAmount({
    label,
    currency,
    amount,
    detail,
    large = false,
    positive = false,
}: {
    label: string;
    currency?: string;
    amount: string;
    detail: string;
    large?: boolean;
    positive?: boolean;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-1.5 lg:last:items-end lg:last:text-right">
            <MetaLabel>{label}</MetaLabel>
            <div className="flex min-w-0 items-baseline gap-2">
                {currency && (
                    <span className="font-data text-sm font-bold text-sidebar-muted">
                        {currency}
                    </span>
                )}
                <span
                    className={cn(
                        'font-data truncate font-bold tabular-nums',
                        large ? 'text-3xl' : 'text-xl',
                        positive && 'text-money-fill',
                    )}
                >
                    {amount}
                </span>
            </div>
            <span className="text-xs text-sidebar-muted">{detail}</span>
        </div>
    );
}
