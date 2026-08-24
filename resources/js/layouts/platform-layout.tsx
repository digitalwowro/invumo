import { AppContent } from '@/components/app/app-content';
import { AppShell } from '@/components/app/app-shell';
import { AppSidebarHeader } from '@/components/app/app-sidebar-header';
import { PlatformSidebar } from '@/components/app/platform-sidebar';

export default function PlatformLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <AppShell variant="sidebar">
            <PlatformSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader />
                {children}
            </AppContent>
        </AppShell>
    );
}
