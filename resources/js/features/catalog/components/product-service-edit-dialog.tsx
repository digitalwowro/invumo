import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { SystemMessage } from '@/components/app/system-message';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { ProductServiceFormFields } from '@/features/catalog/components/product-service-form-fields';
import type {
    CatalogCurrencyOption,
    CatalogLimits,
    CatalogOption,
    CatalogTranslations,
    ProductServiceFormData,
    ProductServiceRow,
} from '@/types/catalog';

type Props = {
    product: ProductServiceRow;
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
    labels: CatalogTranslations;
    cancelLabel: string;
    closeLabel: string;
};

function initialData(product: ProductServiceRow): ProductServiceFormData {
    return {
        name: product.name,
        description: product.description ?? '',
        internal_code: product.internalCode ?? '',
        unit_price: product.unitPrice ?? '',
        currency_id: product.currencyId ?? '',
        unit: product.unit ?? '',
        period_unit: product.periodUnit,
        tax_preset_id: product.taxPresetId ?? '',
    };
}

export function ProductServiceEditDialog(props: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm<ProductServiceFormData>(initialData(props.product));
    const generalError = (form.errors as Record<string, string>)
        .product_service;
    const changeOpen = (next: boolean) => {
        if (
            !next &&
            form.isDirty &&
            !form.processing &&
            !window.confirm(props.labels.form.unsaved_warning)
        ) {
            return;
        }

        if (next) {
            const initial = initialData(props.product);
            form.setDefaults(initial);
            form.setData(initial);
            form.clearErrors();
        }

        setOpen(next);
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(props.product.updateUrl, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button variant="secondary">{props.labels.actions.edit}</Button>
            </DialogTrigger>
            <DialogContent
                closeLabel={props.closeLabel}
                className="sm:max-w-3xl"
            >
                <DialogHeader>
                    <DialogTitle>{props.labels.form.edit_title}</DialogTitle>
                    <DialogDescription>
                        {props.labels.form.edit_description}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    {generalError && (
                        <SystemMessage title={generalError} tone="error" />
                    )}
                    <ProductServiceFormFields
                        {...props}
                        labels={props.labels.form}
                        data={form.data}
                        errors={form.errors}
                        onChange={form.setData}
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {props.cancelLabel}
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.isDirty}
                        >
                            {form.processing && <Spinner />}
                            {props.labels.form.save}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
