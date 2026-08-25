export type NumberSeriesResetPolicy = 'NEVER' | 'ANNUAL';

export type CompanyNumberSeries = {
    id: string;
    pattern: string;
    padding: string;
    resetPolicy: NumberSeriesResetPolicy;
    preview: string | null;
};

export type CompanyNumberSeriesConfiguration = {
    quote: CompanyNumberSeries;
    invoice: CompanyNumberSeries;
};

export type CompanyNumberSeriesLimits = {
    patternCharacters: number;
    minimumPadding: number;
    maximumPadding: number;
};

export type CompanyNumberPreviewContext = {
    year: number | null;
    sequence: number;
};

export type CompanyNumberSeriesTranslations = {
    head_title: string;
    title: string;
    description: string;
    quote_title: string;
    quote_description: string;
    invoice_title: string;
    invoice_description: string;
    pattern_description: string;
    padding_description: string;
    reset_policy_description: string;
    preview_label: string;
    preview_invalid: string;
    preview_timezone_required: string;
    save: string;
    unsaved_warning: string;
    fields: {
        pattern: string;
        padding: string;
        reset_policy: string;
        quote_pattern: string;
        quote_padding: string;
        quote_reset_policy: string;
        invoice_pattern: string;
        invoice_padding: string;
        invoice_reset_policy: string;
    };
    reset_policy_options: Record<NumberSeriesResetPolicy, string>;
    feedback: { saved: string };
    errors: {
        pattern_invalid: string;
        invalid_configuration: string;
        timezone_required: string;
    };
};
