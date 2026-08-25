import { Form } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { CustomerDetailSections } from '@/features/customers/components/customer-detail-sections';
import { CustomerIdentitySections } from '@/features/customers/components/customer-identity-sections';
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
}: Props) {
    return (
        <Form
            action={actionUrl}
            method={method}
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
        >
            {({ errors, isDirty, processing }) => (
                <Stack gap="2xl">
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
