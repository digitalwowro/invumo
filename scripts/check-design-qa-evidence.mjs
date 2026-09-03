import { createHash } from 'node:crypto';
import { readdir, readFile, stat } from 'node:fs/promises';
import { dirname, extname, isAbsolute, join, relative } from 'node:path';

const projectRoot = process.cwd();
const canonicalLogRelative = 'docs/development/design-qa.md';
const canonicalLogPath = join(projectRoot, canonicalLogRelative);
const docsIndexPath = join(projectRoot, 'docs/README.md');
const evidenceRootRelative = 'docs/development/design-qa-evidence';
const evidenceRoot = join(projectRoot, evidenceRootRelative);
const registryPath = join(projectRoot, 'tests/Browser/design-qa-reviews.json');
const commitPattern = /^[0-9a-f]{7,40}$/;
const datePattern = /^\d{4}-\d{2}-\d{2}$/;
const hashPattern = /^[0-9a-f]{64}$/;
const pngSignature = Buffer.from('89504e470d0a1a0a', 'hex');

function hasText(value, minimum = 1) {
    return typeof value === 'string' && value.trim().length >= minimum;
}

function hasTextList(value) {
    return (
        Array.isArray(value) &&
        value.length > 0 &&
        value.every((item) => hasText(item))
    );
}

async function rootLogExists() {
    try {
        await stat(join(projectRoot, 'design-qa.md'));

        return true;
    } catch (error) {
        if (error?.code === 'ENOENT') {
            return false;
        }

        throw error;
    }
}

const errors = [];
const canonicalLog = await readFile(canonicalLogPath, 'utf8');
const docsIndex = await readFile(docsIndexPath, 'utf8');
const registrySource = await readFile(registryPath, 'utf8');
const registry = JSON.parse(registrySource);
const reviews = Array.isArray(registry.reviews) ? registry.reviews : [];
const registeredArtifacts = new Set();
const reviewIds = new Set();

if (registry.version !== 1) {
    errors.push('The design-QA evidence registry must use version 1.');
}

if (await rootLogExists()) {
    errors.push(
        'Design QA must have one canonical log; remove the repository-root design-qa.md.',
    );
}

if (!docsIndex.includes('(development/design-qa.md)')) {
    errors.push('docs/README.md must link the canonical design-QA log.');
}

for (const forbidden of ['/home/', '.codex/', 'tests/Browser/Screenshots/']) {
    if (
        canonicalLog.includes(forbidden) ||
        registrySource.includes(forbidden)
    ) {
        errors.push(
            `Design-QA evidence contains a machine-local path: ${forbidden}`,
        );
    }
}

for (const review of reviews) {
    const label = hasText(review.id) ? review.id : 'unnamed review';

    if (!hasText(review.id)) {
        errors.push('Every design-QA review requires an id.');
    } else if (reviewIds.has(review.id)) {
        errors.push(`Duplicate design-QA review id: ${review.id}`);
    }

    reviewIds.add(review.id);

    if (!hasText(review.documentAnchor)) {
        errors.push(`${label} requires a documentAnchor.`);
    } else if (
        !canonicalLog.includes(`<a id="${review.documentAnchor}"></a>`)
    ) {
        errors.push(`${label} has no matching canonical-log anchor.`);
    }

    if (!datePattern.test(review.reviewedOn ?? '')) {
        errors.push(`${label} has an invalid reviewedOn date.`);
    }

    if (!commitPattern.test(review.implementationCommit ?? '')) {
        errors.push(`${label} has an invalid implementationCommit.`);
    }

    if (
        !Array.isArray(review.causeCommits) ||
        review.causeCommits.length === 0 ||
        !review.causeCommits.every((commit) => commitPattern.test(commit))
    ) {
        errors.push(`${label} requires valid causeCommits.`);
    }

    if (!hasTextList(review.screens)) {
        errors.push(`${label} requires reviewed screens.`);
    }

    if (!hasTextList(review.comparisonSummary)) {
        errors.push(`${label} requires a comparison summary.`);
    }

    if (!hasTextList(review.tests)) {
        errors.push(`${label} requires focused verification evidence.`);
    }

    if (review.renderingInspected !== true) {
        errors.push(`${label} must confirm rendering inspection.`);
    }

    if (review.result !== 'passed') {
        errors.push(`${label} must record the reviewed result.`);
    }

    for (const kind of ['sourceArtifacts', 'implementationArtifacts']) {
        const artifacts = Array.isArray(review[kind]) ? review[kind] : [];

        if (artifacts.length === 0) {
            errors.push(`${label} requires ${kind}.`);
        }

        for (const artifact of artifacts) {
            const path = artifact?.path;

            if (
                !hasText(path) ||
                isAbsolute(path) ||
                !path.startsWith(`${evidenceRootRelative}/`) ||
                path.includes('..')
            ) {
                errors.push(
                    `${label} has an invalid evidence path: ${path ?? ''}`,
                );
                continue;
            }

            if (!['.html', '.png'].includes(extname(path))) {
                errors.push(
                    `${label} has an unsupported evidence type: ${path}`,
                );
            }

            if (registeredArtifacts.has(path)) {
                errors.push(`Duplicate design-QA evidence artifact: ${path}`);
            }

            registeredArtifacts.add(path);

            if (!hashPattern.test(artifact.sha256 ?? '')) {
                errors.push(`${path} has an invalid SHA-256 value.`);
                continue;
            }

            let contents;

            try {
                contents = await readFile(join(projectRoot, path));
            } catch {
                errors.push(`Missing design-QA evidence artifact: ${path}`);
                continue;
            }

            const actualHash = createHash('sha256')
                .update(contents)
                .digest('hex');

            if (actualHash !== artifact.sha256) {
                errors.push(
                    `Design-QA evidence hash is stale for ${path}: expected ${actualHash}.`,
                );
            }

            if (
                extname(path) === '.png' &&
                !contents.subarray(0, 8).equals(pngSignature)
            ) {
                errors.push(`Design-QA PNG has an invalid signature: ${path}`);
            }

            const relativeLink = relative(
                dirname(canonicalLogPath),
                join(projectRoot, path),
            );

            if (!canonicalLog.includes(`(${relativeLink})`)) {
                errors.push(`Canonical design QA does not link ${path}.`);
            }
        }
    }
}

const evidenceFiles = (await readdir(evidenceRoot, { withFileTypes: true }))
    .filter((entry) => entry.isFile())
    .map((entry) => `${evidenceRootRelative}/${entry.name}`)
    .sort();

for (const path of evidenceFiles) {
    if (!registeredArtifacts.has(path)) {
        errors.push(`Unregistered design-QA evidence artifact: ${path}`);
    }
}

for (const path of registeredArtifacts) {
    if (!evidenceFiles.includes(path)) {
        errors.push(
            `Registered design-QA evidence is outside the artifact inventory: ${path}`,
        );
    }
}

if (errors.length > 0) {
    console.error('Design-QA evidence violations found:\n');
    errors.forEach((error) => console.error(`- ${error}`));
    process.exit(1);
}

console.log(
    `Design-QA evidence passed for ${reviews.length} reviews and ${evidenceFiles.length} artifacts.`,
);
