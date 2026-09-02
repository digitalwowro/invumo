import { useLayoutEffect, useState } from 'react';

const TABLE_MINIMUM_WIDTH = 1120;

export function useWideDocumentTable() {
    const [container, setContainer] = useState<HTMLDivElement | null>(null);
    const [wide, setWide] = useState(false);

    useLayoutEffect(() => {
        if (!container) {
            return;
        }

        const update = () =>
            setWide(
                container.getBoundingClientRect().width >= TABLE_MINIMUM_WIDTH,
            );
        update();

        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(update);
        observer.observe(container);

        return () => observer.disconnect();
    }, [container]);

    return { containerRef: setContainer, wide };
}
