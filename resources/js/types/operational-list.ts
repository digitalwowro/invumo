export type OperationalListCursorPage<Row> = {
    items: Row[];
    previousUrl: string | null;
    nextUrl: string | null;
};

export type OperationalListSummaryAmount = {
    currencyCode: string;
    amount: string;
};

export type OperationalListSummaryItem = {
    count: number;
    amounts: OperationalListSummaryAmount[];
};

export type OperationalListDatePresets = {
    today: string;
    monthStart: string;
    ninetyDaysAgo: string;
    nextThirtyDays: string;
    yesterday: string;
};
