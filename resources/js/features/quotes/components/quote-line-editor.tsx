import { Plus, Search } from 'lucide-react';
import { SystemMessage } from '@/components/app/system-message';
import { DocumentLineCard } from '@/components/domain/documents/document-line-card';
import { Button } from '@/components/ui/button';
import { blankQuoteLine } from '@/features/quotes/components/quote-draft-form-data';
import {
    moveQuoteLine,
    normalizeEditedLine,
} from '@/features/quotes/components/quote-draft-lines';
import type { LineAmounts } from '@/lib/money/line-calculation';
import type {
    QuoteLimits,
    QuoteLine,
    QuoteTaxDefault,
    QuoteTranslations,
} from '@/types/quote';

type Props = {
    lines: QuoteLine[];
    calculated: Array<LineAmounts | null>;
    taxDefault: QuoteTaxDefault | null;
    limits: QuoteLimits;
    labels: QuoteTranslations['edit'];
    errors: Record<string, string>;
    onChange: (change: (lines: QuoteLine[]) => QuoteLine[]) => void;
    onSelectProduct: (index: number) => void;
};

export function QuoteLineEditor({
    lines,
    calculated,
    taxDefault,
    limits,
    labels,
    errors,
    onChange,
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
                    sourceAction={
                        <Button
                            type="button"
                            variant="secondary"
                            data-testid={`quote-product-select-${index}`}
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
                            moveQuoteLine(current, index, direction),
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
                    onChange((current) => [
                        ...current,
                        blankQuoteLine(taxDefault),
                    ])
                }
            >
                <Plus aria-hidden="true" />
                {labels.add_line}
            </Button>
        </>
    );
}

function sourceNotice(line: QuoteLine, labels: QuoteTranslations['edit']) {
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
