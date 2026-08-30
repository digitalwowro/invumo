import { Form } from '@inertiajs/react';
import { Clipboard, Link2, RefreshCw, Unlink } from 'lucide-react';
import { toast } from 'sonner';
import { ContentSection } from '@/components/app/content-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
    PublicDocumentLink,
    PublicDocumentTranslations,
} from '@/types/public-document';

type Props = {
    link: PublicDocumentLink;
    labels: PublicDocumentTranslations['management'];
};

export function PublicDocumentLinkPanel({ link, labels }: Props) {
    const expiry =
        link.expiresAt === null
            ? null
            : new Intl.DateTimeFormat(link.locale, {
                  dateStyle: 'medium',
                  timeStyle: 'short',
                  timeZone: link.timezone,
              }).format(new Date(link.expiresAt));
    const copy = async () => {
        if (link.url === null) {
            return;
        }

        try {
            await navigator.clipboard.writeText(link.url);
            toast.success(labels.copied);
        } catch {
            toast.error(labels.copy_failed);
        }
    };

    return (
        <ContentSection
            title={labels.title}
            description={labels.description}
            headerActions={
                <Badge variant="quiet">{labels.statuses[link.status]}</Badge>
            }
        >
            <div className="flex flex-col gap-4 p-5 sm:p-6">
                {link.url && (
                    <code className="rounded-md border border-divider bg-surface-subtle p-3 text-sm break-all">
                        {link.url}
                    </code>
                )}
                {link.expiresAt && expiry && (
                    <p className="text-sm text-muted-foreground">
                        {labels.expires.replace(':date', expiry)}
                    </p>
                )}
                <div className="flex flex-wrap gap-2">
                    {link.url && (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={copy}
                        >
                            <Clipboard data-icon="inline-start" />
                            {labels.copy}
                        </Button>
                    )}
                    {link.status !== 'ACTIVE' && (
                        <Form action={link.createUrl} method="post">
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    <Link2 data-icon="inline-start" />
                                    {link.status === 'DISABLED'
                                        ? labels.re_enable
                                        : labels.create}
                                </Button>
                            )}
                        </Form>
                    )}
                    {link.regenerateUrl && (
                        <Form action={link.regenerateUrl} method="post">
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    disabled={processing}
                                >
                                    <RefreshCw data-icon="inline-start" />
                                    {labels.regenerate}
                                </Button>
                            )}
                        </Form>
                    )}
                    {link.revokeUrl && (
                        <Form action={link.revokeUrl} method="delete">
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    <Unlink data-icon="inline-start" />
                                    {labels.revoke}
                                </Button>
                            )}
                        </Form>
                    )}
                </div>
            </div>
        </ContentSection>
    );
}
