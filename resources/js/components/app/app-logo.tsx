import { cn } from '@/lib/utils';

type AppLogoProps = {
    className?: string;
    size?: 'sidebar' | 'header' | 'hero';
};

export default function AppLogo({ className, size = 'sidebar' }: AppLogoProps) {
    return (
        <span
            aria-label="Invumo"
            role="img"
            data-size={size}
            className={cn('product-mark', className)}
        >
            <span aria-hidden="true" className="product-mark-word">
                INVUMO
            </span>
        </span>
    );
}
