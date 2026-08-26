import type {
    DocumentLineDraft,
    DocumentLineLimits,
    DocumentPeriodUnit,
} from '@/types/document';

export type QuotePeriodUnit = DocumentPeriodUnit;
export type QuoteLine = DocumentLineDraft;

export type QuoteDraft = {
    id: string;
    number: string;
    issueDate: string | null;
    currencyCode: string | null;
    currencyPrecision: number | null;
    editVersion: number;
    subtotal: string | null;
    taxTotal: string | null;
    total: string | null;
    lines: Omit<QuoteLine, 'key'>[];
};

export type QuoteLimits = DocumentLineLimits;

export type QuoteTranslations = {
    create: Record<string, string>;
    edit: {
        head_title: string;
        title: string;
        description: string;
        line: string;
        add_line: string;
        remove_line: string;
        move_up: string;
        move_down: string;
        line_total: string;
        incomplete: string;
        subtotal: string;
        tax_total: string;
        total: string;
        save: string;
        unsaved_warning: string;
        currency_required: string;
        fields: Record<string, string>;
        periods: Record<QuotePeriodUnit, string>;
    };
    feedback: Record<string, string>;
    errors: Record<string, string>;
};
