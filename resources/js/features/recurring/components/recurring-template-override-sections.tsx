import { RecurringCompanyOverrides } from '@/features/recurring/components/recurring-company-overrides';
import { RecurringCustomerValueOverrides } from '@/features/recurring/components/recurring-customer-value-overrides';
import { RecurringIdentityOverrides } from '@/features/recurring/components/recurring-identity-overrides';
import { RecurringRecipientOverrides } from '@/features/recurring/components/recurring-recipient-overrides';
import type { CustomerTranslations } from '@/types/customer';
import type { DocumentCustomerFormOptions } from '@/types/document';
import type {
    RecurringInheritance,
    RecurringInheritanceProps,
    RecurringTemplateLimits,
    RecurringTranslations,
} from '@/types/recurring';

type Props = RecurringInheritanceProps & {
    value: RecurringInheritance;
    limits: RecurringTemplateLimits;
    labels: RecurringTranslations['editor'];
    customerLabels: CustomerTranslations;
    customerForm: DocumentCustomerFormOptions;
    errors: Record<string, string>;
    onChange: (value: RecurringInheritance) => void;
};

export function RecurringTemplateOverrideSections(props: Props) {
    const labels = props.labels.inheritance;

    return (
        <>
            <RecurringIdentityOverrides
                value={props.value}
                labels={labels}
                customerLabels={props.customerLabels.form}
                customerForm={props.customerForm}
                errors={props.errors}
                onChange={props.onChange}
            />
            <RecurringCustomerValueOverrides
                value={props.value}
                labels={labels}
                deliveryLabels={props.customerLabels.delivery.modes}
                currencyOptions={props.currencyOptions}
                languageOptions={props.languageOptions}
                taxPresetOptions={props.taxPresetOptions}
                maxDayOffset={props.limits.maxDayOffset}
                errors={props.errors}
                onChange={props.onChange}
            />
            <RecurringRecipientOverrides
                value={props.value}
                labels={labels}
                customerLabels={props.customerLabels}
                nameLimit={props.customerForm.limits.name}
                emailLimit={props.customerForm.limits.email}
                errors={props.errors}
                onChange={props.onChange}
            />
            <RecurringCompanyOverrides
                value={props.value}
                labels={labels}
                termsLabel={props.labels.fields.terms_and_conditions}
                notesLabel={props.labels.fields.notes}
                bankLabel={props.labels.bank_account}
                bankAccountOptions={props.bankAccountOptions}
                reminderRelationOptions={props.reminderRelationOptions}
                termsLimit={props.limits.termsAndConditions}
                notesLimit={props.limits.notes}
                maxDayOffset={props.limits.maxDayOffset}
                errors={props.errors}
                onChange={props.onChange}
            />
        </>
    );
}
