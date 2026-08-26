import type { Page } from '@inertiajs/core';
import { ProductServiceCreateForm } from '@/components/domain/catalog/product-service-create-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { CatalogTranslations } from '@/types/catalog';
import type { QuoteCatalogFormOptions, QuoteTranslations } from '@/types/quote';

type Props = {
    open: boolean;
    storeUrl: string;
    options: QuoteCatalogFormOptions;
    quoteLabels: QuoteTranslations['edit'];
    catalogLabels: CatalogTranslations;
    onOpenChange: (open: boolean) => void;
    onCreated: (page: Page) => void;
};

export function InlineProductDialog({
    open,
    storeUrl,
    options,
    quoteLabels,
    catalogLabels,
    onOpenChange,
    onCreated,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sm:max-w-4xl"
                closeLabel={quoteLabels.close}
            >
                <DialogHeader>
                    <DialogTitle>
                        {quoteLabels.create_product_title}
                    </DialogTitle>
                    <DialogDescription>
                        {quoteLabels.create_product_description}
                    </DialogDescription>
                </DialogHeader>
                <ProductServiceCreateForm
                    storeUrl={storeUrl}
                    currencyOptions={options.currencyOptions}
                    taxPresetOptions={options.taxPresetOptions}
                    periodOptions={options.periodOptions}
                    limits={options.limits}
                    labels={catalogLabels.form}
                    onSuccess={onCreated}
                />
            </DialogContent>
        </Dialog>
    );
}
