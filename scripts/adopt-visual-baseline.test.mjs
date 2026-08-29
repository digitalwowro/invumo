import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { adoptVisualBaseline } from './adopt-visual-baseline.mjs';

test('adopts only reviewed PNG bytes as canonical base64', async () => {
    const directory = mkdtempSync(join(tmpdir(), 'invumo-visual-baseline-'));
    const actual = join(directory, 'actual.png');
    const snapshot = join(directory, 'baseline.snap');
    const png = Buffer.from([
        0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0x01,
    ]);
    writeFileSync(actual, png);

    const hash = await adoptVisualBaseline(actual, snapshot);

    assert.equal(
        hash,
        '275f1bcbbb585c71e3b2184304eccfa0e37de92022ca3b6f4e9c10df32318d85',
    );
    assert.equal(readFileSync(snapshot, 'utf8'), png.toString('base64'));
    rmSync(directory, { recursive: true, force: true });
});

test('rejects a non-PNG artifact', async () => {
    const directory = mkdtempSync(join(tmpdir(), 'invumo-visual-baseline-'));
    const actual = join(directory, 'actual.txt');
    const snapshot = join(directory, 'baseline.snap');
    writeFileSync(actual, 'not a png');

    await assert.rejects(
        adoptVisualBaseline(actual, snapshot),
        /not a PNG file/,
    );
    rmSync(directory, { recursive: true, force: true });
});
