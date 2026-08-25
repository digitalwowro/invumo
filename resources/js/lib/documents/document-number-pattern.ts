const numberToken = '{NUMBER}';
const yearToken = '{YEAR}';
const maximumPatternCharacters = 120;

type PreviewInput = {
    pattern: string;
    padding: string;
    year: number | null;
    sequence: number;
};

export function renderDocumentNumberPreview({
    pattern,
    padding,
    year,
    sequence,
}: PreviewInput): string | null {
    const numericPadding = Number(padding);
    const literal = pattern
        .replaceAll(numberToken, '')
        .replaceAll(yearToken, '');

    if (
        pattern.trim() !== pattern ||
        Array.from(pattern).length > maximumPatternCharacters ||
        hasControlCharacter(pattern) ||
        count(pattern, numberToken) !== 1 ||
        count(pattern, yearToken) > 1 ||
        literal.includes('{') ||
        literal.includes('}') ||
        !Number.isInteger(numericPadding) ||
        numericPadding < 1 ||
        numericPadding > 12 ||
        !Number.isInteger(sequence) ||
        sequence < 1 ||
        (pattern.includes(yearToken) && year === null)
    ) {
        return null;
    }

    return pattern
        .replaceAll(
            yearToken,
            year === null ? '' : year.toString().padStart(4, '0'),
        )
        .replace(
            numberToken,
            sequence.toString().padStart(numericPadding, '0'),
        );
}

function count(value: string, token: string): number {
    return value.split(token).length - 1;
}

function hasControlCharacter(value: string): boolean {
    return Array.from(value).some((character) => {
        const codePoint = character.codePointAt(0);

        return (
            codePoint !== undefined && (codePoint <= 31 || codePoint === 127)
        );
    });
}
