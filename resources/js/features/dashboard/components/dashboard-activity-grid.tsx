import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Surface } from '@/components/app/surface';
import { SurfaceTitle } from '@/components/app/typography';
import { Badge } from '@/components/ui/badge';
import { interpolate } from '@/lib/translations';
import { cn } from '@/lib/utils';
import type {
    DashboardCurrencyGroup,
    DashboardTranslations,
} from '@/types/dashboard';

type Tone = 'neutral' | 'positive' | 'warning' | 'danger';

type ActivityRow = {
    id: string;
    href: string;
    title: string;
    subtitle: string;
    amount?: string | null;
    meta: string;
    tone: Tone;
};

const accentClasses: Record<Tone, string> = {
    neutral: 'bg-foreground-subtle',
    positive: 'bg-money-fill',
    warning: 'bg-warning-fill',
    danger: 'bg-danger-fill',
};

export function DashboardActivityGrid({
    group,
    labels,
    invoicesUrl,
    recurringUrl,
}: {
    group: DashboardCurrencyGroup;
    labels: DashboardTranslations;
    invoicesUrl: string;
    recurringUrl: string;
}) {
    const attention = group.attention.map((invoice): ActivityRow => ({
        id: invoice.id,
        href: invoice.url,
        title: invoice.number,
        subtitle: invoice.customerName ?? labels.recent.not_available,
        amount: `${invoice.outstanding} ${group.currencyCode}`,
        meta:
            invoice.state === 'OVERDUE'
                ? interpolate(labels.activity.overdue_by, {
                      days: invoice.days,
                  })
                : invoice.days === 0
                  ? labels.activity.today
                  : interpolate(labels.activity.due_in, {
                        days: invoice.days,
                    }),
        tone: invoice.state === 'OVERDUE' ? 'danger' : 'warning',
    }));
    const failures = group.deliveryFailures.map((failure): ActivityRow => ({
        id: failure.id,
        href: failure.url,
        title: failure.invoiceNumber,
        subtitle: failure.recipientEmail ?? labels.recent.not_available,
        amount: `${failure.total} ${group.currencyCode}`,
        meta: failure.failure,
        tone: 'danger',
    }));
    const upcoming = group.upcoming.map((item): ActivityRow => ({
        id: item.id,
        href: item.url,
        title: item.title,
        subtitle:
            item.kind === 'QUOTE'
                ? interpolate(labels.activity.quote_expires, {
                      date: item.date,
                  })
                : interpolate(labels.activity.recurring_generates, {
                      date: item.date,
                  }),
        amount:
            item.amount === null ? null : `${item.amount} ${item.currencyCode}`,
        meta:
            item.daysUntil === 0
                ? labels.activity.today
                : interpolate(labels.activity.due_in, {
                      days: item.daysUntil,
                  }),
        tone: item.kind === 'QUOTE' ? 'warning' : 'positive',
    }));

    return (
        <div className="grid min-w-0 gap-4 xl:grid-cols-3">
            <ActivityPanel
                title={labels.activity.attention_title}
                count={interpolate(labels.activity.attention_count, {
                    count: group.overdueCount + group.dueSoonCount,
                })}
                countTone={group.overdueCount > 0 ? 'danger' : 'muted'}
                rows={attention}
                empty={interpolate(labels.activity.attention_empty, {
                    currency: group.currencyCode,
                })}
                footer={
                    <Link
                        href={`${invoicesUrl}?lifecycle=ISSUED&payment=OUTSTANDING&overdue=overdue`}
                    >
                        {labels.activity.open_overdue}
                    </Link>
                }
            />
            <ActivityPanel
                title={labels.activity.delivery_title}
                count={
                    group.deliveryFailureCount === 0
                        ? labels.activity.delivery_success
                        : interpolate(labels.activity.delivery_count, {
                              count: group.deliveryFailureCount,
                          })
                }
                countTone={group.deliveryFailureCount > 0 ? 'danger' : 'muted'}
                rows={failures}
                empty={interpolate(labels.activity.delivery_empty, {
                    currency: group.currencyCode,
                })}
                footer={
                    <Link href={invoicesUrl}>
                        {labels.activity.open_invoices}
                    </Link>
                }
            />
            <ActivityPanel
                title={labels.activity.upcoming_title}
                count={interpolate(labels.activity.upcoming_count, {
                    count: group.upcomingCount,
                })}
                countTone="muted"
                rows={upcoming}
                empty={interpolate(labels.activity.upcoming_empty, {
                    currency: group.currencyCode,
                })}
                footer={
                    <Link href={recurringUrl}>
                        {labels.activity.open_recurring}
                    </Link>
                }
            />
        </div>
    );
}

function ActivityPanel({
    title,
    count,
    countTone,
    rows,
    empty,
    footer,
}: {
    title: string;
    count: string;
    countTone: 'danger' | 'muted';
    rows: ActivityRow[];
    empty: string;
    footer: ReactNode;
}) {
    return (
        <Surface className="flex min-h-72 min-w-0 flex-col overflow-hidden p-0">
            <header className="flex min-h-12 items-center justify-between gap-3 border-b border-rule px-4">
                <SurfaceTitle>{title}</SurfaceTitle>
                <Badge variant={countTone}>{count}</Badge>
            </header>
            <div className="flex flex-1 flex-col">
                {rows.length === 0 ? (
                    <p className="m-4 rounded-md border border-dashed border-border bg-surface-subtle p-4 text-xs text-foreground-muted">
                        {empty}
                    </p>
                ) : (
                    rows.map((row) => (
                        <Link
                            key={row.id}
                            href={row.href}
                            className="flex min-h-14 min-w-0 items-center gap-3 border-b border-rule px-4 py-2 transition-colors hover:bg-surface-subtle focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'h-8 w-1 shrink-0 rounded-full',
                                    accentClasses[row.tone],
                                )}
                            />
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="font-data truncate text-xs font-bold">
                                    {row.title}
                                </span>
                                <span className="truncate text-xs text-foreground-muted">
                                    {row.subtitle}
                                </span>
                            </span>
                            <span className="flex shrink-0 flex-col items-end">
                                {row.amount && (
                                    <span className="font-data text-xs font-bold tabular-nums">
                                        {row.amount}
                                    </span>
                                )}
                                <span
                                    className={cn(
                                        'font-data max-w-32 truncate text-[11px]',
                                        row.tone === 'danger'
                                            ? 'text-danger-text'
                                            : row.tone === 'warning'
                                              ? 'text-warning-text'
                                              : 'text-foreground-muted',
                                    )}
                                >
                                    {row.meta}
                                </span>
                            </span>
                        </Link>
                    ))
                )}
            </div>
            <div className="mt-auto border-t border-rule bg-surface-subtle px-4 py-2.5 text-center text-xs font-semibold [&_a]:outline-none [&_a]:hover:underline [&_a]:focus-visible:ring-2 [&_a]:focus-visible:ring-ring">
                {footer}
            </div>
        </Surface>
    );
}
