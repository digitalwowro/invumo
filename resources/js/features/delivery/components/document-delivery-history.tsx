import { Paperclip } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { DocumentDeliveryRetryDialog } from '@/features/delivery/components/document-delivery-retry-dialog';
import type {
    DeliveryHistoryItem,
    DocumentDeliveryTranslations,
} from '@/types/document-delivery';

type Props = {
    items: DeliveryHistoryItem[];
    locale: string;
    timezone: string;
    labels: DocumentDeliveryTranslations;
};

export function DocumentDeliveryHistory({
    items,
    locale,
    timezone,
    labels,
}: Props) {
    if (items.length === 0) {
        return (
            <p className="text-sm text-foreground-muted">
                {labels.history.empty}
            </p>
        );
    }

    const date = (value: string | null) =>
        value === null
            ? ''
            : new Intl.DateTimeFormat(locale, {
                  dateStyle: 'medium',
                  timeStyle: 'short',
                  timeZone: timezone,
              }).format(new Date(value));

    return (
        <div className="divide-y divide-divider rounded-lg border border-border">
            {items.map((item) => (
                <article
                    key={item.id}
                    className="grid min-w-0 gap-3 p-4 lg:grid-cols-[minmax(0,1fr)_auto]"
                >
                    <div className="min-w-0 space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="quiet">
                                {labels.history.statuses[item.state]}
                            </Badge>
                            {item.attachmentMode === 'ATTACH_PDF' && (
                                <span className="inline-flex items-center gap-1 text-sm text-foreground-muted">
                                    <Paperclip
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {labels.history.attachment}
                                </span>
                            )}
                        </div>
                        <p className="truncate font-medium text-foreground">
                            {item.subject}
                        </p>
                        <p className="text-sm break-words text-foreground-muted">
                            {labels.history.recipients}:{' '}
                            {item.recipients
                                .map((recipient) => recipient.email)
                                .join(', ')}
                        </p>
                        {item.failureSummary && (
                            <p className="text-sm text-danger-text">
                                {item.failureSummary}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap items-start gap-3 text-sm text-foreground-muted lg:flex-col lg:items-end">
                        <span>{date(item.createdAt)}</span>
                        <span>
                            {labels.history.attempts}: {item.attemptCount}
                        </span>
                        {item.retryUrl && (
                            <DocumentDeliveryRetryDialog
                                url={item.retryUrl}
                                labels={labels.history}
                                closeLabel={labels.composer.close}
                            />
                        )}
                    </div>
                </article>
            ))}
        </div>
    );
}
