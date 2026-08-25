import { router } from '@inertiajs/react';
import { Cluster } from '@/components/app/layout';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalTableStateCopy } from '@/components/app/operational-table';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import { BodyStrong, TableAmount } from '@/components/app/typography';
import { Badge } from '@/components/ui/badge';
import { TaxPresetEditDialog } from '@/features/companies/components/tax-preset-edit-dialog';
import { interpolate } from '@/lib/translations';
import type {
    CompanyTaxPresetTranslations,
    TaxPreset,
} from '@/types/company-tax';

type Props = {
    presets: TaxPreset[];
    labels: CompanyTaxPresetTranslations;
    cancelLabel: string;
    closeLabel: string;
};

function stateCopy(labels: CompanyTaxPresetTranslations) {
    return {
        loading: '',
        emptyTitle: labels.empty_title,
        emptyDescription: labels.empty_description,
        noResultsTitle: '',
        noResultsDescription: '',
        errorTitle: '',
        errorDescription: '',
    } satisfies OperationalTableStateCopy;
}

export function TaxPresetTable({
    presets,
    labels,
    cancelLabel,
    closeLabel,
}: Props) {
    return (
        <OperationalTable
            ariaLabel={labels.list_title}
            rows={presets}
            rowKey={(preset) => preset.id}
            state={presets.length === 0 ? 'empty' : 'ready'}
            stateCopy={stateCopy(labels)}
            columns={[
                {
                    key: 'name',
                    label: labels.name_column,
                    kind: 'identity',
                    render: (preset) => <BodyStrong>{preset.name}</BodyStrong>,
                },
                {
                    key: 'percentage',
                    label: labels.percentage_column,
                    kind: 'amount',
                    render: (preset) => (
                        <TableAmount>{preset.percentage}%</TableAmount>
                    ),
                },
                {
                    key: 'default',
                    label: labels.default_column,
                    kind: 'status',
                    render: (preset) => (
                        <Badge
                            variant={preset.isDefault ? 'positive' : 'muted'}
                        >
                            {preset.isDefault
                                ? labels.default
                                : labels.not_default}
                        </Badge>
                    ),
                },
                {
                    key: 'status',
                    label: labels.status_column,
                    kind: 'status',
                    render: (preset) => (
                        <Badge variant={preset.archived ? 'muted' : 'quiet'}>
                            {preset.archived ? labels.archived : labels.active}
                        </Badge>
                    ),
                },
                {
                    key: 'actions',
                    label: labels.actions_column,
                    kind: 'actions',
                    render: (preset) => (
                        <Cluster gap="sm">
                            {preset.updateUrl && (
                                <TaxPresetEditDialog
                                    preset={preset}
                                    labels={labels}
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                />
                            )}
                            {preset.archiveUrl && (
                                <ConfirmationDialog
                                    triggerLabel={labels.archive}
                                    title={labels.archive_title}
                                    description={interpolate(
                                        labels.archive_description,
                                        { name: preset.name },
                                    )}
                                    confirmLabel={labels.confirm_archive}
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                    onConfirm={() =>
                                        router.patch(
                                            preset.archiveUrl as string,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            )}
                        </Cluster>
                    ),
                },
            ]}
        />
    );
}
