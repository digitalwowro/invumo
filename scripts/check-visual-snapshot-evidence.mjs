import { createHash } from 'node:crypto';
import { readdir, readFile } from 'node:fs/promises';
import { join, relative } from 'node:path';

const projectRoot = process.cwd();
const snapshotRoot = join(projectRoot, 'tests/.pest/snapshots');
const registryPath = join(
    projectRoot,
    'tests/Browser/visual-baseline-reviews.json',
);
const commitPattern = /^[0-9a-f]{7,40}$/;
const datePattern = /^\d{4}-\d{2}-\d{2}$/;

async function snapshotsIn(directory) {
    const entries = await readdir(directory, { withFileTypes: true });
    const snapshots = [];

    for (const entry of entries) {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            snapshots.push(...(await snapshotsIn(path)));
        } else if (entry.name.endsWith('.snap')) {
            snapshots.push(relative(projectRoot, path));
        }
    }

    return snapshots;
}

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

const errors = [];
const registry = JSON.parse(await readFile(registryPath, 'utf8'));
const snapshots = (await snapshotsIn(snapshotRoot)).sort();
const reviews = Array.isArray(registry.baselines) ? registry.baselines : [];
const reviewsByPath = new Map();

if (registry.version !== 1) {
    errors.push('The visual baseline evidence registry must use version 1.');
}

for (const review of reviews) {
    if (!hasText(review.path)) {
        errors.push('Every visual baseline review requires a snapshot path.');
        continue;
    }

    if (reviewsByPath.has(review.path)) {
        errors.push(`Duplicate visual baseline review: ${review.path}`);
    }

    reviewsByPath.set(review.path, review);
}

for (const snapshot of snapshots) {
    const review = reviewsByPath.get(snapshot);

    if (!review) {
        errors.push(`Missing visual baseline review: ${snapshot}`);
        continue;
    }

    const encoded = (
        await readFile(join(projectRoot, snapshot), 'utf8')
    ).trim();
    const png = Buffer.from(encoded, 'base64');
    const pngHash = createHash('sha256').update(png).digest('hex');

    if (png.toString('base64') !== encoded) {
        errors.push(`Snapshot is not canonical base64 PNG data: ${snapshot}`);
    }

    if (review.pngSha256 !== pngHash) {
        errors.push(
            `Visual baseline evidence hash is stale for ${snapshot}: expected ${pngHash}.`,
        );
    }

    if (!datePattern.test(review.reviewedOn ?? '')) {
        errors.push(`Invalid reviewedOn date for ${snapshot}.`);
    }

    if (!commitPattern.test(review.causeCommit ?? '')) {
        errors.push(`Invalid causeCommit for ${snapshot}.`);
    }

    if (!commitPattern.test(review.baselineCommit ?? '')) {
        errors.push(`Invalid baselineCommit for ${snapshot}.`);
    }

    if (!hasTextList(review.screens)) {
        errors.push(`Protected screens are missing for ${snapshot}.`);
    }

    if (!hasTextList(review.intendedChanges)) {
        errors.push(`Intended visual changes are missing for ${snapshot}.`);
    }

    if (review.renderingInspected !== true) {
        errors.push(`Rendering inspection is not confirmed for ${snapshot}.`);
    }

    if (!hasText(review.inspectionEvidence, 20)) {
        errors.push(`Inspection evidence is incomplete for ${snapshot}.`);
    }
}

for (const path of reviewsByPath.keys()) {
    if (!snapshots.includes(path)) {
        errors.push(`Visual baseline review has no matching snapshot: ${path}`);
    }
}

if (errors.length > 0) {
    console.error('Visual baseline evidence violations found:\n');
    errors.forEach((error) => console.error(`- ${error}`));
    process.exit(1);
}

console.log(
    `Visual baseline evidence passed for ${snapshots.length} canonical snapshots.`,
);
