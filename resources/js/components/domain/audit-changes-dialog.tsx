import { Stack } from '@/components/app/layout';
import { ResponsiveDialog } from '@/components/app/responsive-dialog';
import { MetaLabel } from '@/components/app/typography';
import { Button } from '@/components/ui/button';
import type { AuditValue } from '@/types/company-audit';

type Props = {
    before: AuditValue | null;
    after: AuditValue | null;
    triggerLabel: string;
    title: string;
    description: string;
    beforeLabel: string;
    afterLabel: string;
    notAvailable: string;
    closeLabel: string;
};

export function AuditChangesDialog(props: Props) {
    return (
        <ResponsiveDialog
            trigger={
                <Button type="button" variant="secondary">
                    {props.triggerLabel}
                </Button>
            }
            title={props.title}
            description={props.description}
            closeLabel={props.closeLabel}
            size="wide"
        >
            <div className="grid min-w-0 gap-4 md:grid-cols-2">
                <AuditValueBlock
                    label={props.beforeLabel}
                    value={props.before}
                    fallback={props.notAvailable}
                />
                <AuditValueBlock
                    label={props.afterLabel}
                    value={props.after}
                    fallback={props.notAvailable}
                />
            </div>
        </ResponsiveDialog>
    );
}

function AuditValueBlock(props: {
    label: string;
    value: AuditValue | null;
    fallback: string;
}) {
    return (
        <Stack gap="sm" className="min-w-0">
            <MetaLabel>{props.label}</MetaLabel>
            <pre className="font-data max-h-96 min-w-0 overflow-auto rounded-md bg-surface-inset p-3 text-xs leading-5 whitespace-pre-wrap text-foreground">
                {props.value
                    ? JSON.stringify(props.value, null, 2)
                    : props.fallback}
            </pre>
        </Stack>
    );
}
