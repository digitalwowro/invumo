#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const PNG_SIGNATURE = Buffer.from([
    0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a,
]);

export async function adoptVisualBaseline(actualPath, snapshotPath) {
    const png = await readFile(actualPath);

    if (!png.subarray(0, PNG_SIGNATURE.length).equals(PNG_SIGNATURE)) {
        throw new Error('The reviewed actual artifact is not a PNG file.');
    }

    await writeFile(snapshotPath, png.toString('base64'));

    return createHash('sha256').update(png).digest('hex');
}

async function main() {
    const [actualPath, snapshotPath] = process.argv.slice(2);

    if (!actualPath || !snapshotPath) {
        console.error(
            'Usage: node scripts/adopt-visual-baseline.mjs <reviewed-actual.png> <snapshot.snap>',
        );
        process.exitCode = 2;

        return;
    }

    const hash = await adoptVisualBaseline(actualPath, snapshotPath);

    console.log(`Adopted reviewed PNG ${hash} into ${snapshotPath}`);
}

if (
    process.argv[1] &&
    import.meta.url === pathToFileURL(process.argv[1]).href
) {
    await main();
}
