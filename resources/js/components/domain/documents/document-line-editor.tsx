import { Plus } from 'lucide-react';
import { FormSection } from '@/components/app/form-section';
import { SystemMessage } from '@/components/app/system-message';
import {
    DocumentTotals,
    moveDocumentLine,
    normalizeEditedLine,
} from '@/components/domain/documents/document-draft-lines';
import { DocumentLineCard } from '@/components/domain/documents/document-line-card';
import { Button } from '@/components/ui/button';
import type { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import type { LineAmounts } from '@/lib/money/line-calculation';
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
    limits: DocumentLineLimits;
    labels: DocumentEditorTranslations;
    errors: Record<string, string>;
    onChange: (
        change: (lines: DocumentLineDraft[]) => DocumentLineDraft[],
    ) => void;
    onAdd: (tax: DocumentTaxDefault | null) => DocumentLineDraft;
    onSelectProduct: (index: number) => void;
};

export function DocumentLineEditor({
    lines,
    calculated,
    totals,
    taxDefault,
    limits,
    labels,
    errors,
    onChange,
    onAdd,
    onSelectProduct,
}: Props) {
    return (
        <FormSection
            title={labels.products_services_section}
            description={labels.products_services_description}
            flush
            headerActions={
                <Button
                    type="button"
                    variant="secondary"
                    onClick={() =>
                        onChange((current) => [...current, onAdd(taxDefault)])
                    }
                >
                    <Plus aria-hidden="true" />
                    {labels.add_line}
                </Button>
            }
        >
            {lines.map((line, index) => (
                <DocumentLineCard
                    key={line.key}
                    line={{
                        ...line,
                        finalLineTotal:
                            calculated[index]?.final_line_total ?? null,
                    }}
                    amounts={calculated[index]}
                    index={index}
                    count={lines.length}
                    limits={limits}
                    labels={labels}
                    errors={errors}
                    inheritedTax={taxDefault}
                    sourceNotice={sourceNotice(line, labels)}
                    onSelectProduct={() => onSelectProduct(index)}
                    onChange={(next) =>
                        onChange((current) =>
                            current.map((item, itemIndex) =>
                                itemIndex === index
                                    ? normalizeEditedLine(item, next)
                                    : item,
                            ),
                        )
                    }
                    onMove={(direction) =>
                        onChange((current) =>
                            moveDocumentLine(current, index, direction),
                        )
                    }
                    onRemove={() =>
                        onChange((current) =>
                            current.filter(
                                (_, itemIndex) => itemIndex !== index,
                            ),
                        )
                    }
                />
            ))}
            <Button
                type="button"
                variant="ghost"
                className="w-full rounded-none border-b border-divider bg-surface-subtle py-3"
                onClick={() =>
                    onChange((current) => [...current, onAdd(taxDefault)])
                }
            >
                <Plus aria-hidden="true" />
                {labels.add_line}
            </Button>
            <DocumentTotals labels={labels} totals={totals} embedded />
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
