import { Grid, Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { Surface } from '@/components/app/surface';
import {
    BodyStrong,
    MetaLabel,
    SecondaryText,
} from '@/components/app/typography';
import { customerResolvedDefaultSummaries } from '@/features/customers/components/customer-resolved-default-summaries';
import type {
    CustomerDefaultsTranslations,
    CustomerResolvedDefaults,
} from '@/types/customer-defaults';

type Props = {
    resolved: CustomerResolvedDefaults;
    labels: CustomerDefaultsTranslations;
};

export function CustomerResolvedDefaults({ resolved, labels }: Props) {
    const summaries = customerResolvedDefaultSummaries(resolved, labels);

    return (
        <Surface>
            <Stack gap="lg">
                <SectionHeader
                    title={labels.resolved_title}
                    description={labels.resolved_description}
                />
                <Grid columns={3} gap="lg">
                    {summaries.map((summary) => (
                        <Stack key={summary.label} gap="xs">
                            <MetaLabel>{summary.label}</MetaLabel>
                            <BodyStrong>{summary.value}</BodyStrong>
                            <SecondaryText>
                                {labels.sources[summary.source]}
                            </SecondaryText>
                        </Stack>
                    ))}
                </Grid>
            </Stack>
        </Surface>
    );
}
