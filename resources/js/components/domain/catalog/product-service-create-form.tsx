import type { Page } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { ProductServiceFormFields } from '@/components/domain/catalog/product-service-form-fields';
import { Button } from '@/components/ui/button';
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
    cancelLabel?: string;
    onCancel?: () => void;
    onSuccess?: (page: Page) => void;
    formId?: string;
    showActions?: boolean;
    renderHeader?: (primaryAction: ReactNode) => ReactNode;
};

export function ProductServiceCreateForm(props: Props) {
    const form = useForm<ProductServiceFormData>(emptyForm);
    const generalError = (form.errors as Record<string, string>)
        .product_service;
    const cancel = () => {
        if (
            form.isDirty &&
            !form.processing &&
            !window.confirm(props.labels.unsaved_warning)
        ) {
            return;
        }

        form.reset();
        form.clearErrors();
        props.onCancel?.();
    };

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
        <Stack gap="xl">
            {props.renderHeader?.(
                <SubmitButton form={props.formId} processing={form.processing}>
                    {props.labels.create}
                </SubmitButton>,
            )}
            <form id={props.formId} onSubmit={submit}>
                <FormSection
                    title={props.labels.create_title}
                    description={props.labels.create_description}
                    actions={
                        props.showActions !== false ? (
                            <FormActions>
                                {props.onCancel && props.cancelLabel && (
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={cancel}
                                    >
                                        {props.cancelLabel}
                                    </Button>
                                )}
                                <SubmitButton processing={form.processing}>
                                    {props.labels.create}
                                </SubmitButton>
                            </FormActions>
                        ) : undefined
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
        </Stack>
    );
}
