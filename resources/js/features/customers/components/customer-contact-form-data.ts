import type {
    CustomerContact,
    CustomerContactFormData,
} from '@/types/customer';

export function emptyCustomerContact(): CustomerContactFormData {
    return {
        name: '',
        email: '',
        phone: '',
        position_title: '',
        is_primary: false,
        is_billing: false,
    };
}

export function customerContactData(
    contact: CustomerContact,
): CustomerContactFormData {
    return {
        name: contact.name,
        email: contact.email ?? '',
        phone: contact.phone ?? '',
        position_title: contact.positionTitle ?? '',
        is_primary: contact.isPrimary,
        is_billing: contact.isBilling,
    };
}
