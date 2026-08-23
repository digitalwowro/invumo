import { readdir, readFile } from 'node:fs/promises';
import { extname, join, relative } from 'node:path';

const projectRoot = process.cwd();
const roots = ['resources/js', 'resources/views'];
const extensions = new Set(['.ts', '.tsx', '.js', '.jsx', '.php']);
const productNameRoots = [
    'app',
    'bootstrap',
    'config',
    'database',
    'lang',
    'public',
    'resources',
    'routes',
    'tests',
];
const productNameExtensions = new Set([
    '.css',
    '.html',
    '.js',
    '.jsx',
    '.json',
    '.mjs',
    '.php',
    '.svg',
    '.ts',
    '.tsx',
    '.txt',
    '.xml',
    '.yaml',
    '.yml',
]);
const productNameMetadataFiles = [
    '.env.example',
    'components.json',
    'composer.json',
    'package.json',
    'phpunit.xml',
    'vite.config.ts',
];
const ignoredFilenameDirectories = new Set([
    '.git',
    'cache',
    'node_modules',
    'storage',
    'vendor',
]);
const productNameTypo = new RegExp(`\\b${'invum'}a\\b`, 'i');

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

async function collectAllFiles(directory) {
    const entries = await readdir(directory, { withFileTypes: true });
    const files = [];

    for (const entry of entries) {
        if (entry.isDirectory() && ignoredFilenameDirectories.has(entry.name)) {
            continue;
        }

        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            files.push(...(await collectAllFiles(path)));
        } else {
            files.push(path);
        }
    }

    return files;
}

const violations = [];

for (const file of await collectAllFiles(projectRoot)) {
    const path = relative(projectRoot, file);

    if (productNameTypo.test(path)) {
        violations.push({
            file: path,
            line: 1,
            rule: 'product-name typo in filename',
            match: path,
            context: path,
        });
    }
}

for (const root of productNameRoots) {
    for (const file of await collectAllFiles(join(projectRoot, root))) {
        if (!productNameExtensions.has(extname(file))) {
            continue;
        }

        const source = await readFile(file, 'utf8');
        const match = source.match(productNameTypo);

        if (match) {
            const line = source.slice(0, match.index).split('\n').length;
            violations.push({
                file: relative(projectRoot, file),
                line,
                rule: 'product-name typo in application content',
                match: match[0],
                context: source.split('\n')[line - 1]?.trim(),
            });
        }
    }
}

for (const metadataFile of productNameMetadataFiles) {
    const source = await readFile(join(projectRoot, metadataFile), 'utf8');
    const match = source.match(productNameTypo);

    if (match) {
        const line = source.slice(0, match.index).split('\n').length;
        violations.push({
            file: metadataFile,
            line,
            rule: 'product-name typo in metadata',
            match: match[0],
            context: source.split('\n')[line - 1]?.trim(),
        });
    }
}

for (const root of roots) {
    for (const file of await collectFiles(join(projectRoot, root))) {
        const source = await readFile(file, 'utf8');
        const lines = source.split('\n');
        const projectPath = relative(projectRoot, file);

        if (
            projectPath.startsWith('resources/js/pages/') &&
            /\b(?:className|style)\s*=/.test(source)
        ) {
            const match = source.match(/\b(?:className|style)\s*=/);
            const line = source.slice(0, match.index).split('\n').length;

            violations.push({
                file: projectPath,
                line,
                rule: 'page-owned visual override',
                match: match[0],
                context: lines[line - 1]?.trim(),
            });
        }

        for (const rule of rules) {
            rule.pattern.lastIndex = 0;

            for (const match of source.matchAll(rule.pattern)) {
                const line = source.slice(0, match.index).split('\n').length;
                violations.push({
                    file: projectPath,
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
