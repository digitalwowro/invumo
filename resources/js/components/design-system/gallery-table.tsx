import { TextField } from '@/components/app/form-field';
import { Stack } from '@/components/app/layout';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalColumn } from '@/components/app/operational-table';
import { SectionHeader } from '@/components/app/section-header';
import { SecondaryText, TableValue } from '@/components/app/typography';
import { MoneyValue } from '@/components/domain/money-value';
import { StatusBadge } from '@/components/domain/status-badge';
import type { Status } from '@/components/domain/status-badge';
import type {
    DesignSystemStatusLabels,
    DesignSystemTranslations,
} from '@/types';

type ExampleInvoice = {
    id: string;
    customer: string;
    issued: string;
    total: string;
    balance: string;
    status: Status;
};

const rows: ExampleInvoice[] = [
    {
        id: 'INV-2026-0048',
        customer: 'Atelier Nord SRL',
        issued: '12.05.2026',
        total: '€ 2,350.00',
        balance: '€ 0.00',
        status: 'paid',
    },
    {
        id: 'INV-2026-0047',
        customer: 'Munte Digital SRL',
        issued: '08.05.2026',
        total: '€ 1,980.00',
        balance: '€ 990.00',
        status: 'partial',
    },
    {
        id: 'INV-2026-0046',
        customer: 'Studio Șapte SRL',
        issued: '25.04.2026',
        total: '€ 3,120.00',
        balance: '€ 3,120.00',
        status: 'overdue',
    },
];

type GalleryTableProps = {
    labels: DesignSystemTranslations;
    statusLabels: DesignSystemStatusLabels;
};

export function GalleryTable({ labels, statusLabels }: GalleryTableProps) {
    const columns: OperationalColumn<ExampleInvoice>[] = [
        {
            key: 'invoice',
            label: labels.table.columns.invoice,
            kind: 'identity',
            render: (row) => <TableValue>{row.id}</TableValue>,
        },
        {
            key: 'customer',
            label: labels.table.columns.customer,
            kind: 'text',
            render: (row) => row.customer,
        },
        {
            key: 'issued',
            label: labels.table.columns.issued,
            kind: 'data',
            render: (row) => row.issued,
        },
        {
            key: 'total',
            label: labels.table.columns.total,
            kind: 'amount',
            render: (row) => <MoneyValue value={row.total} emphasis="strong" />,
        },
        {
            key: 'balance',
            label: labels.table.columns.balance,
            kind: 'amount',
            render: (row) => <MoneyValue value={row.balance} />,
        },
        {
            key: 'status',
            label: labels.table.columns.status,
            kind: 'status',
            render: (row) => (
                <StatusBadge
                    status={row.status}
                    label={statusLabels[row.status]}
                />
            ),
        },
    ];

    return (
        <Stack gap="lg">
            <SectionHeader title={labels.sections.table} />
            <OperationalTable
                ariaLabel={labels.table.ariaLabel}
                columns={columns}
                rows={rows}
                rowKey={(row) => row.id}
                stateCopy={labels.asyncStates}
                toolbar={
                    <TextField
                        id="gallery-table-search"
                        label={labels.table.searchPlaceholder}
                        input={{
                            name: 'search',
                            type: 'search',
                            placeholder: labels.table.searchPlaceholder,
                        }}
                    />
                }
                footer={<SecondaryText>{labels.table.footer}</SecondaryText>}
            />
        </Stack>
    );
}
