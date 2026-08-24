import { ImpersonationBanner } from '@/components/app/impersonation-banner';

export default function ImpersonationLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <div className="min-h-svh bg-page">
            <ImpersonationBanner />
            {children}
        </div>
    );
}
