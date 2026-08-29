import { Plus, Search } from 'lucide-react';
import { SystemMessage } from '@/components/app/system-message';
import {
    moveDocumentLine,
    normalizeEditedLine,
} from '@/components/domain/documents/document-draft-lines';
import { DocumentLineCard } from '@/components/domain/documents/document-line-card';
import { Button } from '@/components/ui/button';
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
    taxDefault,
    limits,
    labels,
    errors,
    onChange,
    onAdd,
    onSelectProduct,
}: Props) {
    return (
        <>
            {lines.map((line, index) => (
                <DocumentLineCard
                    key={line.key}
                    line={{
                        ...line,
                        finalLineTotal:
                            calculated[index]?.final_line_total ?? null,
                    }}
                    index={index}
                    count={lines.length}
                    limits={limits}
                    labels={labels}
                    errors={errors}
                    inheritedTax={taxDefault}
                    sourceAction={
                        <Button
                            type="button"
                            variant="secondary"
                            data-testid={`document-product-select-${index}`}
                            onClick={() => onSelectProduct(index)}
                        >
                            <Search aria-hidden="true" />
                            {labels.select_product}
                        </Button>
                    }
                    sourceNotice={sourceNotice(line, labels)}
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
                variant="secondary"
                onClick={() =>
                    onChange((current) => [...current, onAdd(taxDefault)])
                }
            >
                <Plus aria-hidden="true" />
                {labels.add_line}
            </Button>
        </>
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
