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
    QuoteCustomerSearchItem,
    QuoteCustomerSelection,
    QuoteTranslations,
} from '@/types/quote';

type Props = {
    open: boolean;
    searchUrl: string;
    companyDefaultsUrl: string;
    labels: QuoteTranslations['edit'];
    canCreate: boolean;
    onOpenChange: (open: boolean) => void;
    onCreate: () => void;
    onSelect: (selection: QuoteCustomerSelection) => void;
};

export function QuoteCustomerSelector(props: Props) {
    const [query, setQuery] = useState('');
    const [items, setItems] = useState<QuoteCustomerSearchItem[]>([]);
    const [preview, setPreview] = useState<QuoteCustomerSelection | null>(null);
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
                items: QuoteCustomerSearchItem[];
            };
            setItems(payload.items);
        } catch {
            setFailed(true);
        } finally {
            setLoading(false);
        }
    };

    const loadPreview = async (url: string) => {
        setLoading(true);
        setFailed(false);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error();
            }

            setPreview((await response.json()) as QuoteCustomerSelection);
        } catch {
            setFailed(true);
        } finally {
            setLoading(false);
        }
    };

    const confirm = () => {
        if (preview === null) {
            return;
        }

        props.onSelect(preview);
        setPreview(null);
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
                        {props.labels.customer_search_title}
                    </DialogTitle>
                    <DialogDescription>
                        {props.labels.customer_search_description}
                    </DialogDescription>
                </DialogHeader>
                {failed && (
                    <SystemMessage
                        title={props.labels.source_error}
                        tone="error"
                    />
                )}
                {preview === null ? (
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
                                    label={props.labels.customer_search_label}
                                    input={{
                                        value: query,
                                        placeholder:
                                            props.labels
                                                .customer_search_placeholder,
                                        onChange: (event) =>
                                            setQuery(event.target.value),
                                    }}
                                />
                            </div>
                            <Button
                                type="submit"
                                data-testid="quote-customer-search"
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
                                    data-testid="quote-customer-result"
                                    className="h-auto w-full justify-start py-3 text-left whitespace-normal"
                                    onClick={() =>
                                        void loadPreview(item.previewUrl)
                                    }
                                >
                                    <span>
                                        <span className="block font-medium">
                                            {item.displayName}
                                        </span>
                                        <span className="block text-foreground-muted">
                                            {item.email ??
                                                item.externalReference ??
                                                props.labels.not_available}
                                        </span>
                                    </span>
                                </Button>
                            ))}
                            {!loading && items.length === 0 && (
                                <p className="py-6 text-center text-sm text-foreground-muted">
                                    {props.labels.no_customer_results}
                                </p>
                            )}
                        </div>
                    </div>
                ) : (
                    <CustomerPreview
                        selection={preview}
                        labels={props.labels}
                    />
                )}
                <DialogFooter>
                    {preview === null ? (
                        <>
                            {props.canCreate && (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-testid="quote-inline-customer"
                                    onClick={props.onCreate}
                                >
                                    {props.labels.create_customer}
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() =>
                                    void loadPreview(props.companyDefaultsUrl)
                                }
                            >
                                {props.labels.clear_customer}
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setPreview(null)}
                            >
                                {props.labels.cancel}
                            </Button>
                            <Button
                                type="button"
                                data-testid="quote-customer-confirm"
                                onClick={confirm}
                            >
                                {props.labels.confirm_customer}
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function CustomerPreview({
    selection,
    labels,
}: {
    selection: QuoteCustomerSelection;
    labels: QuoteTranslations['edit'];
}) {
    return (
        <div className="space-y-4">
            <SystemMessage
                title={labels.customer_confirmation_title}
                description={labels.customer_confirmation_description}
                tone="warning"
            />
            <dl className="grid gap-4 rounded-lg border border-border p-4 sm:grid-cols-2">
                <Summary
                    label={labels.select_customer}
                    value={selection.displayName ?? labels.no_customer}
                />
                <Summary
                    label={labels.currency}
                    value={selection.currencyCode ?? labels.not_available}
                />
                <Summary
                    label={labels.language}
                    value={selection.documentLanguage ?? labels.not_available}
                />
                <Summary
                    label={labels.tax_default}
                    value={selection.taxDefault?.name ?? labels.no_tax}
                />
                <Summary
                    label={labels.recipients}
                    value={String(selection.recipientCount)}
                />
                <Summary
                    label={labels.delivery}
                    value={
                        selection.emailAttachmentMode === 'ATTACH_PDF'
                            ? labels.attach_pdf
                            : labels.secure_link_only
                    }
                />
            </dl>
        </div>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-sm text-foreground-muted">{label}</dt>
            <dd className="truncate font-medium">{value}</dd>
        </div>
    );
}
