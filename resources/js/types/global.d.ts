import type { Auth } from '@/types/auth';
import type { CompanyContext } from '@/types/company';
import type { I18nProps } from '@/types/localization';
import type { PlatformContext } from '@/types/platform';
import type { ImpersonationContext } from '@/types/platform';

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
            companyContext?: CompanyContext;
            platformContext?: PlatformContext;
            impersonation?: ImpersonationContext;
            i18n: I18nProps;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
