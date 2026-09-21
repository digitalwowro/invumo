import assert from 'node:assert/strict';
import { mkdir, mkdtemp, rm, stat, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { makePublicBuildReadable } from './set-public-build-permissions.mjs';

const permissions = async (path) => (await stat(path)).mode & 0o777;

test('makes generated public build files web-readable', async () => {
    const temporaryPath = await mkdtemp(
        join(tmpdir(), 'invumo-public-build-permissions-'),
    );
    const buildPath = join(temporaryPath, 'build');
    const assetsPath = join(buildPath, 'assets');

    try {
        await mkdir(assetsPath, { recursive: true, mode: 0o700 });
        await writeFile(join(buildPath, 'manifest.json'), '{}', {
            mode: 0o600,
        });
        await writeFile(join(assetsPath, 'app.js'), 'export {};', {
            mode: 0o600,
        });

        await makePublicBuildReadable(buildPath);

        assert.equal(await permissions(buildPath), 0o755);
        assert.equal(await permissions(assetsPath), 0o755);
        assert.equal(
            await permissions(join(buildPath, 'manifest.json')),
            0o644,
        );
        assert.equal(await permissions(join(assetsPath, 'app.js')), 0o644);
    } finally {
        await rm(temporaryPath, { recursive: true, force: true });
    }
});
