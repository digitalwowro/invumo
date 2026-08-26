import type { CSSProperties } from 'react';
import {
    OutwardDocumentLines,
    OutwardDocumentSections,
    OutwardDocumentTotals,
} from '@/components/domain/outward-document-details';
import type {
    OutwardDocument as OutwardDocumentData,
    OutwardParty,
} from '@/types/outward-document';

type Props = {
    document: OutwardDocumentData;
};

type ThemeProperties = CSSProperties & {
    '--outward-accent': string;
    '--outward-on-accent': string;
    '--outward-text': string;
    '--outward-rule': string;
};

export function OutwardDocument({ document }: Props) {
    const theme: ThemeProperties = {
        '--outward-accent': document.theme.accentColor,
        '--outward-on-accent': document.theme.onAccentColor,
        '--outward-text': document.theme.textColor,
        '--outward-rule': document.theme.ruleColor,
    };

    return (
        <article
            lang={document.language}
            style={theme}
            className="min-w-0 overflow-hidden rounded-lg border border-border bg-background"
        >
            <div className="h-2 bg-(--outward-accent)" aria-hidden="true" />
            <div className="flex min-w-0 flex-col gap-8 p-5 sm:p-8 lg:p-10">
                <DocumentHeader document={document} />
                <div className="grid min-w-0 gap-8 border-t border-(--outward-rule) pt-6 md:grid-cols-2">
                    <Party
                        label={document.labels.from}
                        party={document.company}
                    />
                    <Party
                        label={document.labels.bill_to}
                        party={document.customer}
                        notSet={document.labels.not_set}
                    />
                </div>
                <OutwardDocumentLines document={document} />
                <OutwardDocumentTotals document={document} />
                <OutwardDocumentSections document={document} />
            </div>
        </article>
    );
}

function DocumentHeader({ document }: Props) {
    return (
        <header className="grid min-w-0 gap-8 sm:grid-cols-2">
            <div className="flex min-w-0 flex-col items-start gap-4">
                {document.logoUrl ? (
                    <img
                        src={document.logoUrl}
                        alt=""
                        className="max-h-16 max-w-56 object-contain object-left"
                    />
                ) : null}
                <div className="min-w-0">
                    <p className="text-xl font-bold break-words text-(--outward-text)">
                        {document.company.displayName}
                    </p>
                    {document.company.legalName ? (
                        <p className="text-sm break-words text-muted-foreground">
                            {document.company.legalName}
                        </p>
                    ) : null}
                </div>
            </div>
            <div className="flex min-w-0 flex-col items-start gap-4 sm:items-end sm:text-right">
                <h1 className="text-3xl font-bold text-(--outward-text)">
                    {document.kind}
                </h1>
                <span className="max-w-full rounded-md bg-(--outward-accent) px-3 py-1.5 font-mono font-bold break-all text-(--outward-on-accent) tabular-nums">
                    {document.number}
                </span>
                <dl className="grid min-w-0 gap-2 text-sm">
                    <MetaRow
                        label={document.labels.issue_date}
                        value={document.issueDate}
                    />
                    <MetaRow
                        label={document.labels.valid_until}
                        value={document.validUntil}
                    />
                    <MetaRow
                        label={document.labels.due_date}
                        value={document.dueDate}
                    />
                    <MetaRow
                        label={document.labels.customer_reference}
                        value={document.customerReference}
                    />
                </dl>
            </div>
        </header>
    );
}

function MetaRow({ label, value }: { label: string; value: string | null }) {
    return value ? (
        <div className="grid min-w-0 gap-1 sm:grid-cols-[auto_minmax(0,1fr)]">
            <dt className="font-medium text-muted-foreground">{label}</dt>
            <dd className="font-mono break-words tabular-nums">{value}</dd>
        </div>
    ) : null;
}

function Party({
    label,
    party,
    notSet,
}: {
    label: string;
    party: OutwardParty | null;
    notSet?: string;
}) {
    return (
        <section className="min-w-0">
            <h2 className="text-xs font-bold tracking-wide text-muted-foreground uppercase">
                {label}
            </h2>
            {party ? (
                <div className="mt-3 flex min-w-0 flex-col gap-1">
                    <p className="font-bold break-words text-(--outward-text)">
                        {party.displayName}
                    </p>
                    <DetailLines values={party.contact ?? []} />
                    <DetailLines values={party.address} />
                    <DetailLines values={party.registrations} />
                    <DetailLines values={party.contacts} />
                </div>
            ) : (
                <p className="mt-3 text-muted-foreground">{notSet}</p>
            )}
        </section>
    );
}

function DetailLines({ values }: { values: string[] }) {
    return values.map((value, index) => (
        <p key={`${value}-${index}`} className="text-sm break-words">
            {value}
        </p>
    ));
}
