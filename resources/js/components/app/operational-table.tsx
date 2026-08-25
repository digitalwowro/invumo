import type { KeyboardEvent, ReactNode } from 'react';
import { ErrorState, LoadingState } from '@/components/app/async-state';
import { MetaLabel } from '@/components/app/typography';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type OperationalTableState =
    'ready' | 'loading' | 'empty' | 'no-results' | 'error';

type ColumnKind =
    'identity' | 'text' | 'data' | 'amount' | 'status' | 'actions';

type OperationalColumn<Row> = {
    key: string;
    label: string;
    kind?: ColumnKind;
    render: (row: Row) => ReactNode;
};

type StateCopy = {
    loading: string;
    emptyTitle: string;
    emptyDescription: string;
    noResultsTitle: string;
    noResultsDescription: string;
    errorTitle: string;
    errorDescription: string;
};

type OperationalTableProps<Row> = {
    ariaLabel: string;
    columns: OperationalColumn<Row>[];
    rows: Row[];
    rowKey: (row: Row) => string;
    state?: OperationalTableState;
    stateCopy: StateCopy;
    toolbar?: ReactNode;
    footer?: ReactNode;
    stateAction?: ReactNode;
    onRowActivate?: (row: Row) => void;
    rowLabel?: (row: Row) => string;
};

const cellClasses: Record<ColumnKind, string> = {
    identity: 'min-w-44 max-w-80 font-semibold text-foreground',
    text: 'min-w-40 max-w-96 text-foreground',
    data: 'font-data text-[13px] leading-5 tabular-nums',
    amount: 'font-data whitespace-nowrap text-right text-[13px] leading-5 tabular-nums',
    status: 'w-px whitespace-nowrap',
    actions: 'w-px whitespace-nowrap text-right',
};

const headClasses: Record<ColumnKind, string | undefined> = {
    identity: undefined,
    text: undefined,
    data: 'whitespace-nowrap',
    amount: 'whitespace-nowrap text-right',
    status: 'w-px whitespace-nowrap',
    actions: 'w-px whitespace-nowrap text-right',
};

function stateContent(
    state: Exclude<OperationalTableState, 'ready'>,
    copy: StateCopy,
    action?: ReactNode,
) {
    if (state === 'loading') {
        return <LoadingState label={copy.loading} />;
    }

    if (state === 'error') {
        return (
            <ErrorState
                title={copy.errorTitle}
                description={copy.errorDescription}
                retry={action}
            />
        );
    }

    const title =
        state === 'no-results' ? copy.noResultsTitle : copy.emptyTitle;
    const description =
        state === 'no-results'
            ? copy.noResultsDescription
            : copy.emptyDescription;

    return (
        <div className="py-10 text-center">
            <p className="text-sm font-semibold text-foreground">{title}</p>
            <p className="mt-1 text-sm text-foreground-muted">{description}</p>
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

export function OperationalTable<Row>({
    ariaLabel,
    columns,
    rows,
    rowKey,
    state = 'ready',
    stateCopy,
    toolbar,
    footer,
    stateAction,
    onRowActivate,
    rowLabel,
}: OperationalTableProps<Row>) {
    const activateFromKeyboard = (
        event: KeyboardEvent<HTMLTableRowElement>,
        row: Row,
    ) => {
        if (!onRowActivate || (event.key !== 'Enter' && event.key !== ' ')) {
            return;
        }

        event.preventDefault();
        onRowActivate(row);
    };

    return (
        <div
            data-slot="operational-table"
            className="w-full max-w-full min-w-0 overflow-hidden rounded-lg border border-border bg-background"
        >
            {toolbar && (
                <div className="border-b border-divider p-4">{toolbar}</div>
            )}

            <Table aria-label={ariaLabel}>
                <TableHeader>
                    <TableRow>
                        {columns.map((column) => (
                            <TableHead
                                key={column.key}
                                className={headClasses[column.kind ?? 'text']}
                            >
                                <MetaLabel>{column.label}</MetaLabel>
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {state === 'ready' ? (
                        rows.map((row) => (
                            <TableRow
                                key={rowKey(row)}
                                role={onRowActivate ? 'link' : undefined}
                                tabIndex={onRowActivate ? 0 : undefined}
                                aria-label={rowLabel?.(row)}
                                onClick={() => onRowActivate?.(row)}
                                onKeyDown={(event) =>
                                    activateFromKeyboard(event, row)
                                }
                            >
                                {columns.map((column) => (
                                    <TableCell
                                        key={column.key}
                                        className={
                                            cellClasses[column.kind ?? 'text']
                                        }
                                    >
                                        {column.kind === 'actions' ? (
                                            <div className="ml-auto w-max">
                                                {column.render(row)}
                                            </div>
                                        ) : (
                                            column.render(row)
                                        )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell colSpan={columns.length}>
                                {stateContent(state, stateCopy, stateAction)}
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>

            {footer && (
                <div className="border-t border-divider p-4">{footer}</div>
            )}
        </div>
    );
}

export type {
    OperationalColumn,
    OperationalTableProps,
    OperationalTableState,
    StateCopy as OperationalTableStateCopy,
};
