import { render } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';

const inertia = vi.hoisted(() => ({
    before: undefined as
        | ((event: {
              detail: {
                  visit: { method: string; prefetch: boolean };
              };
          }) => boolean | undefined)
        | undefined,
}));

vi.mock('@inertiajs/react', () => ({
    router: {
        on: vi.fn(
            (event: string, callback: NonNullable<typeof inertia.before>) => {
                if (event === 'before') {
                    inertia.before = callback;
                }

                return vi.fn();
            },
        ),
    },
}));

describe('UnsavedChangesGuard', () => {
    beforeEach(() => {
        inertia.before = undefined;
        vi.restoreAllMocks();
    });

    it('ignores prefetches but confirms real get navigation', () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        render(<UnsavedChangesGuard active message="Leave?" />);

        expect(
            inertia.before?.({
                detail: { visit: { method: 'get', prefetch: true } },
            }),
        ).toBeUndefined();
        expect(confirm).not.toHaveBeenCalled();

        expect(
            inertia.before?.({
                detail: { visit: { method: 'get', prefetch: false } },
            }),
        ).toBe(false);
        expect(confirm).toHaveBeenCalledOnce();
        expect(confirm).toHaveBeenCalledWith('Leave?');
    });
});
