#!/usr/bin/env node

// Structural safety net for the dependency directions documented in docs/architecture/codebase-map.md.

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const violations = [];

const trackedFiles = execFileSync(
    'git',
    [
        'ls-files',
        '--cached',
        '--others',
        '--exclude-standard',
        '--',
        '*.php',
        '*.ts',
        '*.tsx',
    ],
    { cwd: root, encoding: 'utf8' },
)
    .trim()
    .split('\n')
    .filter(Boolean)
    .map((file) => file.replaceAll('\\', '/'));

const existingFiles = trackedFiles.filter((file) =>
    existsSync(path.join(root, file)),
);

function addViolation(file, message) {
    violations.push(`${file}: ${message}`);
}

function checkPhp(file) {
    const source = readFileSync(path.join(root, file), 'utf8');

    const directLoggingAllowed = [
        'app/Foundation/Diagnostics/OperationalLogger.php',
        'app/Http/Middleware/AttachCorrelationId.php',
    ].includes(file);

    if (
        file.startsWith('app/') &&
        !directLoggingAllowed &&
        (source.includes('Illuminate\\Support\\Facades\\Log') ||
            /\blogger\s*\(/.test(source))
    ) {
        addViolation(
            file,
            'application logs must use the allowlisted OperationalLogger boundary.',
        );
    }

    if (
        !file.startsWith('app/Modules/') &&
        !file.startsWith('app/Foundation/') &&
        !file.startsWith('app/Integrations/')
    ) {
        return;
    }

    const sourceModule = file.match(/^app\/Modules\/([^/]+)\//)?.[1];
    const isFoundation = file.startsWith('app/Foundation/');
    const isIntegration = file.startsWith('app/Integrations/');
    const references = source.matchAll(
        /App\\(Modules|Foundation|Integrations)\\([A-Za-z0-9_]+)(?:\\([A-Za-z0-9_]+))?/g,
    );

    for (const match of references) {
        const [, area, targetName, targetBoundary] = match;

        if (isFoundation && (area === 'Modules' || area === 'Integrations')) {
            addViolation(
                file,
                `Foundation cannot depend on App\\${area}\\${targetName}.`,
            );
        }

        if (sourceModule && area === 'Integrations') {
            addViolation(
                file,
                `module ${sourceModule} must use its own contract instead of concrete integration ${targetName}.`,
            );
        }

        if (
            sourceModule &&
            area === 'Modules' &&
            targetName !== sourceModule &&
            !['Actions', 'Contracts', 'Data', 'Models', 'Queries'].includes(
                targetBoundary,
            )
        ) {
            addViolation(
                file,
                `cross-module access to ${targetName} must use Actions, Contracts, Data, Models, or Queries.`,
            );
        }

        if (
            isIntegration &&
            area === 'Modules' &&
            !['Contracts', 'Data'].includes(targetBoundary)
        ) {
            addViolation(
                file,
                `integrations may consume only ${targetName} Contracts or Data.`,
            );
        }
    }
}

function resolveFrontendImport(file, specifier) {
    if (specifier.startsWith('@/')) {
        return `resources/js/${specifier.slice(2)}`;
    }

    if (!specifier.startsWith('.')) {
        return null;
    }

    return path
        .relative(root, path.resolve(root, path.dirname(file), specifier))
        .replaceAll('\\', '/');
}

function frontendLayer(file) {
    if (file.startsWith('resources/js/pages/')) {
        return 'page';
    }

    if (file.startsWith('resources/js/components/ui/')) {
        return 'ui';
    }

    if (file.startsWith('resources/js/components/app/')) {
        return 'app';
    }

    if (file.startsWith('resources/js/components/domain/')) {
        return 'domain';
    }

    if (file.startsWith('resources/js/features/')) {
        return 'feature';
    }

    if (
        file.startsWith('resources/js/hooks/') ||
        file.startsWith('resources/js/lib/') ||
        file.startsWith('resources/js/types/')
    ) {
        return 'shared';
    }

    return null;
}

function checkFrontend(file) {
    const layer = frontendLayer(file);

    if (!layer) {
        return;
    }

    const source = readFileSync(path.join(root, file), 'utf8');
    const importMatches = [
        ...source.matchAll(
            /\b(?:import|export)\s+(?:type\s+)?(?:[^'";]*?\s+from\s+)?['"]([^'"]+)['"]/g,
        ),
        ...source.matchAll(/\bimport\(\s*['"]([^'"]+)['"]\s*\)/g),
    ];

    for (const match of importMatches) {
        const target = resolveFrontendImport(file, match[1]);

        if (!target) {
            continue;
        }

        const targetLayer = frontendLayer(target);
        const targetFeature = target.match(
            /^resources\/js\/features\/([^/]+)\//,
        )?.[1];
        const sourceFeature = file.match(
            /^resources\/js\/features\/([^/]+)\//,
        )?.[1];

        const forbidden = {
            page: ['ui'],
            ui: ['app', 'domain', 'feature'],
            app: ['domain', 'feature'],
            domain: ['feature'],
            shared: ['ui', 'app', 'domain', 'feature'],
        };

        if (forbidden[layer]?.includes(targetLayer)) {
            addViolation(
                file,
                `${layer} code cannot import the higher ${targetLayer} layer (${match[1]}).`,
            );
        }

        if (
            layer === 'feature' &&
            targetLayer === 'feature' &&
            sourceFeature !== targetFeature
        ) {
            addViolation(
                file,
                `feature ${sourceFeature} cannot import feature ${targetFeature}'s internal code.`,
            );
        }
    }
}

for (const file of existingFiles) {
    if (file.endsWith('.php')) {
        checkPhp(file);
    }

    if (/\.(?:ts|tsx)$/.test(file)) {
        checkFrontend(file);
    }
}

if (violations.length > 0) {
    console.error('Module boundary violations:\n');

    for (const violation of violations) {
        console.error(`- ${violation}`);
    }

    process.exit(1);
}

const checkedPhp = existingFiles.filter(
    (file) =>
        file.endsWith('.php') &&
        /^(?:app\/(?:Modules|Foundation|Integrations))\//.test(file),
).length;
const checkedFrontend = existingFiles.filter(
    (file) => /\.(?:ts|tsx)$/.test(file) && frontendLayer(file),
).length;

console.log(
    `Module boundaries passed (${checkedPhp} modular PHP files, ${checkedFrontend} layered frontend files).`,
);
