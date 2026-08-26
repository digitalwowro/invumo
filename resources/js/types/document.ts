export type DocumentPeriodUnit = 'NONE' | 'MONTH' | 'YEAR';

export type DocumentLineDraft = {
    key: string;
    id: string | null;
    description: string;
    itemPrice: string;
    quantity: string;
    unit: string;
    periodUnit: DocumentPeriodUnit;
    periodQuantity: string;
    discountPercentage: string;
    taxName: string;
    taxPercentage: string;
    finalLineTotal: string | null;
};

export type DocumentLineLimits = {
    description: number;
    unit: number;
    taxName: number;
};

export type DocumentLineLabels = {
    line: string;
    move_up: string;
    move_down: string;
    remove_line: string;
    line_total: string;
    incomplete: string;
    fields: Record<string, string>;
    periods: Record<DocumentPeriodUnit, string>;
};
