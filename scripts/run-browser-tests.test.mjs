import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';
import { test } from 'node:test';
import {
    browserArguments,
    superviseBrowserTests,
} from './run-browser-tests.mjs';

test('adds the Browser suite unless a focused Browser path is supplied', () => {
    assert.deepEqual(browserArguments(['--shard=1/2']), [
        'tests/Browser',
        '--shard=1/2',
        '--enforce-time-limit',
        '--default-time-limit=120',
    ]);
    assert.deepEqual(browserArguments(['tests/Browser/ExampleTest.php']), [
        'tests/Browser/ExampleTest.php',
        '--enforce-time-limit',
        '--default-time-limit=120',
    ]);
    assert.deepEqual(
        browserArguments(
            [
                'tests/Browser/ExampleTest.php',
                '--enforce-time-limit',
                '--default-time-limit=45',
            ],
            90,
        ),
        [
            'tests/Browser/ExampleTest.php',
            '--enforce-time-limit',
            '--default-time-limit=45',
        ],
    );
    assert.deepEqual(browserArguments([], 90), [
        'tests/Browser',
        '--enforce-time-limit',
        '--default-time-limit=90',
    ]);
});

test('closes stdin and reports progress before terminating a silent runner', async () => {
    const messages = [];
    const directory = mkdtempSync(join(tmpdir(), 'invumo-browser-runner-'));
    const pidFile = join(directory, 'descendant.pid');
    const childProgram = `
        const { spawn } = require('node:child_process');
        const { writeFileSync } = require('node:fs');
        const descendant = spawn(process.execPath, ['-e', 'setInterval(() => {}, 1000)']);
        writeFileSync(${JSON.stringify(pidFile)}, String(descendant.pid));
        process.stdin.resume();
        setInterval(() => {}, 1000);
    `;
    const code = await superviseBrowserTests({
        executable: process.execPath,
        args: ['-e', childProgram],
        timeoutMs: 120,
        heartbeatMs: 30,
        killGraceMs: 20,
        announce: (message) => messages.push(message),
    });

    assert.equal(code, 124);
    assert.ok(messages.some((message) => message.includes('still running')));
    assert.ok(messages.some((message) => message.includes('hard timeout')));
    const descendantPid = Number.parseInt(readFileSync(pidFile, 'utf8'), 10);
    await waitUntilGone(descendantPid);
    assert.throws(() => process.kill(descendantPid, 0), { code: 'ESRCH' });
    rmSync(directory, { recursive: true, force: true });
});

test('cleans descendant servers after a successful runner exits', async () => {
    const directory = mkdtempSync(join(tmpdir(), 'invumo-browser-success-'));
    const pidFile = join(directory, 'descendant.pid');
    const childProgram = `
        const { spawn } = require('node:child_process');
        const { writeFileSync } = require('node:fs');
        const descendant = spawn(process.execPath, ['-e', 'setInterval(() => {}, 1000)'], {
            stdio: 'ignore',
        });
        writeFileSync(${JSON.stringify(pidFile)}, String(descendant.pid));
        descendant.unref();
    `;
    const code = await superviseBrowserTests({
        executable: process.execPath,
        args: ['-e', childProgram],
        timeoutMs: 1_000,
        heartbeatMs: 500,
        killGraceMs: 50,
        announce: () => {},
    });

    assert.equal(code, 0);
    const descendantPid = Number.parseInt(readFileSync(pidFile, 'utf8'), 10);
    await waitUntilGone(descendantPid);
    assert.throws(() => process.kill(descendantPid, 0), { code: 'ESRCH' });
    rmSync(directory, { recursive: true, force: true });
});

async function waitUntilGone(pid) {
    for (let attempt = 0; attempt < 20; attempt += 1) {
        try {
            process.kill(pid, 0);
        } catch (error) {
            if (error?.code === 'ESRCH') {
                return;
            }

            throw error;
        }

        await new Promise((resolve) => setTimeout(resolve, 25));
    }
}
