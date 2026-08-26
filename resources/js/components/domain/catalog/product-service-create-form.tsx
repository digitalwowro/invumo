import type { Page } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { FormSection } from '@/components/app/form-section';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { ProductServiceFormFields } from '@/components/domain/catalog/product-service-form-fields';
import type {
    CatalogCurrencyOption,
    CatalogLimits,
    CatalogOption,
    CatalogTranslations,
    ProductServiceFormData,
} from '@/types/catalog';

const emptyForm: ProductServiceFormData = {
    name: '',
    description: '',
    internal_code: '',
    unit_price: '',
    currency_id: '',
    unit: '',
    period_unit: 'NONE',
    tax_preset_id: '',
};

type Props = {
    storeUrl: string;
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
    labels: CatalogTranslations['form'];
    onSuccess?: (page: Page) => void;
};

export function ProductServiceCreateForm(props: Props) {
    const form = useForm<ProductServiceFormData>(emptyForm);
    const generalError = (form.errors as Record<string, string>)
        .product_service;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(props.storeUrl, {
            preserveScroll: true,
            onSuccess: (page) => {
                form.reset();
                props.onSuccess?.(page);
            },
        });
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title={props.labels.create_title}
                description={props.labels.create_description}
                actions={
                    <FormActions>
                        <SubmitButton processing={form.processing}>
                            {props.labels.create}
                        </SubmitButton>
                    </FormActions>
                }
            >
                <UnsavedChangesGuard
                    active={form.isDirty && !form.processing}
                    message={props.labels.unsaved_warning}
                />
                {generalError && (
                    <SystemMessage title={generalError} tone="error" />
                )}
                <ProductServiceFormFields
                    {...props}
                    data={form.data}
                    errors={form.errors}
                    onChange={form.setData}
                />
            </FormSection>
        </form>
    );
}
