export type CatalogPeriodUnit = 'NONE' | 'MONTH' | 'YEAR';

export type CatalogOption = {
    value: string;
    label: string;
};

export type CatalogCurrencyOption = CatalogOption & {
    code: string;
    precision: number;
};

export type ProductServiceFormData = {
    name: string;
    description: string;
    internal_code: string;
    unit_price: string;
    currency_id: string;
    unit: string;
    period_unit: CatalogPeriodUnit;
    tax_preset_id: string;
};

export type ProductServiceRow = {
    id: string;
    name: string;
    description: string | null;
    internalCode: string | null;
    unitPrice: string | null;
    currencyId: string | null;
    currencyCode: string | null;
    unit: string | null;
    periodUnit: CatalogPeriodUnit;
    periodLabel: string;
    taxPresetId: string | null;
    taxPresetName: string | null;
    archived: boolean;
    updatedAt: string | null;
    updateUrl: string;
    archiveUrl: string;
    restoreUrl: string;
    deleteUrl: string;
};

export type ProductServiceCursorPage = {
    items: ProductServiceRow[];
    previousUrl: string | null;
    nextUrl: string | null;
};

export type CatalogFilters = {
    q: string;
    status: 'active' | 'archived' | 'all';
    sort: 'recent' | 'name_asc' | 'name_desc';
    perPage: number;
};

export type CatalogLimits = {
    name: number;
    description: number;
    code: number;
    unit: number;
};

export type CatalogTranslations = {
    index: Record<string, string> & {
        columns: Record<string, string>;
        status_options: Record<string, string>;
        sort_options: Record<string, string>;
    };
    form: {
        create_title: string;
        create_description: string;
        edit_title: string;
        edit_description: string;
        fields: Record<keyof ProductServiceFormData, string>;
        descriptions: Record<string, string>;
        periods: Record<CatalogPeriodUnit, string>;
        no_currency: string;
        no_tax: string;
        save: string;
        create: string;
        unsaved_warning: string;
    };
    actions: Record<string, string>;
};
