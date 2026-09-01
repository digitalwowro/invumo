import type { Page } from '@inertiajs/core';
import { Form } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { CustomerDetailSections } from '@/components/domain/customers/customer-detail-sections';
import { CustomerIdentitySections } from '@/components/domain/customers/customer-identity-sections';
import type {
    CustomerFieldLimits,
    CustomerOption,
    CustomerRecord,
    CustomerTranslations,
} from '@/types/customer';

type Props = {
    customer: CustomerRecord;
    actionUrl: string;
    method: 'post' | 'patch';
    submitLabel: string;
    countryOptions: CustomerOption[];
    customerTypeOptions: CustomerOption[];
    limits: CustomerFieldLimits;
    labels: CustomerTranslations['form'];
    disabled?: boolean;
    unsavedWarning: string;
    onSuccess?: (page: Page) => void;
};

export function CustomerForm({
    customer,
    actionUrl,
    method,
    submitLabel,
    countryOptions,
    customerTypeOptions,
    limits,
    labels,
    disabled = false,
    unsavedWarning,
    onSuccess,
}: Props) {
    return (
        <Form
            action={actionUrl}
            method={method}
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
            onSuccess={onSuccess}
        >
            {({ errors, isDirty, processing }) => (
                <Stack gap="xl">
                    <UnsavedChangesGuard
                        active={!disabled && isDirty && !processing}
                        message={unsavedWarning}
                    />
                    <CustomerIdentitySections
                        customer={customer}
                        customerTypeOptions={customerTypeOptions}
                        limits={limits}
                        labels={labels}
                        errors={errors}
                        disabled={disabled}
                    />
                    <CustomerDetailSections
                        customer={customer}
                        countryOptions={countryOptions}
                        limits={limits}
                        labels={labels}
                        errors={errors}
                        disabled={disabled}
                        processing={processing}
                        submitLabel={submitLabel}
                    />
                </Stack>
            )}
        </Form>
    );
}
