import { matchesCurrentUrl, urlPath } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function resolveActiveNavItem(
    items: NavItem[],
    currentUrl: string,
): NavItem | null {
    const explicitItem = items.find((item) => item.isActive === true);

    if (explicitItem) {
        return explicitItem;
    }

    return items.reduce<NavItem | null>((currentMatch, item) => {
        if (
            item.isActive === false ||
            !matchesCurrentUrl(item.activeHref ?? item.href, currentUrl, true)
        ) {
            return currentMatch;
        }

        if (!currentMatch) {
            return item;
        }

        const currentLength =
            urlPath(currentMatch.activeHref ?? currentMatch.href)?.length ?? 0;
        const candidateLength =
            urlPath(item.activeHref ?? item.href)?.length ?? 0;

        return candidateLength > currentLength ? item : currentMatch;
    }, null);
}
