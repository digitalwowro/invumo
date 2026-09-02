import { LoaderCircle } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type {
    DocumentLineLabels,
    DocumentProductDefaults,
    DocumentProductSearchItem,
} from '@/types/document';

type Props = {
    id: string;
    value: string;
    searchUrl: string;
    currencyCode: string | null;
    labels: DocumentLineLabels;
    testId: string;
    maxLength: number;
    quiet?: boolean;
    onChange: (value: string) => void;
    onSelect: (defaults: DocumentProductDefaults) => void;
};

export function DocumentProductCombobox(props: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const listId = useId();
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<DocumentProductSearchItem[]>([]);
    const [activeIndex, setActiveIndex] = useState(0);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const customName = props.value.trim();
    const customOptionId = `${listId}-custom`;
    const showSearch = () => {
        setItems([]);
        setFailed(false);
        setLoading(true);
        setOpen(true);
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            try {
                const url = new URL(props.searchUrl, window.location.origin);
                url.searchParams.set('q', props.value);
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error();
                }

                const payload = (await response.json()) as {
                    items: DocumentProductSearchItem[];
                };
                setItems(payload.items);
                setActiveIndex(0);
            } catch {
                if (!controller.signal.aborted) {
                    setFailed(true);
                    setItems([]);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }, 180);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [open, props.searchUrl, props.value]);

    const select = async (item: DocumentProductSearchItem) => {
        setOpen(false);
        setLoading(true);
        setFailed(false);

        try {
            const url = new URL(item.defaultsUrl, window.location.origin);

            if (props.currencyCode !== null) {
                url.searchParams.set('currency_code', props.currencyCode);
            }

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error();
            }

            props.onSelect((await response.json()) as DocumentProductDefaults);
        } catch {
            setFailed(true);
            setItems([]);
            setLoading(true);
            setOpen(true);
        } finally {
            setLoading(false);
        }
    };

    const confirmCustom = () => setOpen(false);

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            setOpen(false);

            return;
        }

        if (event.key === 'Tab') {
            setOpen(false);

            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (!open) {
                showSearch();
            }

            setActiveIndex((current) => {
                const direction = event.key === 'ArrowDown' ? 1 : -1;

                return Math.max(
                    0,
                    Math.min(items.length - 1, current + direction),
                );
            });

            return;
        }

        if (event.key !== 'Enter' || !open) {
            return;
        }

        if (items[activeIndex]) {
            event.preventDefault();
            void select(items[activeIndex]);

            return;
        }

        if (!loading && customName) {
            event.preventDefault();
            confirmCustom();
        }
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverAnchor asChild>
                <Input
                    id={props.id}
                    ref={inputRef}
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls={listId}
                    aria-expanded={open}
                    aria-activedescendant={
                        open && items[activeIndex]
                            ? `${listId}-${items[activeIndex].id}`
                            : open && !loading && customName
                              ? customOptionId
                              : undefined
                    }
                    aria-label={props.labels.product_or_service}
                    autoComplete="off"
                    data-test={props.testId}
                    className={cn(
                        'h-8 font-semibold',
                        props.quiet &&
                            'border-transparent bg-transparent shadow-none hover:border-input hover:bg-background',
                    )}
                    value={props.value}
                    maxLength={props.maxLength}
                    placeholder={props.labels.product_search_placeholder}
                    onFocus={showSearch}
                    onChange={(event) => {
                        props.onChange(event.target.value);
                        showSearch();
                    }}
                    onKeyDown={onKeyDown}
                />
            </PopoverAnchor>
            <PopoverContent
                align="start"
                className="w-80 max-w-[calc(100vw-2rem)] p-1"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => {
                    if (event.target === inputRef.current) {
                        event.preventDefault();
                    }
                }}
            >
                <div
                    id={listId}
                    role="listbox"
                    aria-label={props.labels.product_search_label}
                    aria-busy={loading}
                    className="max-h-64 overflow-y-auto"
                >
                    {loading ? (
                        <div className="flex justify-center p-4">
                            <LoaderCircle
                                className="animate-spin"
                                aria-label={props.labels.search}
                            />
                        </div>
                    ) : (
                        <>
                            {failed ? (
                                <p className="px-2 py-2 text-xs text-destructive">
                                    {props.labels.source_error}
                                </p>
                            ) : items.length === 0 ? (
                                <p className="px-2 py-2 text-xs text-foreground-muted">
                                    {props.labels.no_product_results}
                                </p>
                            ) : (
                                items.map((item, index) => (
                                    <Button
                                        key={item.id}
                                        id={`${listId}-${item.id}`}
                                        type="button"
                                        role="option"
                                        aria-selected={index === activeIndex}
                                        variant="ghost"
                                        data-testid="document-product-result"
                                        className="h-auto w-full justify-start py-2 text-left whitespace-normal"
                                        onMouseDown={(event) =>
                                            event.preventDefault()
                                        }
                                        onMouseEnter={() =>
                                            setActiveIndex(index)
                                        }
                                        onClick={() => void select(item)}
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate font-medium">
                                                {item.name}
                                            </span>
                                            {item.internalCode ? (
                                                <span className="font-data block truncate text-xs text-foreground-muted">
                                                    {item.internalCode}
                                                </span>
                                            ) : null}
                                        </span>
                                    </Button>
                                ))
                            )}
                            {items.length === 0 && customName ? (
                                <Button
                                    id={customOptionId}
                                    type="button"
                                    role="option"
                                    variant="secondary"
                                    data-testid="document-product-custom"
                                    className="h-auto w-full justify-start py-2 text-left whitespace-normal"
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onClick={confirmCustom}
                                >
                                    {props.labels.use_custom_product.replace(
                                        ':name',
                                        customName,
                                    )}
                                </Button>
                            ) : null}
                        </>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
