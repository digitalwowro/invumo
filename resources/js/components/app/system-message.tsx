import { cva } from 'class-variance-authority';
import { AlertCircle, AlertTriangle, CheckCircle2, Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type SystemMessageTone = 'neutral' | 'money' | 'warning' | 'error' | 'info';

const messageVariants = cva('border-l-4', {
    variants: {
        tone: {
            neutral:
                'border-primary bg-primary text-primary-foreground [&_[data-slot=alert-description]]:text-primary-foreground',
            money: 'border-l-money-fill [&>svg]:text-money-text',
            warning: 'border-l-warning-fill [&>svg]:text-warning-text',
            error: 'border-l-danger-fill [&>svg]:text-danger-text',
            info: 'border-l-border-strong [&>svg]:text-foreground',
        },
    },
    defaultVariants: {
        tone: 'info',
    },
});

const toneIcons = {
    neutral: Info,
    money: CheckCircle2,
    warning: AlertTriangle,
    error: AlertCircle,
    info: Info,
} as const;

type SystemMessageProps = {
    title: string;
    description?: string;
    tone?: SystemMessageTone;
    action?: ReactNode;
};

export function SystemMessage({
    title,
    description,
    tone = 'info',
    action,
}: SystemMessageProps) {
    const Icon = toneIcons[tone];
    const liveRole = tone === 'error' ? 'alert' : 'status';

    return (
        <Alert className={messageVariants({ tone })} role={liveRole}>
            <Icon aria-hidden="true" />
            <AlertTitle>{title}</AlertTitle>
            {description && <AlertDescription>{description}</AlertDescription>}
            {action && <div className="col-start-2 mt-2">{action}</div>}
        </Alert>
    );
}

export type { SystemMessageTone };
