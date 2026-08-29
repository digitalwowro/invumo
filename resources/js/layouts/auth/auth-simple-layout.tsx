import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app/app-logo';
import { PageSubtitle, PageTitle } from '@/components/app/typography';
import { home } from '@/routes';
import type { AuthLayoutProps, AuthUiTranslations } from '@/types';

export default function AuthSimpleLayout({ children }: AuthLayoutProps) {
    const { translations } = usePage<{
        translations: AuthUiTranslations;
    }>().props;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-page p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-page"
                        >
                            <AppLogo size="hero" />
                        </Link>

                        <div className="space-y-2 text-center">
                            <PageTitle>{translations.page.title}</PageTitle>
                            <PageSubtitle>
                                {translations.page.description}
                            </PageSubtitle>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
