import { chmod, readdir } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export async function makePublicBuildReadable(
    buildPath = join(process.cwd(), 'public', 'build'),
) {
    await chmod(buildPath, 0o755);

    const entries = await readdir(buildPath, { withFileTypes: true });

    await Promise.all(
        entries.map(async (entry) => {
            const path = join(buildPath, entry.name);

            if (entry.isDirectory()) {
                await makePublicBuildReadable(path);

                return;
            }

            if (entry.isFile()) {
                await chmod(path, 0o644);
            }
        }),
    );
}

const modulePath = fileURLToPath(import.meta.url);

if (process.argv[1] && resolve(process.argv[1]) === modulePath) {
    await makePublicBuildReadable();
}
