#!/usr/bin/env node

import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

import { utils } from 'playwright-core/lib/coreBundle';

const [actualPath, snapshotPath, artifactDirectory, artifactName] =
    process.argv.slice(2);

if (!actualPath || !snapshotPath || !artifactDirectory || !artifactName) {
    console.error(
        'Usage: compare-visual-snapshot <actual> <snapshot> <artifact-directory> <artifact-name>',
    );
    process.exit(2);
}

try {
    const actual = readFileSync(actualPath);
    const encodedExpected = readFileSync(snapshotPath, 'utf8').trim();
    const expected = Buffer.from(encodedExpected, 'base64');
    const compare = utils.getComparator('image/png');
    const result = compare(actual, expected, {
        comparator: 'pixelmatch',
        maxDiffPixelRatio: 0.01,
        maxDiffPixels: 300,
        threshold: 0.3,
    });

    if (result === null) {
        process.exit(0);
    }

    mkdirSync(artifactDirectory, { recursive: true });

    const artifactBase = path.join(artifactDirectory, artifactName);

    writeFileSync(`${artifactBase}-expected.png`, expected);
    copyFileSync(actualPath, `${artifactBase}-actual.png`);

    if (result.diff) {
        writeFileSync(`${artifactBase}-diff.png`, result.diff);
    }

    console.error(result.errorMessage);
    process.exit(1);
} catch (error) {
    console.error(
        error instanceof Error ? error.message : 'Visual comparison failed.',
    );
    process.exit(2);
}
