export const DEFAULT_OUTWARD_BRAND_COLOR = '#14181C';
export const OUTWARD_BRAND_TEXT_CONTRAST_MINIMUM = 4.5;
export const OUTWARD_BRAND_RULE_CONTRAST_MINIMUM = 3;

const BLACK = '#000000';
const WHITE = '#FFFFFF';
const brandColorPattern = /^#[0-9A-F]{6}$/;

export type OutwardBrandTheme = {
    accentColor: string;
    onAccentColor: string;
    textColor: string;
    ruleColor: string;
};

export function isOutwardBrandColor(color: string): boolean {
    return brandColorPattern.test(color);
}

export function resolveOutwardBrandTheme(color: string): OutwardBrandTheme {
    if (!isOutwardBrandColor(color)) {
        throw new Error(
            'Outward brand colors require uppercase #RRGGBB notation.',
        );
    }

    const whiteContrast = contrastRatio(color, WHITE);
    const blackContrast = contrastRatio(color, BLACK);

    return {
        accentColor: color,
        onAccentColor: whiteContrast >= blackContrast ? WHITE : BLACK,
        textColor:
            whiteContrast >= OUTWARD_BRAND_TEXT_CONTRAST_MINIMUM
                ? color
                : DEFAULT_OUTWARD_BRAND_COLOR,
        ruleColor:
            whiteContrast >= OUTWARD_BRAND_RULE_CONTRAST_MINIMUM
                ? color
                : DEFAULT_OUTWARD_BRAND_COLOR,
    };
}

function contrastRatio(first: string, second: string): number {
    const lighter = Math.max(luminance(first), luminance(second));
    const darker = Math.min(luminance(first), luminance(second));

    return (lighter + 0.05) / (darker + 0.05);
}

function luminance(color: string): number {
    const channels = [
        color.slice(1, 3),
        color.slice(3, 5),
        color.slice(5, 7),
    ].map((channel) => {
        const value = Number.parseInt(channel, 16) / 255;

        return value <= 0.04045
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}
