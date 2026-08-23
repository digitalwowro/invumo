import { describe, expect, it } from 'vitest';
import { cn } from '@/lib/utils';

describe('cn', () => {
    it('resolves conflicting Tailwind utilities predictably', () => {
        expect(cn('px-2', undefined, 'px-4')).toBe('px-4');
    });
});
