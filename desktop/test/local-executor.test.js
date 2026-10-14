'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');

const { createLocalExecutor } = require('../src/local-executor');

function temporaryDirectory() {
    return fs.mkdtempSync(path.join(os.tmpdir(), 'jarvis-executor-'));
}

test('runs shell commands through the platform shell', async () => {
    const calls = [];
    const executor = createLocalExecutor({
        platform: 'linux',
        runCommand: async (command, args, options) => {
            calls.push({ command, args, options });

            return { code: 0, stdout: 'jarvis-online\n', stderr: '' };
        },
    });

    const result = JSON.parse(await executor.execute('run_shell_command', { command: 'echo jarvis-online' }));

    assert.equal(result.successful, true);
    assert.equal(result.output, 'jarvis-online');
    assert.equal(result.exit_code, 0);
    assert.equal(result.target, 'device');
    assert.equal(calls[0].command, '/bin/sh');
    assert.deepEqual(calls[0].args, ['-c', 'echo jarvis-online']);
});

test('refuses permanently blocked commands', async () => {
    const executor = createLocalExecutor({
        platform: 'linux',
        runCommand: async () => {
            throw new Error('should not run');
        },
    });

    const result = JSON.parse(await executor.execute('run_shell_command', { command: 'sudo rm -rf /' }));

    assert.equal(result.successful, false);
    assert.match(result.error, /permanently blocked/);
});

test('reports a timeout without throwing', async () => {
    const executor = createLocalExecutor({
        platform: 'linux',
        runCommand: async () => ({ code: null, stdout: '', stderr: '', timedOut: true }),
    });

    const result = JSON.parse(await executor.execute('run_shell_command', { command: 'sleep 300' }));

    assert.equal(result.timed_out, true);
    assert.equal(result.successful, true);
});

test('reads, writes, and lists files', async () => {
    const directory = temporaryDirectory();
    const executor = createLocalExecutor({ platform: 'linux' });

    const written = JSON.parse(await executor.execute('write_file', {
        path: path.join(directory, 'notes.txt'),
        contents: 'hello from the desktop',
    }));

    assert.equal(written.successful, true);
    assert.equal(written.bytes, 22);

    const read = JSON.parse(await executor.execute('read_file', { path: path.join(directory, 'notes.txt') }));

    assert.equal(read.contents, 'hello from the desktop');

    const listed = JSON.parse(await executor.execute('list_directory', { path: directory }));

    assert.equal(listed.entries.length, 1);
    assert.equal(listed.entries[0].name, 'notes.txt');
    assert.equal(listed.entries[0].type, 'file');

    fs.rmSync(directory, { recursive: true, force: true });
});

test('refuses to write to protected paths', async () => {
    const executor = createLocalExecutor({ platform: 'linux' });

    const result = JSON.parse(await executor.execute('write_file', {
        path: '/etc/jarvis.conf',
        contents: 'nope',
    }));

    assert.equal(result.successful, false);
    assert.match(result.error, /blocked/);
});

test('rejects unknown capabilities', async () => {
    const executor = createLocalExecutor({ platform: 'linux' });

    const result = JSON.parse(await executor.execute('launch_missiles', {}));

    assert.equal(result.successful, false);
    assert.match(result.error, /Unknown capability/);
});
