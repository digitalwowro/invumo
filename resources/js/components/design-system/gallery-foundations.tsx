import { FormActions } from '@/components/app/form-actions';
import { TextField } from '@/components/app/form-field';
import { Grid, Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { Surface } from '@/components/app/surface';
import {
    Body,
    BodyStrong,
    MetaLabel,
    MetricValue,
    PageSubtitle,
    PageTitle,
    SecondaryText,
    SectionTitle,
    StatusLabel,
    SurfaceTitle,
    TableAmount,
    TableValue,
} from '@/components/app/typography';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { DesignSystemTranslations } from '@/types';

export function GalleryFoundations({
    labels,
}: {
    labels: DesignSystemTranslations;
}) {
    return (
        <Stack gap="2xl">
            <Stack gap="lg">
                <SectionHeader title={labels.sections.typography} />
                <Surface>
                    <Grid columns={2} gap="xl">
                        <Stack gap="md">
                            <PageTitle>{labels.typography.pageTitle}</PageTitle>
                            <PageSubtitle>
                                {labels.typography.pageSubtitle}
                            </PageSubtitle>
                            <SectionTitle>
                                {labels.typography.sectionTitle}
                            </SectionTitle>
                            <SurfaceTitle>
                                {labels.typography.surfaceTitle}
                            </SurfaceTitle>
                            <Body>{labels.typography.body}</Body>
                            <BodyStrong>
                                {labels.typography.bodyStrong}
                            </BodyStrong>
                            <SecondaryText>
                                {labels.typography.secondary}
                            </SecondaryText>
                        </Stack>
                        <Stack gap="lg">
                            <Stack gap="xs">
                                <MetaLabel>{labels.typography.meta}</MetaLabel>
                                <MetricValue>
                                    {labels.typography.metric}
                                </MetricValue>
                            </Stack>
                            <TableValue>
                                {labels.typography.tableValue}
                            </TableValue>
                            <TableAmount>
                                {labels.typography.tableAmount}
                            </TableAmount>
                            <TableValue>
                                {labels.typography.characters}
                            </TableValue>
                            <StatusLabel>
                                {labels.typography.status}
                            </StatusLabel>
                        </Stack>
                    </Grid>
                </Surface>
            </Stack>

            <Stack gap="lg">
                <SectionHeader title={labels.sections.actions} />
                <Surface>
                    <FormActions align="start">
                        <Button>{labels.actions.primary}</Button>
                        <Button variant="secondary">
                            {labels.actions.secondary}
                        </Button>
                        <Button variant="ghost">{labels.actions.ghost}</Button>
                        <Button variant="destructive">
                            {labels.actions.destructive}
                        </Button>
                        <Button disabled>{labels.actions.disabled}</Button>
                        <Button disabled>
                            <Spinner />
                            {labels.actions.loading}
                        </Button>
                    </FormActions>
                </Surface>
            </Stack>

            <Stack gap="lg">
                <SectionHeader title={labels.sections.forms} />
                <Surface>
                    <Grid columns={2} gap="xl">
                        <TextField
                            id="gallery-customer"
                            label={labels.forms.customer}
                            description={labels.forms.customerDescription}
                            input={{
                                name: 'customer',
                                placeholder: labels.forms.customerPlaceholder,
                            }}
                        />
                        <TextField
                            id="gallery-invalid"
                            label={labels.forms.invalid}
                            error={labels.forms.invalidError}
                            input={{
                                name: 'registration',
                                defaultValue: 'RO-?',
                            }}
                        />
                        <TextField
                            id="gallery-disabled"
                            label={labels.forms.disabled}
                            input={{
                                name: 'invoice_number',
                                defaultValue: 'INV-2026-0048',
                                disabled: true,
                            }}
                        />
                        <TextField
                            id="gallery-inherited"
                            label={labels.forms.inherited}
                            inheritedCaption={labels.forms.inheritedCaption}
                            input={{
                                name: 'payment_terms',
                                defaultValue: '30',
                                readOnly: true,
                            }}
                        />
                    </Grid>
                </Surface>
            </Stack>
        </Stack>
    );
}
