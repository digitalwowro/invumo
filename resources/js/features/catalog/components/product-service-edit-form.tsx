import type { Page } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { SaveButton } from '@/components/app/form-actions';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { ProductServiceFormFields } from '@/components/domain/catalog/product-service-form-fields';
import type {
    CatalogCurrencyOption,
    CatalogLimits,
    CatalogOption,
    CatalogTranslations,
    ProductServiceFormData,
    ProductServiceRecord,
} from '@/types/catalog';

type Props = {
    product: ProductServiceRecord;
    updateUrl: string | null;
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
    labels: CatalogTranslations['form'];
    formId?: string;
    messages?: ReactNode;
    renderHeader?: (primaryAction: ReactNode) => ReactNode;
};

function formData(product: ProductServiceRecord): ProductServiceFormData {
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

export function ProductServiceEditForm(props: Props) {
    const form = useForm<ProductServiceFormData>(formData(props.product));
    const generalError = (form.errors as Record<string, string>)
        .product_service;
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!props.updateUrl) {
            return;
        }

        form.patch(props.updateUrl, {
            preserveScroll: true,
            onSuccess: (page: Page) => {
                const next = formData(
                    page.props.product as ProductServiceRecord,
                );
                form.setData(next);
                form.setDefaults(next);
            },
        });
    };

    return (
        <Stack gap="xl">
            {props.renderHeader?.(
                props.updateUrl ? (
                    <SaveButton
                        form={props.formId}
                        processing={form.processing}
                        dirty={form.isDirty}
                        testId="save-product-service"
                    >
                        {props.labels.save}
                    </SaveButton>
                ) : null,
            )}
            {props.messages}
            <form id={props.formId} onSubmit={submit}>
                <UnsavedChangesGuard
                    active={
                        Boolean(props.updateUrl) &&
                        form.isDirty &&
                        !form.processing
                    }
                    message={props.labels.unsaved_warning}
                />
                {generalError && (
                    <SystemMessage title={generalError} tone="error" />
                )}
                <FormSection
                    title={props.labels.edit_title}
                    description={props.labels.edit_description}
                >
                    <ProductServiceFormFields
                        {...props}
                        data={form.data}
                        errors={form.errors}
                        disabled={!props.updateUrl}
                        onChange={form.setData}
                    />
                </FormSection>
            </form>
        </Stack>
    );
}
