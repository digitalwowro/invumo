import { Surface } from '@/components/app/surface';
import { Body, SecondaryText, SurfaceTitle } from '@/components/app/typography';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { RenderedEmailTemplate } from '@/types/company-email-template';

type Props = {
    preview: RenderedEmailTemplate;
    title: string;
    description: string;
    override: boolean;
    overrideLabel: string;
    systemLabel: string;
};

export function EmailTemplatePreview({
    preview,
    title,
    description,
    override,
    overrideLabel,
    systemLabel,
}: Props) {
    return (
        <Surface className="min-w-0">
            <div className="space-y-6">
                <div className="space-y-2">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <SurfaceTitle>{title}</SurfaceTitle>
                        <Badge variant={override ? 'positive' : 'muted'}>
                            {override ? overrideLabel : systemLabel}
                        </Badge>
                    </div>
                    <SecondaryText>{description}</SecondaryText>
                </div>
                <div className="min-w-0 space-y-5 rounded-md border border-border bg-surface-inset p-5">
                    <div className="font-semibold break-words">
                        <Body>{preview.subject}</Body>
                    </div>
                    <div className="break-words whitespace-pre-wrap">
                        <Body>{preview.body}</Body>
                    </div>
                    <Button type="button" disabled>
                        {preview.buttonLabel}
                    </Button>
                    {preview.signature && (
                        <div className="break-words whitespace-pre-wrap">
                            <Body>{preview.signature}</Body>
                        </div>
                    )}
                </div>
            </div>
        </Surface>
    );
}
