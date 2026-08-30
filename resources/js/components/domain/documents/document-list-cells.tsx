import {
    BodyStrong,
    SecondaryText,
    TableValue,
} from '@/components/app/typography';

export function DocumentListIdentity(props: {
    number: string;
    customerName: string | null;
    customerEmail?: string | null;
    notAvailable: string;
}) {
    return (
        <div className="flex flex-col gap-1">
            <BodyStrong>
                {props.number} · {props.customerName ?? props.notAvailable}
            </BodyStrong>
            {props.customerEmail && (
                <SecondaryText>{props.customerEmail}</SecondaryText>
            )}
        </div>
    );
}

export function DocumentListDates(props: {
    issueDate: string | null;
    deadline: string | null;
    deadlinePrefix: string;
    notAvailable: string;
    deadlineIsDanger?: boolean;
}) {
    return (
        <div className="flex flex-col gap-1">
            <TableValue>{props.issueDate ?? props.notAvailable}</TableValue>
            <span
                className={
                    props.deadlineIsDanger
                        ? 'font-data text-xs text-danger-text tabular-nums'
                        : 'font-data text-xs text-foreground-muted tabular-nums'
                }
            >
                {props.deadline
                    ? `${props.deadlinePrefix} ${props.deadline}`
                    : props.notAvailable}
            </span>
        </div>
    );
}
