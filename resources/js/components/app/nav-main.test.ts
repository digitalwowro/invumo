import { describe, expect, it } from 'vitest';
import { resolveActiveNavItem } from '@/components/app/resolve-active-nav-item';
import type { NavItem } from '@/types';

describe('resolveActiveNavItem', () => {
    it('keeps a section selected on a detail route', () => {
        const quotes = {
            title: 'Quotes',
            href: '/companies/company-1/quotes',
        } satisfies NavItem;
        const invoices = {
            title: 'Invoices',
            href: '/companies/company-1/invoices',
        } satisfies NavItem;

        expect(
            resolveActiveNavItem(
                [quotes, invoices],
                '/companies/company-1/invoices/invoice-1',
            ),
        ).toBe(invoices);
    });

    it('uses a shared parent route for Company settings screens', () => {
        const settings = {
            title: 'Settings',
            href: '/companies/company-1/settings/profile',
            activeHref: '/companies/company-1/settings',
        } satisfies NavItem;

        expect(
            resolveActiveNavItem(
                [settings],
                '/companies/company-1/settings/members',
            ),
        ).toBe(settings);
    });

    it('prefers the most specific section when parent routes overlap', () => {
        const overview = {
            title: 'Overview',
            href: '/platform',
        } satisfies NavItem;
        const users = {
            title: 'Users',
            href: '/platform/users',
        } satisfies NavItem;

        expect(
            resolveActiveNavItem([overview, users], '/platform/users/user-1'),
        ).toBe(users);
    });
});
