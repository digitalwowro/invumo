import { Grid, Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { Surface } from '@/components/app/surface';
import {
    BodyStrong,
    MetricValue,
    SecondaryText,
} from '@/components/app/typography';
import { formatPlatformDate } from '@/features/platform/components/platform-list-tools';
import type { PlatformActivityRow, PlatformTranslations } from '@/types';

type OverviewProps = {
    counts: {
        users: number;
        accounts: number;
        companies: number;
        operators: number;
    };
    recentActivity: PlatformActivityRow[];
    translations: PlatformTranslations;
    locale: string;
};

export function PlatformOverviewContent({
    counts,
    recentActivity,
    translations,
    locale,
}: OverviewProps) {
    const copy = translations.overview;
    const metrics = [
        [copy.users, counts.users],
        [copy.accounts, counts.accounts],
        [copy.companies, counts.companies],
        [copy.operators, counts.operators],
    ] as const;

    return (
        <Stack gap="xl">
            <Grid columns={4} gap="lg">
                {metrics.map(([label, value]) => (
                    <Surface key={label}>
                        <Stack gap="xs">
                            <SecondaryText>{label}</SecondaryText>
                            <MetricValue>{value}</MetricValue>
                        </Stack>
                    </Surface>
                ))}
            </Grid>
            <Surface>
                <Stack gap="lg">
                    <SectionHeader
                        title={copy.activity_title}
                        description={copy.activity_description}
                    />
                    {recentActivity.length === 0 ? (
                        <SecondaryText>{copy.activity_empty}</SecondaryText>
                    ) : (
                        <ul className="divide-y divide-divider">
                            {recentActivity.map((activity) => (
                                <li
                                    key={activity.id}
                                    className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <BodyStrong>
                                            {activity.action.replaceAll(
                                                '.',
                                                ' ',
                                            )}
                                        </BodyStrong>
                                        <SecondaryText>
                                            {activity.actorName ??
                                                copy.system_actor}{' '}
                                            · {activity.targetType}
                                        </SecondaryText>
                                    </div>
                                    <SecondaryText>
                                        {formatPlatformDate(
                                            activity.occurredAt,
                                            locale,
                                            translations.common.not_available,
                                        )}
                                    </SecondaryText>
                                </li>
                            ))}
                        </ul>
                    )}
                </Stack>
            </Surface>
        </Stack>
    );
}
