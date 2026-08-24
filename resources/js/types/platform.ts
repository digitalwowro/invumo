export type PlatformAbilities = {
    view_platform: boolean;
    manage_platform_operators: boolean;
    manage_users: boolean;
    manage_accounts: boolean;
    view_platform_audit: boolean;
    impersonate_users: boolean;
};

export type PlatformContext = {
    label: string;
    navigationDescription: string;
    overviewUrl: string;
    navigation: {
        overview: string;
        users: string;
        accounts: string;
        companies: string;
        planLifecycle: string;
        audit: string;
    };
    routes: {
        users: string;
        accounts: string;
        companies: string;
        planLifecycle: string;
        audit: string;
    };
    abilities: PlatformAbilities;
};

export type ImpersonationContext = {
    active: true;
    user: {
        name: string;
        email: string;
    };
    message: string;
    exitLabel: string;
    exitUrl: string;
};

export type PlanStatus =
    'TRIALING' | 'ACTIVE' | 'PAST_DUE' | 'CANCELED' | 'EXPIRED';

export type PlatformCursorPage<Row> = {
    items: Row[];
    previousUrl: string | null;
    nextUrl: string | null;
};

export type PlatformUserRow = {
    id: string;
    name: string;
    email: string;
    verified: boolean;
    suspended: boolean;
    lastLoginAt: string | null;
    createdAt: string | null;
    planName: string | null;
    planStatus: PlanStatus | null;
    companyCount: number;
    isOperator: boolean;
    suspendUrl: string;
    reactivateUrl: string;
    impersonateUrl: string;
};

export type PlatformAccountRow = {
    id: string;
    ownerName: string;
    ownerEmail: string;
    planId: string;
    planName: string;
    planStatus: PlanStatus;
    planStartedAt: string;
    trialEndsAt: string | null;
    accessEndsAt: string | null;
    cancelAtPeriodEnd: boolean;
    endedAt: string | null;
    suspended: boolean;
    companyCount: number;
    suspendUrl: string;
    reactivateUrl: string;
    planUrl: string;
};

export type PlatformCompanyRow = {
    id: string;
    name: string;
    ownerName: string;
    ownerEmail: string;
    memberCount: number;
    archived: boolean;
    createdAt: string | null;
};

export type PlatformAuditRow = {
    id: string;
    actorName: string | null;
    impersonatorName: string | null;
    action: string;
    targetType: string;
    targetId: string;
    reason: string | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    occurredAt: string;
};

export type PlatformActivityRow = Pick<
    PlatformAuditRow,
    'id' | 'actorName' | 'action' | 'targetType' | 'occurredAt'
>;

export type PlatformPlan = {
    id: string;
    name: string;
};

export type PlatformCommonTranslations = {
    search: string;
    apply_filters: string;
    previous: string;
    next: string;
    close: string;
    cancel: string;
    confirm: string;
    reason: string;
    reason_placeholder: string;
    actions: string;
    never: string;
    not_available: string;
    yes: string;
    no: string;
    active: string;
    suspended: string;
    verified: string;
    unverified: string;
    loading: string;
    empty_title: string;
    empty_description: string;
    no_results_title: string;
    no_results_description: string;
    error_title: string;
    error_description: string;
};

type PlatformPageTranslations = Record<string, string>;

export type PlatformTranslations = {
    label: string;
    navigation_description: string;
    navigation: {
        overview: string;
        users: string;
        accounts: string;
        companies: string;
        plan_lifecycle: string;
        audit: string;
    };
    common: PlatformCommonTranslations;
    overview: PlatformPageTranslations;
    users: PlatformPageTranslations;
    accounts: PlatformPageTranslations;
    companies: PlatformPageTranslations;
    plan_lifecycle: PlatformPageTranslations;
    audit: PlatformPageTranslations;
    statuses: Record<PlanStatus, string>;
    feedback: PlatformPageTranslations;
    errors: PlatformPageTranslations;
};
