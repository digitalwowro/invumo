import { Link } from '@inertiajs/react';
import { MetaLabel } from '@/components/app/typography';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { interpolate } from '@/lib/translations';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    shownCount: number;
    previousUrl: string | null;
    nextUrl: string | null;
    perPage: number;
    onPerPageChange: (perPage: number) => void;
    labels: OperationalListTranslations;
};

export function OperationalListPagination(props: Props) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <span className="font-data text-xs text-foreground-muted tabular-nums">
                {interpolate(props.labels.shown_count, {
                    count: props.shownCount,
                })}
            </span>
            <div className="flex flex-wrap items-center justify-end gap-2">
                <label className="flex items-center gap-2">
                    <MetaLabel>{props.labels.per_page_label}</MetaLabel>
                    <Select
                        value={String(props.perPage)}
                        onValueChange={(value) =>
                            props.onPerPageChange(Number(value))
                        }
                    >
                        <SelectTrigger
                            className="w-20"
                            aria-label={props.labels.per_page_label}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent align="end">
                            <SelectGroup>
                                {[10, 25, 50, 100].map((value) => (
                                    <SelectItem
                                        key={value}
                                        value={String(value)}
                                    >
                                        {value}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </label>
                <nav
                    aria-label={`${props.labels.previous} / ${props.labels.next}`}
                    className="flex gap-2"
                >
                    <PageLink
                        href={props.previousUrl}
                        label={props.labels.previous}
                    />
                    <PageLink href={props.nextUrl} label={props.labels.next} />
                </nav>
            </div>
        </div>
    );
}

function PageLink({ href, label }: { href: string | null; label: string }) {
    return href ? (
        <Button asChild variant="secondary">
            <Link href={href} preserveScroll>
                {label}
            </Link>
        </Button>
    ) : (
        <Button disabled variant="secondary">
            {label}
        </Button>
    );
}
