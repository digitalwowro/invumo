import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';

const SOFT_LIMIT = 300;
const HARD_LIMIT = 500;

const INCLUDED_EXTENSIONS = new Set([
    '.cjs',
    '.css',
    '.js',
    '.jsx',
    '.mjs',
    '.php',
    '.scss',
    '.ts',
    '.tsx',
]);

const EXCLUDED_PREFIXES = [
    'bootstrap/cache/',
    'docs/',
    'lang/',
    'node_modules/',
    'public/build/',
    'resources/js/actions/',
    'resources/js/routes/',
    'resources/js/wayfinder/',
    'vendor/',
];

// Each entry must cite an explicit Owner-approved decision and explain why the
// file cannot be split without reducing cohesion. No exceptions are approved.
const APPROVED_EXCEPTIONS = new Map();

function extensionOf(path) {
    const basename = path.split('/').at(-1) ?? path;
    const dot = basename.lastIndexOf('.');

    return dot === -1 ? '' : basename.slice(dot);
}

function isExcluded(path) {
    return EXCLUDED_PREFIXES.some((prefix) => path.startsWith(prefix));
}

function physicalLineCount(contents) {
    if (contents.length === 0) {
        return 0;
    }

    const newlines = contents.match(/\n/g)?.length ?? 0;

    return newlines + (contents.endsWith('\n') ? 0 : 1);
}

const paths = execFileSync(
    'git',
    ['ls-files', '--cached', '--others', '--exclude-standard', '-z'],
    { encoding: 'utf8' },
)
    .split('\0')
    .filter(Boolean)
    .filter((path) => existsSync(path))
    .filter((path) => INCLUDED_EXTENSIONS.has(extensionOf(path)))
    .filter((path) => !isExcluded(path));

const results = paths
    .map((path) => ({
        path,
        lines: physicalLineCount(readFileSync(path, 'utf8')),
        exception: APPROVED_EXCEPTIONS.get(path),
    }))
    .sort((left, right) => right.lines - left.lines);

const softWarnings = results.filter(
    ({ lines, exception }) =>
        lines > SOFT_LIMIT && lines <= HARD_LIMIT && !exception,
);
const hardFailures = results.filter(
    ({ lines, exception }) => lines > HARD_LIMIT && !exception,
);
const exceptions = results.filter(({ exception }) => exception);

for (const { path, lines } of softWarnings) {
    console.warn(`WARNING ${path}: ${lines} lines (soft limit ${SOFT_LIMIT})`);
}

for (const { path, lines } of hardFailures) {
    console.error(`ERROR ${path}: ${lines} lines (hard limit ${HARD_LIMIT})`);
}

for (const { path, lines, exception } of exceptions) {
    console.warn(`EXCEPTION ${path}: ${lines} lines — ${exception}`);
}

console.log(
    `Checked ${results.length} handwritten source files: ${softWarnings.length} soft warnings, ${hardFailures.length} hard failures, ${exceptions.length} approved exceptions.`,
);

if (hardFailures.length > 0) {
    process.exitCode = 1;
}
