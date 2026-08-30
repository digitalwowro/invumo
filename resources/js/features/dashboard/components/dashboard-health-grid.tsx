import { Link } from '@inertiajs/react';
import { Surface } from '@/components/app/surface';
import { SurfaceTitle } from '@/components/app/typography';
import { Button } from '@/components/ui/button';
import { interpolate } from '@/lib/translations';
import type {
    DashboardCurrencyGroup,
    DashboardTranslations,
} from '@/types/dashboard';

export function DashboardHealthGrid({
    group,
    labels,
    invoicesUrl,
}: {
    group: DashboardCurrencyGroup;
    labels: DashboardTranslations;
    invoicesUrl: string;
}) {
    const health = [
        {
            key: 'settled',
            label: labels.health.settled,
            value: `${group.settledRate}%`,
            width: group.settledRate,
            className: 'bg-money-fill',
        },
        {
            key: 'overdue',
            label: labels.health.overdue_share,
            value: `${group.overdueShare}%`,
            width: group.overdueShare,
            className: 'bg-danger-fill',
        },
        {
            key: 'age',
            label: labels.health.average_age,
            value: interpolate(labels.health.days, {
                count: group.averageUnpaidAgeDays,
            }),
            width: Math.min(group.averageUnpaidAgeDays, 100),
            className: 'bg-foreground',
        },
    ];

    return (
        <div className="grid min-w-0 gap-4 lg:grid-cols-2">
            <Surface className="flex min-w-0 flex-col gap-4 p-5">
                <SurfaceTitle>{labels.health.title}</SurfaceTitle>
                <dl className="flex flex-col gap-3">
                    {health.map((item) => (
                        <div key={item.key} className="flex flex-col gap-1.5">
                            <div className="flex items-baseline justify-between gap-3">
                                <dt className="text-xs text-foreground-muted">
                                    {item.label}
                                </dt>
                                <dd className="font-data text-xs font-bold tabular-nums">
                                    {item.value}
                                </dd>
                            </div>
                            <div className="h-1 overflow-hidden rounded-full bg-rule">
                                <div
                                    className={`h-full rounded-full ${item.className}`}
                                    style={{ width: `${item.width}%` }}
                                />
                            </div>
                        </div>
                    ))}
                </dl>
            </Surface>
            <Surface className="flex min-w-0 flex-col gap-3 p-5">
                <SurfaceTitle>{labels.drafts.title}</SurfaceTitle>
                <p className="text-xs text-foreground-muted">
                    {group.draftCount === 0
                        ? interpolate(labels.drafts.empty, {
                              currency: group.currencyCode,
                          })
                        : interpolate(labels.drafts.waiting, {
                              count: group.draftCount,
                          })}
                </p>
                <div className="flex min-w-0 items-baseline gap-2">
                    <span className="font-data truncate text-xl font-bold tabular-nums">
                        {group.draftTotal}
                    </span>
                    <span className="font-data text-xs text-foreground-muted">
                        {interpolate(labels.drafts.unbilled, {
                            currency: group.currencyCode,
                        })}
                    </span>
                </div>
                <Button asChild variant="secondary" size="compact">
                    <Link href={`${invoicesUrl}?lifecycle=DRAFT`}>
                        {labels.drafts.review}
                    </Link>
                </Button>
            </Surface>
        </div>
    );
}
