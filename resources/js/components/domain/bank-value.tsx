import { CheckIcon, CopyIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

type Props = {
    value: string;
    copyLabel: string;
    copiedLabel: string;
};

export function BankValue({ value, copyLabel, copiedLabel }: Props) {
    const [copiedValue, copy] = useClipboard();
    const copied = copiedValue === value;
    const label = copied ? copiedLabel : copyLabel;

    return (
        <span className="flex min-w-0 items-center gap-1">
            <span className="font-data min-w-0 text-[13px] leading-5 break-all tabular-nums">
                {value}
            </span>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={label}
                title={label}
                onClick={() => void copy(value)}
            >
                {copied ? <CheckIcon /> : <CopyIcon />}
            </Button>
        </span>
    );
}
