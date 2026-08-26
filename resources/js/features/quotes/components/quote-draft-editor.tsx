import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { Grid, Stack } from '@/components/app/layout';
import { Surface } from '@/components/app/surface';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { DocumentLineCard } from '@/components/domain/documents/document-line-card';
import { Button } from '@/components/ui/button';
import { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import { calculateLineAmounts } from '@/lib/money/line-calculation';
import type { LineAmounts } from '@/lib/money/line-calculation';
import type {
    QuoteDraft,
    QuoteLimits,
    QuoteLine,
    QuoteTranslations,
} from '@/types/quote';

type EditorData = {
    edit_version: number;
    lines: QuoteLine[];
};

type Props = {
    quote: QuoteDraft;
    limits: QuoteLimits;
    updateUrl: string;
    labels: QuoteTranslations['edit'];
};

const blankLine = (): QuoteLine => ({
    key: crypto.randomUUID(),
    id: null,
    description: '',
    itemPrice: '',
    quantity: '1',
    unit: '',
    periodUnit: 'NONE',
    periodQuantity: '',
    discountPercentage: '0',
    taxName: '',
    taxPercentage: '0',
    finalLineTotal: null,
});

const formData = (quote: QuoteDraft): EditorData => ({
    edit_version: quote.editVersion,
    lines: quote.lines.map((line) => ({
        ...line,
        key: line.id ?? crypto.randomUUID(),
        description: line.description ?? '',
        itemPrice: line.itemPrice ?? '',
        quantity: line.quantity ?? '',
        unit: line.unit ?? '',
        periodQuantity: line.periodQuantity ?? '',
        taxName: line.taxName ?? '',
    })),
});

export function QuoteDraftEditor({ quote, limits, updateUrl, labels }: Props) {
    const form = useForm<EditorData>(formData(quote));
    const precision = quote.currencyPrecision;
    const calculated = form.data.lines.map((line) =>
        calculate(line, precision),
    );
    const complete = calculated.filter(
        (amounts): amounts is LineAmounts => amounts !== null,
    );
    const totals =
        precision === null
            ? null
            : calculateDocumentAmounts(complete, precision);
    const errors = form.errors as Record<string, string>;

    const changeLines = (change: (lines: QuoteLine[]) => QuoteLine[]) => {
        form.setData((current) => ({
            ...current,
            lines: change(current.lines),
        }));
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            edit_version: data.edit_version,
            lines: data.lines.map((line) => ({
                id: line.id,
                description: line.description,
                item_price: line.itemPrice,
                quantity: line.quantity,
                unit: line.unit,
                period_unit: line.periodUnit,
                period_quantity: line.periodQuantity,
                discount_percentage: line.discountPercentage,
                tax_name: line.taxName,
                tax_percentage: line.taxPercentage,
            })),
        }));
        form.patch(updateUrl, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updated = page.props.quote as QuoteDraft;
                const next = formData(updated);
                form.setData(next);
                form.setDefaults(next);
            },
        });
    };

    return (
        <form onSubmit={submit}>
            <Stack gap="xl">
                <UnsavedChangesGuard
                    active={form.isDirty && !form.processing}
                    message={labels.unsaved_warning}
                />
                {(errors.lines || errors.edit_version) && (
                    <SystemMessage
                        title={errors.lines ?? errors.edit_version}
                        tone="error"
                    />
                )}
                {form.data.lines.map((line, index) => (
                    <DocumentLineCard
                        key={line.key}
                        line={{
                            ...line,
                            finalLineTotal:
                                calculated[index]?.final_line_total ?? null,
                        }}
                        index={index}
                        count={form.data.lines.length}
                        limits={limits}
                        labels={labels}
                        errors={errors}
                        onChange={(next) =>
                            changeLines((lines) =>
                                lines.map((item, itemIndex) =>
                                    itemIndex === index ? next : item,
                                ),
                            )
                        }
                        onMove={(direction) =>
                            changeLines((lines) =>
                                move(lines, index, direction),
                            )
                        }
                        onRemove={() =>
                            changeLines((lines) =>
                                lines.filter(
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
                        changeLines((lines) => [...lines, blankLine()])
                    }
                >
                    <Plus aria-hidden="true" />
                    {labels.add_line}
                </Button>
                <Surface>
                    <Grid columns={3} gap="lg">
                        <Total
                            label={labels.subtotal}
                            value={totals?.grand_subtotal}
                        />
                        <Total
                            label={labels.tax_total}
                            value={totals?.tax_amount}
                        />
                        <Total
                            label={labels.total}
                            value={totals?.final_total}
                            strong
                        />
                    </Grid>
                </Surface>
                <FormActions separated>
                    <SubmitButton
                        processing={form.processing}
                        testId="save-quote"
                    >
                        {labels.save}
                    </SubmitButton>
                </FormActions>
            </Stack>
        </form>
    );
}

function calculate(
    line: QuoteLine,
    precision: number | null,
): LineAmounts | null {
    if (
        precision === null ||
        line.itemPrice === '' ||
        line.quantity === '' ||
        (line.periodUnit !== 'NONE' && line.periodQuantity === '')
    ) {
        return null;
    }

    try {
        return calculateLineAmounts({
            unitPrice: line.itemPrice,
            quantity: line.quantity,
            periodUnit: line.periodUnit,
            periodQuantity:
                line.periodUnit === 'NONE' ? null : line.periodQuantity,
            discountPercentage: line.discountPercentage,
            taxPercentage: line.taxPercentage,
            currencyPrecision: precision,
        });
    } catch {
        return null;
    }
}

function move(
    lines: QuoteLine[],
    index: number,
    direction: -1 | 1,
): QuoteLine[] {
    const destination = index + direction;

    if (destination < 0 || destination >= lines.length) {
        return lines;
    }

    const next = [...lines];
    [next[index], next[destination]] = [next[destination], next[index]];

    return next;
}

function Total({
    label,
    value,
    strong = false,
}: {
    label: string;
    value?: string;
    strong?: boolean;
}) {
    return (
        <div className="space-y-1">
            <p className="text-sm text-foreground-muted">{label}</p>
            <p
                className={`font-mono tabular-nums ${strong ? 'text-xl font-semibold' : 'text-base'}`}
            >
                {value ?? '—'}
            </p>
        </div>
    );
}
