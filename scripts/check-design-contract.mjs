import { readdir, readFile } from 'node:fs/promises';
import { extname, join, relative } from 'node:path';

const projectRoot = process.cwd();
const roots = ['resources/js', 'resources/views'];
const extensions = new Set(['.ts', '.tsx', '.js', '.jsx', '.php']);

const rules = [
    {
        name: 'raw colour value',
        pattern: /#[0-9a-f]{3,8}\b|\b(?:rgb|rgba|hsl|hsla|oklch)\s*\(/gi,
    },
    {
        name: 'Tailwind palette colour',
        pattern:
            /\b(?:bg|text|border|ring|outline|decoration|fill|stroke)-(?:(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}|black|white)(?:\/\d{1,3})?\b/gi,
    },
    {
        name: 'arbitrary Tailwind colour',
        pattern:
            /\b(?:bg|text|border|ring|outline|decoration|fill|stroke)-\[(?:#|rgb|rgba|hsl|hsla|oklch|color:)[^\]]+\]/gi,
    },
    {
        name: 'dark-mode variant',
        pattern: /\bdark:/g,
    },
    {
        name: 'unapproved component shadow',
        pattern: /\bshadow-(?!overlay\b|none\b)[^\s"'`]+/g,
    },
    {
        name: 'arbitrary component radius',
        pattern: /\brounded-\[[^\]]+\]/g,
    },
];

async function collectFiles(directory) {
    const entries = await readdir(directory, { withFileTypes: true });
    const files = [];

    for (const entry of entries) {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            if (['actions', 'routes', 'wayfinder'].includes(entry.name)) {
                continue;
            }

            files.push(...(await collectFiles(path)));
        } else if (extensions.has(extname(entry.name))) {
            files.push(path);
        }
    }

    return files;
}

const violations = [];

for (const root of roots) {
    for (const file of await collectFiles(join(projectRoot, root))) {
        const source = await readFile(file, 'utf8');
        const lines = source.split('\n');

        for (const rule of rules) {
            rule.pattern.lastIndex = 0;

            for (const match of source.matchAll(rule.pattern)) {
                const line = source.slice(0, match.index).split('\n').length;
                violations.push({
                    file: relative(projectRoot, file),
                    line,
                    rule: rule.name,
                    match: match[0],
                    context: lines[line - 1]?.trim(),
                });
            }
        }
    }
}

if (violations.length > 0) {
    console.error('Design contract violations found:\n');

    for (const violation of violations) {
        console.error(
            `${violation.file}:${violation.line} — ${violation.rule}: ${violation.match}`,
        );
        console.error(`  ${violation.context}`);
    }

    process.exitCode = 1;
} else {
    console.log('Design contract check passed.');
}
