import type { Status } from '@/types/status';

export type DesignSystemTranslations = {
    page: {
        title: string;
        subtitle: string;
    };
    sections: {
        typography: string;
        actions: string;
        forms: string;
        statuses: string;
        feedback: string;
        asyncStates: string;
        table: string;
        dialog: string;
    };
    typography: {
        pageTitle: string;
        pageSubtitle: string;
        sectionTitle: string;
        surfaceTitle: string;
        body: string;
        bodyStrong: string;
        secondary: string;
        meta: string;
        metric: string;
        tableValue: string;
        tableAmount: string;
        characters: string;
        status: string;
    };
    actions: {
        primary: string;
        secondary: string;
        ghost: string;
        destructive: string;
        disabled: string;
        loading: string;
        retry: string;
    };
    forms: {
        customer: string;
        customerDescription: string;
        customerPlaceholder: string;
        invalid: string;
        invalidError: string;
        disabled: string;
        inherited: string;
        inheritedCaption: string;
    };
    feedback: Record<
        'neutral' | 'money' | 'warning' | 'error' | 'info',
        { title: string; description: string }
    >;
    asyncStates: {
        loading: string;
        emptyTitle: string;
        emptyDescription: string;
        noResultsTitle: string;
        noResultsDescription: string;
        errorTitle: string;
        errorDescription: string;
    };
    table: {
        ariaLabel: string;
        searchPlaceholder: string;
        columns: {
            invoice: string;
            customer: string;
            issued: string;
            total: string;
            balance: string;
            status: string;
        };
        footer: string;
    };
    dialog: {
        trigger: string;
        title: string;
        description: string;
        confirm: string;
        cancel: string;
        close: string;
    };
};

export type DesignSystemStatusLabels = Record<Status, string>;
