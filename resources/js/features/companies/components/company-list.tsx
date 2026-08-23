import { Building2 } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { EmptyState } from '@/components/app/async-state';
import { Grid, Stack } from '@/components/app/layout';
import { Surface } from '@/components/app/surface';
import { BodyStrong, SecondaryText } from '@/components/app/typography';
import type { CompaniesUiTranslations, CompanySummary } from '@/types';

type CompanyListProps = {
    companies: CompanySummary[];
    translations: CompaniesUiTranslations;
    createUrl: string;
};

export function CompanyList({
    companies,
    translations,
    createUrl,
}: CompanyListProps) {
    const labels = translations.index;

    if (companies.length === 0) {
        return (
            <EmptyState
                title={labels.empty_title}
                description={labels.empty_description}
                action={
                    <ActionLink href={createUrl}>{labels.create}</ActionLink>
                }
            />
        );
    }

    return (
        <Grid as="ul" columns={2} gap="lg">
            {companies.map((company) => (
                <li key={company.id}>
                    <Surface className="h-full">
                        <Stack gap="lg">
                            <div className="flex size-10 items-center justify-center rounded-md bg-accent text-foreground">
                                <Building2
                                    aria-hidden="true"
                                    className="size-5"
                                />
                            </div>
                            <Stack gap="xs">
                                <BodyStrong>{company.name}</BodyStrong>
                                <SecondaryText>
                                    {labels.description}
                                </SecondaryText>
                            </Stack>
                            <ActionLink
                                href={company.dashboardUrl}
                                variant="secondary"
                            >
                                {labels.open}
                            </ActionLink>
                        </Stack>
                    </Surface>
                </li>
            ))}
        </Grid>
    );
}
