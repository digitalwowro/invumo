export type OutwardParty = {
    displayName: string;
    legalName?: string | null;
    contact?: string[];
    address: string[];
    registrations: string[];
    contacts: string[];
};

export type OutwardDocumentLine = {
    position: number;
    description: string;
    quantity: string;
    unitPrice: string;
    discount: string | null;
    tax: string | null;
    total: string;
};

export type OutwardDocument = {
    kind: string;
    number: string;
    status: string;
    language: string;
    issueDate: string | null;
    validUntil: string | null;
    dueDate: string | null;
    customerReference: string | null;
    theme: {
        accentColor: string;
        onAccentColor: string;
        textColor: string;
        ruleColor: string;
    };
    company: OutwardParty;
    customer: OutwardParty | null;
    lines: OutwardDocumentLine[];
    subtotal: string;
    taxTotal: string;
    total: string;
    bank: { label: string; value: string }[];
    termsAndConditions: string | null;
    notes: string | null;
    logoUrl: string | null;
    labels: Record<string, string>;
};
