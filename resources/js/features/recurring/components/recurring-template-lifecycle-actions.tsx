import { router } from '@inertiajs/react';
import { Cluster } from '@/components/app/layout';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import type {
    RecurringTemplateState,
    RecurringTranslations,
} from '@/types/recurring';

type Transition = 'activate' | 'pause' | 'resume' | 'complete';

type Props = {
    state: RecurringTemplateState;
    editVersion: number;
    urls: Record<Transition, string>;
    duplicateUrl: string;
    duplicateCreationKey: string;
    canManageAutomation: boolean;
    canDuplicate: boolean;
    labels: RecurringTranslations['lifecycle'];
    closeLabel: string;
};

export function RecurringTemplateLifecycleActions(props: Props) {
    const transitions = availableTransitions(props.state);

    return (
        <Cluster gap="sm">
            {props.canManageAutomation &&
                transitions.map((transition) => (
                    <ConfirmationDialog
                        key={transition}
                        triggerLabel={props.labels[transition]}
                        title={props.labels.title[transition]}
                        description={props.labels.description[transition]}
                        confirmLabel={props.labels.confirm[transition]}
                        cancelLabel={props.labels.cancel}
                        closeLabel={props.closeLabel}
                        tone="default"
                        onConfirm={() =>
                            router.post(props.urls[transition], {
                                edit_version: props.editVersion,
                                confirmed: true,
                            })
                        }
                    />
                ))}
            {props.canDuplicate && (
                <ConfirmationDialog
                    triggerLabel={props.labels.duplicate}
                    title={props.labels.title.duplicate}
                    description={props.labels.description.duplicate}
                    confirmLabel={props.labels.confirm.duplicate}
                    cancelLabel={props.labels.cancel}
                    closeLabel={props.closeLabel}
                    tone="default"
                    onConfirm={() =>
                        router.post(props.duplicateUrl, {
                            creation_key: props.duplicateCreationKey,
                        })
                    }
                />
            )}
        </Cluster>
    );
}

function availableTransitions(state: RecurringTemplateState): Transition[] {
    if (state === 'DRAFT') {
        return ['activate'];
    }

    if (state === 'ACTIVE') {
        return ['pause', 'complete'];
    }

    if (state === 'PAUSED') {
        return ['resume', 'complete'];
    }

    return [];
}
