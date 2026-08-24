export type AuthUiTranslations = {
    shared: {
        email: string;
        emailPlaceholder: string;
        password: string;
        passwordPlaceholder: string;
        confirmPassword: string;
        currentPassword: string;
        newPassword: string;
        name: string;
        fullNamePlaceholder: string;
        showPassword: string;
        hidePassword: string;
    };
    page: Record<string, string> & {
        title: string;
        description: string;
        headTitle: string;
    };
};

export type SettingsUiTranslations = {
    layout: {
        title: string;
        description: string;
        navigationLabel: string;
        profile: string;
        security: string;
    };
    shared: {
        save: string;
        cancel: string;
        password: string;
        passwordPlaceholder: string;
        showPassword: string;
        hidePassword: string;
    };
    page: Record<string, string> & {
        headTitle: string;
        title: string;
        description: string;
    };
};

export type DashboardTranslations = {
    title: string;
    subtitle: string;
    members: string;
};

export type ErrorPageTranslations = {
    page: {
        headTitle: string;
        title: string;
        description: string;
        action: string;
    };
};
