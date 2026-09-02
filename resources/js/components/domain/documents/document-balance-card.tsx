import type { ReactNode } from 'react';
import { moneySource } from '@/lib/money/decimal';
import type { DocumentEditorFinancials } from '@/types/document';

export type DocumentBalanceLabels = {
    gross: string;
    discount: string;
    taxableBase: string;
    tax: string;
    total: string;
};

type BalanceRow = {
    label: string;
    value: string;
    tone?: 'money' | 'warning';
};

type Props = {
    title: string;
    primaryLabel: string;
    primaryValue: string;
    progress: number;
    financials: DocumentEditorFinancials;
    labels: DocumentBalanceLabels;
    status?: ReactNode;
    footerRows?: BalanceRow[];
    action?: ReactNode;
};

export function DocumentBalanceCard(props: Props) {
    const currency = props.financials.currencyCode ?? '';
    const totals = props.financials.totals;
    const taxRows = groupedTaxes(props.financials, props.labels.tax);

    return (
        <section className="flex h-full min-h-[22rem] flex-col gap-4 rounded-lg bg-primary p-5 text-primary-foreground">
            <div className="flex items-start justify-between gap-3">
                <span className="font-data text-[11px] font-bold tracking-[0.09em] text-sidebar-muted uppercase">
                    {props.title}
                </span>
                {props.status}
            </div>
            <div className="font-data flex flex-wrap items-baseline gap-2 tabular-nums">
                <span className="text-sm font-bold text-sidebar-muted">
                    {currency}
                </span>
                <strong className="text-3xl leading-none">
                    {props.primaryValue}
                </strong>
                <span className="text-xs text-sidebar-muted">
                    {props.primaryLabel}
                </span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-sidebar-surface">
                <div
                    className="h-full rounded-full bg-money-fill"
                    style={{
                        width: `${Math.min(100, Math.max(0, props.progress))}%`,
                    }}
                />
            </div>
            <div className="font-data flex flex-1 flex-col justify-center gap-2 border-t border-sidebar-border pt-4 text-xs tabular-nums">
                <BalanceRow
                    label={props.labels.gross}
                    value={totals?.items_total ?? '—'}
                />
                <BalanceRow
                    label={props.labels.discount}
                    value={totals ? `−${totals.discount_amount}` : '—'}
                    tone={
                        totals && Number(totals.discount_amount) > 0
                            ? 'warning'
                            : undefined
                    }
                />
                <BalanceRow
                    label={props.labels.taxableBase}
                    value={totals?.grand_subtotal ?? '—'}
                />
                {taxRows.map((row) => (
                    <BalanceRow key={row.label} {...row} />
                ))}
                <BalanceRow
                    label={props.labels.total}
                    value={totals?.final_total ?? '—'}
                    strong
                />
                {props.footerRows?.map((row) => (
                    <BalanceRow key={row.label} {...row} />
                ))}
            </div>
            {props.action}
        </section>
    );
}

function BalanceRow(props: BalanceRow & { strong?: boolean }) {
    const valueClass =
        props.tone === 'money'
            ? 'text-money-fill'
            : props.tone === 'warning'
              ? 'text-warning-fill'
              : '';

    return (
        <div
            className={`flex items-baseline justify-between gap-4 ${props.strong ? 'mt-1 border-t border-sidebar-border pt-3 text-sm' : ''}`}
        >
            <span
                className={
                    props.strong
                        ? 'text-primary-foreground'
                        : 'text-sidebar-muted'
                }
            >
                {props.label}
            </span>
            <strong className={valueClass}>{props.value}</strong>
        </div>
    );
}

function groupedTaxes(
    financials: DocumentEditorFinancials,
    label: string,
): BalanceRow[] {
    const precision = financials.currencyPrecision;

    if (precision === null || financials.totals === null) {
        return [{ label, value: '—' }];
    }

    const groups = new Map<string, ReturnType<typeof moneySource>>();
    financials.calculated.forEach((amounts, index) => {
        if (!amounts || Number(amounts.tax_amount) === 0) {
            return;
        }

        const line = financials.lines[index];
        const key = line.taxName
            ? `${line.taxName} ${line.taxPercentage}%`
            : `${line.taxPercentage}%`;
        groups.set(
            key,
            (groups.get(key) ?? moneySource('0')).plus(
                moneySource(amounts.tax_amount),
            ),
        );
    });

    if (groups.size === 0) {
        return [{ label, value: financials.totals.tax_amount }];
    }

    return [...groups].map(([key, value]) => ({
        label: `${label} · ${key}`,
        value: value.toFixed(precision),
    }));
}
