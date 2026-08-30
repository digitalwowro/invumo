import { describe, expect, it } from 'vitest';
import { matchesCurrentUrl, urlPath } from '@/hooks/use-current-url';

describe('current URL matching', () => {
    it('matches an exact route and its child routes', () => {
        expect(
            matchesCurrentUrl(
                '/companies/company-1/invoices',
                '/companies/company-1/invoices/invoice-1',
                true,
            ),
        ).toBe(true);
    });

    it('does not treat a similarly prefixed sibling as a child route', () => {
        expect(
            matchesCurrentUrl(
                '/companies/company-1/invoices',
                '/companies/company-1/invoices-archive',
                true,
            ),
        ).toBe(false);
    });

    it('ignores query strings and trailing slashes', () => {
        expect(
            matchesCurrentUrl(
                '/companies/company-1/recurring?status=failed',
                '/companies/company-1/recurring/',
                true,
            ),
        ).toBe(true);
    });

    it('normalizes absolute URLs', () => {
        expect(urlPath('https://app.invumo.com/settings/profile')).toBe(
            '/settings/profile',
        );
    });
});
