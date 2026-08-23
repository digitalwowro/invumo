export type SupportedLocale = 'en' | 'ro';

export type CommonTranslations = {
    navigation: {
        dashboard: string;
        quotes: string;
        invoices: string;
        transactions: string;
        customers: string;
        recurring: string;
        products: string;
        settings: string;
    };
    create: {
        label: string;
        invoice: string;
        quote: string;
        customer: string;
    };
    company: {
        switch: string;
        select: string;
        manage: string;
    };
    account: {
        profile: string;
        security: string;
        preferences: string;
        sign_out: string;
    };
    actions: {
        save: string;
        cancel: string;
        continue: string;
        retry: string;
        previous: string;
        next: string;
        more: string;
    };
    accessibility: {
        skip_to_content: string;
        navigation: string;
        navigation_description: string;
        open_navigation: string;
        close_navigation: string;
    };
    pagination: {
        showing: string;
        per_page: string;
    };
    status: {
        draft: string;
        sent: string;
        accepted: string;
        rejected: string;
        issued: string;
        partial: string;
        paid: string;
        overdue: string;
        cancelled: string;
        active: string;
        paused: string;
        completed: string;
    };
    counts: {
        invoices: PluralMessages;
    };
};

export type I18nProps = {
    locale: SupportedLocale;
    supportedLocales: SupportedLocale[];
    common: CommonTranslations;
};

export type PluralMessages = {
    one: string;
    few: string;
    other: string;
};
