export type CustomerDefaultsRecord = {
    currencyId: string | null;
    documentLanguage: string | null;
    paymentTermDays: string | null;
    taxPresetId: string | null;
};

export type CustomerDefaultOption = {
    value: string;
    label: string;
    disabled?: boolean;
};

export type CustomerDefaultSource = 'CUSTOMER' | 'COMPANY' | 'UNRESOLVED';

export type CustomerResolvedDefaults = {
    currency: {
        id: string;
        code: string;
        precision: number;
        source: CustomerDefaultSource;
    } | null;
    documentLanguage: {
        value: string | null;
        source: CustomerDefaultSource;
    };
    paymentTermDays: {
        value: string | null;
        source: CustomerDefaultSource;
    };
    taxPreset: {
        id: string;
        name: string;
        percentage: string;
        source: CustomerDefaultSource;
    } | null;
    emailAttachmentMode: {
        value: 'SECURE_LINK_ONLY' | 'ATTACH_PDF';
        source: CustomerDefaultSource;
    };
    recipients: {
        count: number;
        source: CustomerDefaultSource;
    };
};

export type CustomerDefaultsTranslations = Record<string, unknown> & {
    head_title: string;
    description: string;
    title: string;
    form_description: string;
    save: string;
    unsaved_warning: string;
    not_configured: string;
    inherit_option: string;
    inherit_payment_term: string;
    fields: Record<string, string>;
    field_descriptions: Record<string, string>;
    languages: Record<string, string>;
    modes: Record<'SECURE_LINK_ONLY' | 'ATTACH_PDF', string>;
    resolved_title: string;
    resolved_description: string;
    resolved_fields: Record<string, string>;
    resolved_currency: string;
    resolved_payment_term: string;
    resolved_tax: string;
    sources: Record<CustomerDefaultSource, string>;
};

export type CustomerDefaultsFormProps = {
    defaults: CustomerDefaultsRecord;
    currencyOptions: CustomerDefaultOption[];
    languageOptions: CustomerDefaultOption[];
    taxPresetOptions: CustomerDefaultOption[];
    companyPaymentTermDays: string | null;
    maxPaymentTermDays: number;
    updateUrl: string | null;
    labels: CustomerDefaultsTranslations;
};
