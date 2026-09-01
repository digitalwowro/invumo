import type { ReactNode, RefObject } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

export type DocumentWorkspaceFact = {
    label: string;
    value: string;
};

type Props = {
    renderPrimary: () => ReactNode;
    repeatedPrimaryTestId: string;
    factsTitle: string;
    facts: DocumentWorkspaceFact[];
    sharingTitle: string;
    sharing: DocumentWorkspaceFact[];
    sharingActionLabel: string;
    onSharingAction: () => void;
};

export function DocumentWorkspaceSidebar(props: Props) {
    const repeatBoundaryRef = useRef<HTMLDivElement>(null);
    const { repeatPrimary, stickyTop } = useRepeatedPrimary(repeatBoundaryRef);

    return (
        <aside className="relative min-w-0">
            <div className="flex min-w-0 flex-col gap-4">
                {props.renderPrimary()}
                <FactsCard title={props.factsTitle} facts={props.facts} />
                <FactsCard
                    title={props.sharingTitle}
                    facts={props.sharing}
                    actionLabel={props.sharingActionLabel}
                    onAction={props.onSharingAction}
                />
            </div>
            <div ref={repeatBoundaryRef} className="h-px" aria-hidden="true" />
            {repeatPrimary && (
                <div
                    className="mt-4 hidden xl:sticky xl:block"
                    style={{ top: stickyTop }}
                    data-testid={props.repeatedPrimaryTestId}
                >
                    {props.renderPrimary()}
                </div>
            )}
        </aside>
    );
}

function FactsCard({
    title,
    facts,
    actionLabel,
    onAction,
}: {
    title: string;
    facts: DocumentWorkspaceFact[];
    actionLabel?: string;
    onAction?: () => void;
}) {
    return (
        <Card className="gap-4 p-5 py-5">
            <h2 className="font-data text-[11px] font-bold tracking-[0.09em] text-foreground-muted uppercase">
                {title}
            </h2>
            <dl className="flex flex-col gap-3">
                {facts.map((fact) => (
                    <div
                        key={fact.label}
                        className="flex min-w-0 items-baseline justify-between gap-4 text-sm"
                    >
                        <dt className="shrink-0 text-foreground-muted">
                            {fact.label}
                        </dt>
                        <dd className="truncate text-right font-medium">
                            {fact.value}
                        </dd>
                    </div>
                ))}
            </dl>
            {actionLabel && onAction && (
                <Button
                    type="button"
                    variant="secondary"
                    className="w-full"
                    onClick={onAction}
                >
                    {actionLabel}
                </Button>
            )}
        </Card>
    );
}

function useRepeatedPrimary(boundaryRef: RefObject<HTMLDivElement | null>) {
    const [repeatPrimary, setRepeatPrimary] = useState(false);
    const [stickyTop, setStickyTop] = useState(192);

    useEffect(() => {
        const header = document.querySelector<HTMLElement>(
            '[data-slot="document-workspace-header"]',
        );
        const updateStickyTop = () =>
            setStickyTop((header?.offsetHeight ?? 176) + 16);

        updateStickyTop();

        if (!header || typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(updateStickyTop);
        observer.observe(header);

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        const boundary = boundaryRef.current;

        if (!boundary || typeof IntersectionObserver === 'undefined') {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry) {
                    setRepeatPrimary(entry.boundingClientRect.top <= stickyTop);
                }
            },
            {
                rootMargin: `-${stickyTop}px 0px 0px 0px`,
                threshold: 0,
            },
        );
        observer.observe(boundary);

        return () => observer.disconnect();
    }, [boundaryRef, stickyTop]);

    return { repeatPrimary, stickyTop };
}
