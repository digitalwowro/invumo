import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { OutwardDocument } from '@/types/outward-document';

type Props = { document: OutwardDocument };

export function OutwardDocumentLines({ document }: Props) {
    return (
        <div
            tabIndex={0}
            role="region"
            aria-label={`${document.kind} ${document.number}`}
            className="max-w-full overflow-x-auto rounded-md border border-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <table className="w-full min-w-3xl table-fixed text-left text-sm">
                <thead className="bg-surface-subtle text-xs tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th className="w-[38%] px-4 py-3">
                            {document.labels.description}
                        </th>
                        <th className="w-[17%] px-4 py-3">
                            {document.labels.quantity}
                        </th>
                        <th className="w-[15%] px-4 py-3 text-right">
                            {document.labels.unit_price}
                        </th>
                        <th className="w-[15%] px-4 py-3">
                            {document.labels.tax}
                        </th>
                        <th className="w-[15%] px-4 py-3 text-right">
                            {document.labels.line_total}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {document.lines.length ? (
                        document.lines.map((line) => (
                            <tr
                                key={line.position}
                                className="border-t border-rule"
                            >
                                <td className="px-4 py-4 align-top break-words whitespace-pre-wrap">
                                    {line.description}
                                    {line.discount ? (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {document.labels.discount}:{' '}
                                            {line.discount}
                                        </p>
                                    ) : null}
                                </td>
                                <DataCell>{line.quantity}</DataCell>
                                <DataCell align="right">
                                    {line.unitPrice}
                                </DataCell>
                                <td className="px-4 py-4 align-top break-words">
                                    {line.tax ?? document.labels.not_set}
                                </td>
                                <DataCell align="right">{line.total}</DataCell>
                            </tr>
                        ))
                    ) : (
                        <tr className="border-t border-rule">
                            <td
                                colSpan={5}
                                className="px-4 py-8 text-center text-muted-foreground"
                            >
                                {document.labels.no_lines}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function DataCell({
    children,
    align = 'left',
}: {
    children: ReactNode;
    align?: 'left' | 'right';
}) {
    return (
        <td
            className={cn(
                'px-4 py-4 align-top font-mono break-words tabular-nums',
                align === 'right' && 'text-right',
            )}
        >
            {children}
        </td>
    );
}

export function OutwardDocumentTotals({ document }: Props) {
    return (
        <dl className="grid min-w-0 gap-2 self-end sm:min-w-80">
            <TotalRow
                label={document.labels.subtotal}
                value={document.subtotal}
            />
            <TotalRow
                label={document.labels.tax_total}
                value={document.taxTotal}
            />
            <div className="mt-2 grid grid-cols-[1fr_auto] gap-6 border-t-2 border-(--outward-rule) pt-3 text-lg font-bold text-(--outward-text)">
                <dt>{document.labels.total}</dt>
                <dd className="text-right font-mono tabular-nums">
                    {document.total}
                </dd>
            </div>
        </dl>
    );
}

function TotalRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-[1fr_auto] gap-6">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-mono tabular-nums">{value}</dd>
        </div>
    );
}

export function OutwardDocumentSections({ document }: Props) {
    const sections = [
        document.bank.length
            ? {
                  key: 'bank',
                  title: document.labels.bank_details,
                  content: (
                      <dl className="grid gap-2">
                          {document.bank.map((row) => (
                              <div
                                  key={row.label}
                                  className="grid min-w-0 gap-1 sm:grid-cols-[auto_minmax(0,1fr)]"
                              >
                                  <dt className="text-muted-foreground">
                                      {row.label}
                                  </dt>
                                  <dd className="font-mono break-all tabular-nums">
                                      {row.value}
                                  </dd>
                              </div>
                          ))}
                      </dl>
                  ),
              }
            : null,
        document.notes
            ? {
                  key: 'notes',
                  title: document.labels.notes,
                  content: (
                      <p className="break-words whitespace-pre-wrap">
                          {document.notes}
                      </p>
                  ),
              }
            : null,
        document.termsAndConditions
            ? {
                  key: 'terms',
                  title: document.labels.terms_and_conditions,
                  content: (
                      <p className="break-words whitespace-pre-wrap">
                          {document.termsAndConditions}
                      </p>
                  ),
              }
            : null,
    ].filter((section) => section !== null);

    return sections.length ? (
        <div className="grid min-w-0 gap-8 md:grid-cols-2">
            {sections.map((section) => (
                <section
                    key={section.key}
                    className="min-w-0 border-t border-(--outward-rule) pt-5"
                >
                    <h2 className="mb-3 font-bold text-(--outward-text)">
                        {section.title}
                    </h2>
                    {section.content}
                </section>
            ))}
        </div>
    ) : null;
}
