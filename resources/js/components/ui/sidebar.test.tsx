import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { SidebarProvider } from '@/components/ui/sidebar-context';
import { SidebarMenuButton } from '@/components/ui/sidebar-menu';

vi.mock('@/hooks/use-mobile', () => ({
    useIsMobile: () => false,
}));

describe('SidebarTrigger', () => {
    it('keeps the desktop collapsed rail reversible', async () => {
        const user = userEvent.setup();

        render(
            <SidebarProvider defaultOpen>
                <SidebarTrigger
                    openLabel="Open navigation"
                    closeLabel="Close navigation"
                />
            </SidebarProvider>,
        );

        await user.click(
            screen.getByRole('button', { name: 'Close navigation' }),
        );

        expect(
            screen.getByRole('button', { name: 'Open navigation' }),
        ).toBeVisible();
    });

    it('preserves a mobile-sized target before the desktop breakpoint', () => {
        render(
            <SidebarProvider>
                <SidebarTrigger />
            </SidebarProvider>,
        );

        expect(screen.getByRole('button')).toHaveClass(
            'size-11',
            'sm:size-8',
        );
    });

    it('centers only the primary visual in the collapsed rail', () => {
        render(
            <SidebarProvider>
                <SidebarMenuButton>
                    <svg aria-label="Company" />
                    <span>Company name</span>
                    <svg aria-label="Switch Company" />
                </SidebarMenuButton>
            </SidebarProvider>,
        );

        expect(screen.getByRole('button')).toHaveClass(
            'group-data-[collapsible=icon]:justify-center',
            'group-data-[collapsible=icon]:gap-0',
            'group-data-[collapsible=icon]:[&>*:not(:first-child)]:hidden',
        );
    });
});
