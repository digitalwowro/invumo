import { Plus } from 'lucide-react';
import { useState } from 'react';
import { FormSection } from '@/components/app/form-section';
import { SystemMessage } from '@/components/app/system-message';
import {
    applyDocumentProductDefaults,
    DocumentTotals,
    moveDocumentLine,
    normalizeEditedLine,
} from '@/components/domain/documents/document-draft-lines';
import { DocumentLineCard } from '@/components/domain/documents/document-line-card';
import type { DocumentLineItemProps } from '@/components/domain/documents/document-line-card';
import { DocumentLineTable } from '@/components/domain/documents/document-line-table';
import { Button } from '@/components/ui/button';
import { useWideDocumentTable } from '@/hooks/use-wide-document-table';
import type { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import type { LineAmounts } from '@/lib/money/line-calculation';
import type { CatalogTaxOption } from '@/types/catalog';
import type {
    DocumentLineLimits,
    DocumentEditorTranslations,
    DocumentLineDraft,
    DocumentTaxDefault,
} from '@/types/document';

type Props = {
    lines: DocumentLineDraft[];
    calculated: Array<LineAmounts | null>;
    totals: ReturnType<typeof calculateDocumentAmounts> | null;
    taxDefault: DocumentTaxDefault | null;
    taxPresetOptions: CatalogTaxOption[];
    currencyCode: string | null;
    currencyPrecision: number | null;
    productSearchUrl: string;
    limits: DocumentLineLimits;
    labels: DocumentEditorTranslations;
    errors: Record<string, string>;
    onChange: (
        change: (lines: DocumentLineDraft[]) => DocumentLineDraft[],
    ) => void;
    onAdd: (tax: DocumentTaxDefault | null) => DocumentLineDraft;
};

export function DocumentLineEditor({
    lines,
    calculated,
    totals,
    taxDefault,
    taxPresetOptions,
    currencyCode,
    currencyPrecision,
    productSearchUrl,
    limits,
    labels,
    errors,
    onChange,
    onAdd,
}: Props) {
    const [expandedLineKey, setExpandedLineKey] = useState<string | null>(null);
    const { containerRef, wide: wideTable } = useWideDocumentTable();
    const addLine = () => {
        const line = onAdd(taxDefault);
        setExpandedLineKey(line.key);
        onChange((current) => [...current, line]);
    };
    const rows: DocumentLineItemProps[] = lines.map((line, index) => ({
        line: {
            ...line,
            finalLineTotal: calculated[index]?.final_line_total ?? null,
        },
        amounts: calculated[index],
        index,
        count: lines.length,
        limits,
        labels,
        errors,
        inheritedTax: taxDefault,
        taxPresetOptions,
        productSearchUrl,
        currencyCode,
        currencyPrecision,
        sourceNotice: sourceNotice(line, labels),
        onChange: (next) =>
            onChange((current) =>
                current.map((item, itemIndex) =>
                    itemIndex === index
                        ? normalizeEditedLine(item, next)
                        : item,
                ),
            ),
        onMove: (direction) =>
            onChange((current) => moveDocumentLine(current, index, direction)),
        onProductSelect: (defaults) =>
            onChange((current) =>
                current.map((item, itemIndex) =>
                    itemIndex === index
                        ? applyDocumentProductDefaults(
                              item,
                              defaults,
                              taxDefault,
                              currencyPrecision,
                          )
                        : item,
                ),
            ),
        onRemove: () => {
            if (expandedLineKey === line.key) {
                setExpandedLineKey(null);
            }

            onChange((current) =>
                current.filter((_, itemIndex) => itemIndex !== index),
            );
        },
    }));

    return (
        <FormSection
            title={labels.products_services_section}
            description={(lines.length === 1
                ? labels.products_services_summary_one
                : labels.products_services_summary
            )
                .replace(':currency', currencyCode ?? labels.not_available)
                .replace(':count', String(lines.length))}
            flush
        >
            <div ref={containerRef} className="min-w-0">
                {wideTable ? (
                    <DocumentLineTable
                        rows={rows}
                        labels={labels}
                        ariaLabel={labels.products_services_section}
                    />
                ) : (
                    <div>
                        {rows.map((row) => (
                            <DocumentLineCard
                                key={row.line.key}
                                {...row}
                                expanded={expandedLineKey === row.line.key}
                                onExpandedChange={(expanded) =>
                                    setExpandedLineKey(
                                        expanded ? row.line.key : null,
                                    )
                                }
                            />
                        ))}
                    </div>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    className="w-full rounded-none border-b border-divider bg-surface-subtle py-3"
                    data-testid="document-line-add"
                    onClick={addLine}
                >
                    <Plus aria-hidden="true" />
                    {labels.add_line}
                </Button>
                <DocumentTotals labels={labels} totals={totals} embedded />
            </div>
        </FormSection>
    );
}

function sourceNotice(
    line: DocumentLineDraft,
    labels: DocumentEditorTranslations,
) {
    if (!line.priceStatus || line.priceStatus === 'COPIED') {
        return undefined;
    }

    return (
        <SystemMessage
            title={
                line.priceStatus === 'CURRENCY_MISMATCH'
                    ? labels.currency_mismatch
                    : labels.manual_price_required
            }
            tone="warning"
        />
    );
}
