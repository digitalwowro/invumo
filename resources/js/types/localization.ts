export type SupportedLocale = string;

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
        create: string;
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
        failed: string;
        pending: string;
        claimed: string;
        skipped: string;
        superseded: string;
        suppressed: string;
        issued: string;
        partial: string;
        expired: string;
        paid: string;
        unpaid: string;
        overdue: string;
        cancelled: string;
        archived: string;
        active: string;
        paused: string;
        completed: string;
    };
    counts: {
        invoices: PluralMessages;
    };
    impersonation: {
        message: string;
        exit: string;
        ended: string;
        nested_denied: string;
        suspended_title: string;
        suspended_description: string;
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
