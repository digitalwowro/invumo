import { router } from '@inertiajs/react';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import { SectionHeader } from '@/components/app/section-header';
import { Surface } from '@/components/app/surface';
import type { CompanyMembersTranslations } from '@/types';

type Props = {
    leaveUrl: string;
    translations: CompanyMembersTranslations;
    cancelLabel: string;
    closeLabel: string;
};

export function CompanyMembershipExit({
    leaveUrl,
    translations,
    cancelLabel,
    closeLabel,
}: Props) {
    return (
        <Surface>
            <SectionHeader
                title={translations.leave_title}
                description={translations.leave_description}
                action={
                    <ConfirmationDialog
                        triggerLabel={translations.leave_company}
                        title={translations.leave_company_title}
                        description={translations.leave_company_description}
                        confirmLabel={translations.confirm_leave_company}
                        cancelLabel={cancelLabel}
                        closeLabel={closeLabel}
                        onConfirm={() => router.delete(leaveUrl)}
                    />
                }
            />
        </Surface>
    );
}
