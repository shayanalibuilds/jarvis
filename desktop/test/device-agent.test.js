'use strict';

const assert = require('node:assert/strict');
const test = require('node:test');

const { DeviceAgent } = require('../src/device-agent');

function fakeFetch(routes) {
    const calls = [];

    const fetchImpl = async (url, options = {}) => {
        const path = url.replace('https://jarvis.test', '');
        calls.push({ path, method: options.method ?? 'GET', body: options.body ? JSON.parse(options.body) : undefined, headers: options.headers });

        const handler = routes[path];

        if (!handler) {
            return { ok: false, status: 404, text: async () => '{"message":"not found"}' };
        }

        const payload = handler(options);

        return {
            ok: true,
            status: 200,
            text: async () => JSON.stringify(payload ?? {}),
        };
    };

    return { fetchImpl, calls };
}

function createAgent(routes, executor, events = []) {
    const { fetchImpl, calls } = fakeFetch(routes);

    const agent = new DeviceAgent({
        baseUrl: 'https://jarvis.test',
        token: 'device-token',
        executor: executor ?? { execute: async () => JSON.stringify({ successful: true }) },
        fetchImpl,
        onEvent: (event) => events.push(event),
    });

    return { agent, calls, events };
}

test('heartbeat stores the session and sends the device token', async () => {
    const { agent, calls } = createAgent({
        '/api/jarvis/devices/heartbeat': () => ({
            session: { id: 'session-1', status: 'running', allows_execution: true, status_label: 'Running' },
            shortcuts: { take_over: { accelerator: 'CommandOrControl+Shift+J' } },
        }),
    });

    await agent.heartbeat();

    assert.equal(agent.session.id, 'session-1');
    assert.equal(calls[0].headers['X-Jarvis-Device-Token'], 'device-token');
});

test('a claimed call is executed locally and reported back', async () => {
    const executed = [];
    const { agent, calls } = createAgent(
        {
            '/api/jarvis/devices/heartbeat': () => ({ session: { allows_execution: true } }),
            '/api/jarvis/devices/next': () => ({ call: { id: 'call-1', capability: 'run_shell_command', arguments: { command: 'echo hi' } } }),
            '/api/jarvis/devices/calls/call-1': () => ({ ok: true }),
        },
        {
            execute: async (capability, args) => {
                executed.push({ capability, args });

                return JSON.stringify({ successful: true, capability, output: 'hi' });
            },
        },
    );

    await agent.heartbeat();
    await agent.pollOnce();

    assert.deepEqual(executed, [{ capability: 'run_shell_command', args: { command: 'echo hi' } }]);

    const report = calls.find((call) => call.path === '/api/jarvis/devices/calls/call-1');

    assert.equal(report.method, 'POST');
    assert.equal(report.body.error, null);
    assert.match(report.body.result, /hi/);
});

test('a failing capability is reported as an error', async () => {
    const { agent, calls } = createAgent(
        {
            '/api/jarvis/devices/next': () => ({ call: { id: 'call-2', capability: 'run_shell_command', arguments: {} } }),
            '/api/jarvis/devices/calls/call-2': () => ({ ok: true }),
        },
        { execute: async () => JSON.stringify({ successful: false, error: 'the command is blocked' }) },
    );

    await agent.pollOnce();

    const report = calls.find((call) => call.path === '/api/jarvis/devices/calls/call-2');

    assert.equal(report.body.result, null);
    assert.equal(report.body.error, 'the command is blocked');
});

test('nothing runs while the user has taken over', async () => {
    let polled = false;
    const { agent } = createAgent(
        {
            '/api/jarvis/devices/next': () => {
                polled = true;

                return { call: null };
            },
        },
    );

    agent.session = { status: 'taken_over', allows_execution: false };

    const result = await agent.pollOnce();

    assert.equal(result, null);
    assert.equal(polled, false);
});

test('the desktop shortcut control calls the control endpoint', async () => {
    const { agent, calls } = createAgent({
        '/api/jarvis/control': () => ({ session: { id: 'session-1', status: 'taken_over', allows_execution: false, status_label: 'You have taken over' }, message: 'Jarvis is now You have taken over.' }),
    });

    const payload = await agent.control('take_over');

    assert.equal(payload.session.status, 'taken_over');
    assert.equal(calls[0].body.action, 'take_over');
    assert.equal(agent.session.allows_execution, false);
});

test('the agent can be started and stopped', async () => {
    const events = [];
    const { agent } = createAgent({
        '/api/jarvis/devices/heartbeat': () => ({ session: { allows_execution: true } }),
        '/api/jarvis/devices/next': () => ({ call: null }),
    }, undefined, events);

    agent.pollIntervalMs = 5;

    agent.start();

    assert.equal(agent.running, true);

    await new Promise((resolve) => setTimeout(resolve, 30));
    agent.stop();

    assert.equal(agent.running, false);
    assert.ok(events.some((event) => event.type === 'started'));
    assert.ok(events.some((event) => event.type === 'stopped'));
});
