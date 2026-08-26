import { Search } from 'lucide-react';
import { useState } from 'react';
import { TextField } from '@/components/app/form-field';
import { SystemMessage } from '@/components/app/system-message';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type {
    DocumentEditorTranslations,
    DocumentProductDefaults,
    DocumentProductSearchItem,
} from '@/types/document';

type Props = {
    open: boolean;
    searchUrl: string;
    currencyCode: string | null;
    labels: DocumentEditorTranslations;
    canCreate: boolean;
    onOpenChange: (open: boolean) => void;
    onCreate: () => void;
    onSelect: (defaults: DocumentProductDefaults) => void;
};

export function DocumentProductSelector(props: Props) {
    const [query, setQuery] = useState('');
    const [items, setItems] = useState<DocumentProductSearchItem[]>([]);
    const [defaults, setDefaults] = useState<DocumentProductDefaults | null>(
        null,
    );
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    const search = async (value = query) => {
        setLoading(true);
        setFailed(false);

        try {
            const url = new URL(props.searchUrl, window.location.origin);
            url.searchParams.set('q', value);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error();
            }

            const payload = (await response.json()) as {
                items: DocumentProductSearchItem[];
            };
            setItems(payload.items);
        } catch {
            setFailed(true);
        } finally {
            setLoading(false);
        }
    };

    const loadDefaults = async (sourceUrl: string) => {
        setLoading(true);
        setFailed(false);

        try {
            const url = new URL(sourceUrl, window.location.origin);

            if (props.currencyCode !== null) {
                url.searchParams.set('currency_code', props.currencyCode);
            }

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error();
            }

            setDefaults((await response.json()) as DocumentProductDefaults);
        } catch {
            setFailed(true);
        } finally {
            setLoading(false);
        }
    };

    const confirm = () => {
        if (defaults === null) {
            return;
        }

        props.onSelect(defaults);
        setDefaults(null);
        props.onOpenChange(false);
    };

    return (
        <Dialog open={props.open} onOpenChange={props.onOpenChange}>
            <DialogContent
                className="sm:max-w-2xl"
                closeLabel={props.labels.close}
            >
                <DialogHeader>
                    <DialogTitle>
                        {props.labels.product_search_title}
                    </DialogTitle>
                    <DialogDescription>
                        {props.labels.product_search_description}
                    </DialogDescription>
                </DialogHeader>
                {failed && (
                    <SystemMessage
                        title={props.labels.source_error}
                        tone="error"
                    />
                )}
                {defaults === null ? (
                    <div className="space-y-4">
                        <form
                            className="flex flex-col gap-3 sm:flex-row sm:items-end"
                            onSubmit={(event) => {
                                event.preventDefault();
                                void search();
                            }}
                        >
                            <div className="min-w-0 flex-1">
                                <TextField
                                    label={props.labels.product_search_label}
                                    input={{
                                        value: query,
                                        placeholder:
                                            props.labels
                                                .product_search_placeholder,
                                        onChange: (event) =>
                                            setQuery(event.target.value),
                                    }}
                                />
                            </div>
                            <Button
                                type="submit"
                                data-testid="document-product-search"
                                disabled={loading}
                            >
                                <Search aria-hidden="true" />
                                {props.labels.search}
                            </Button>
                        </form>
                        <div className="max-h-72 space-y-2 overflow-y-auto">
                            {items.map((item) => (
                                <Button
                                    key={item.id}
                                    type="button"
                                    variant="secondary"
                                    data-testid="document-product-result"
                                    className="h-auto w-full justify-start py-3 text-left whitespace-normal"
                                    onClick={() =>
                                        void loadDefaults(item.defaultsUrl)
                                    }
                                >
                                    <span>
                                        <span className="block font-medium">
                                            {item.name}
                                        </span>
                                        {item.internalCode && (
                                            <span className="block font-mono text-foreground-muted">
                                                {item.internalCode}
                                            </span>
                                        )}
                                    </span>
                                </Button>
                            ))}
                            {!loading && items.length === 0 && (
                                <p className="py-6 text-center text-sm text-foreground-muted">
                                    {props.labels.no_product_results}
                                </p>
                            )}
                        </div>
                    </div>
                ) : (
                    <ProductPreview defaults={defaults} labels={props.labels} />
                )}
                <DialogFooter>
                    {defaults === null ? (
                        props.canCreate && (
                            <Button
                                type="button"
                                variant="secondary"
                                data-testid="document-inline-product"
                                onClick={props.onCreate}
                            >
                                {props.labels.create_product}
                            </Button>
                        )
                    ) : (
                        <>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setDefaults(null)}
                            >
                                {props.labels.cancel}
                            </Button>
                            <Button
                                type="button"
                                data-testid="document-product-confirm"
                                onClick={confirm}
                            >
                                {props.labels.select_product}
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ProductPreview({
    defaults,
    labels,
}: {
    defaults: DocumentProductDefaults;
    labels: DocumentEditorTranslations;
}) {
    const warning =
        defaults.priceStatus === 'CURRENCY_MISMATCH'
            ? labels.currency_mismatch
            : defaults.priceStatus === 'ENTER_MANUALLY'
              ? labels.manual_price_required
              : null;

    return (
        <div className="space-y-4">
            {warning && <SystemMessage title={warning} tone="warning" />}
            <dl className="grid gap-4 rounded-lg border border-border p-4 sm:grid-cols-2">
                <Summary
                    label={labels.fields.description}
                    value={defaults.description}
                />
                <Summary
                    label={labels.fields.item_price}
                    value={defaults.unitPrice ?? labels.manual_price_required}
                />
                <Summary
                    label={labels.fields.unit}
                    value={defaults.unit ?? labels.not_available}
                />
                <Summary
                    label={labels.tax_default}
                    value={defaults.tax?.name ?? labels.no_tax}
                />
            </dl>
        </div>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-sm text-foreground-muted">{label}</dt>
            <dd className="font-medium whitespace-pre-wrap">{value}</dd>
        </div>
    );
}
