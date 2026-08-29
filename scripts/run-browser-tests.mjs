#!/usr/bin/env node

import { spawn } from 'node:child_process';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const DEFAULT_TIMEOUT_MS = 10 * 60 * 1_000;
const DEFAULT_HEARTBEAT_MS = 15_000;
const DEFAULT_KILL_GRACE_MS = 5_000;

export async function superviseBrowserTests({
    executable,
    args,
    timeoutMs = DEFAULT_TIMEOUT_MS,
    heartbeatMs = DEFAULT_HEARTBEAT_MS,
    killGraceMs = DEFAULT_KILL_GRACE_MS,
    announce = (message) => console.error(message),
}) {
    const startedAt = Date.now();
    const child = spawn(executable, args, {
        detached: process.platform !== 'win32',
        env: { ...process.env, CI: 'true' },
        stdio: ['ignore', 'inherit', 'inherit'],
    });
    let timedOut = false;
    let interrupted = false;
    let spawnFailed = false;
    let forceKill;

    announce(
        `[browser-tests] started (hard timeout ${Math.ceil(timeoutMs / 1_000)}s)`,
    );

    const heartbeat = setInterval(() => {
        const elapsed = Math.floor((Date.now() - startedAt) / 1_000);
        announce(`[browser-tests] still running (${elapsed}s elapsed)`);
    }, heartbeatMs);
    const terminate = (signal) => terminateProcessGroup(child, signal);
    const timeout = setTimeout(() => {
        timedOut = true;
        announce(
            '[browser-tests] hard timeout reached; terminating the runner',
        );
        terminate('SIGTERM');
        forceKill = setTimeout(() => terminate('SIGKILL'), killGraceMs);
        forceKill.unref();
    }, timeoutMs);
    const interrupt = () => {
        interrupted = true;
        announce(
            '[browser-tests] interrupted; terminating the complete runner group',
        );
        terminate('SIGTERM');
        forceKill ??= setTimeout(() => terminate('SIGKILL'), killGraceMs);
        forceKill.unref();
    };

    process.once('SIGINT', interrupt);
    process.once('SIGTERM', interrupt);

    return await new Promise((resolve) => {
        child.once('error', () => {
            spawnFailed = true;
        });
        child.once('close', (code) => {
            clearInterval(heartbeat);
            clearTimeout(timeout);
            clearTimeout(forceKill);
            process.off('SIGINT', interrupt);
            process.off('SIGTERM', interrupt);
            terminate('SIGTERM');

            setTimeout(
                () => {
                    terminate('SIGKILL');

                    if (timedOut) {
                        resolve(124);

                        return;
                    }

                    resolve(interrupted ? 130 : spawnFailed ? 1 : (code ?? 1));
                },
                Math.min(killGraceMs, 250),
            );
        });
    });
}

export function browserArguments(cliArguments) {
    const hasBrowserPath = cliArguments.some((argument) =>
        argument.startsWith('tests/Browser'),
    );

    return hasBrowserPath ? cliArguments : ['tests/Browser', ...cliArguments];
}

function terminateProcessGroup(child, signal) {
    if (child.pid === undefined || child.killed) {
        return;
    }

    try {
        if (process.platform === 'win32') {
            child.kill(signal);
        } else {
            process.kill(-child.pid, signal);
        }
    } catch (error) {
        if (error?.code !== 'ESRCH') {
            throw error;
        }
    }
}

function positiveMilliseconds(environmentKey, fallback) {
    const seconds = Number.parseInt(process.env[environmentKey] ?? '', 10);

    return Number.isInteger(seconds) && seconds > 0
        ? seconds * 1_000
        : fallback;
}

async function main() {
    const cliArguments = process.argv.slice(2);

    if (
        cliArguments.includes('--debug') &&
        process.env.BROWSER_TEST_INTERACTIVE !== 'true'
    ) {
        console.error(
            '[browser-tests] --debug is interactive; set BROWSER_TEST_INTERACTIVE=true and run it directly in a headed terminal.',
        );
        process.exitCode = 2;

        return;
    }

    process.exitCode = await superviseBrowserTests({
        executable: 'vendor/bin/pest',
        args: browserArguments(cliArguments),
        timeoutMs: positiveMilliseconds(
            'BROWSER_TEST_TIMEOUT_SECONDS',
            DEFAULT_TIMEOUT_MS,
        ),
        heartbeatMs: positiveMilliseconds(
            'BROWSER_TEST_HEARTBEAT_SECONDS',
            DEFAULT_HEARTBEAT_MS,
        ),
    });
}

if (
    process.argv[1] &&
    import.meta.url === pathToFileURL(process.argv[1]).href
) {
    await main();
}
