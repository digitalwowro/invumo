import type { Auth } from '@/types/auth';
import type { I18nProps } from '@/types/localization';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            i18n: I18nProps;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
