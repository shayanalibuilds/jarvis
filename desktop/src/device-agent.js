'use strict';

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

/**
 * Polls the Jarvis server for tool calls and executes them on this computer.
 *
 * The agent never decides what to do on its own: it only runs the capabilities
 * the server hands it, and it stops as soon as the user takes over.
 */
class DeviceAgent {
    constructor(options = {}) {
        this.baseUrl = String(options.baseUrl ?? '').replace(/\/$/, '');
        this.token = options.token;
        this.executor = options.executor;
        this.fetchImpl = options.fetchImpl ?? globalThis.fetch;
        this.pollIntervalMs = options.pollIntervalMs ?? 1000;
        this.heartbeatIntervalMs = options.heartbeatIntervalMs ?? 15000;
        this.pausedPollIntervalMs = options.pausedPollIntervalMs ?? 5000;
        this.onEvent = options.onEvent ?? (() => {});
        this.logger = options.logger ?? (() => {});

        this.running = false;
        this.session = null;
        this.heartbeatTimer = null;
    }

    /**
     * Start heartbeating and polling.
     */
    start() {
        if (this.running) {
            return;
        }

        this.running = true;

        this.heartbeatTimer = setInterval(() => {
            this.heartbeat().catch((error) => this.logger(`jarvis: heartbeat failed: ${error.message}`));
        }, this.heartbeatIntervalMs);

        this.loop().catch((error) => this.logger(`jarvis: agent loop stopped: ${error.message}`));
        this.onEvent({ type: 'started' });
    }

    /**
     * Stop the agent.
     */
    stop() {
        this.running = false;

        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }

        this.onEvent({ type: 'stopped' });
    }

    /**
     * Report that this computer is online and pick up the current session state.
     */
    async heartbeat() {
        const payload = await this.request('/api/jarvis/devices/heartbeat', { method: 'POST', body: {} });

        this.session = payload.session ?? null;
        this.onEvent({ type: 'heartbeat', session: this.session });

        return payload;
    }

    /**
     * Claim and run a single tool call.
     */
    async pollOnce() {
        if (this.session && this.session.allows_execution === false) {
            return null;
        }

        const payload = await this.request('/api/jarvis/devices/next', { method: 'GET' });
        const call = payload?.call ?? null;

        if (!call) {
            return null;
        }

        await this.runCall(call);

        return call;
    }

    /**
     * Execute a claimed tool call locally and report the result.
     */
    async runCall(call) {
        this.onEvent({ type: 'call.started', call });

        let result = null;
        let error = null;

        try {
            result = await this.executor.execute(call.capability, call.arguments ?? {});
        } catch (exception) {
            error = exception.message;
        }

        if (result !== null) {
            const parsed = this.parse(result);

            if (parsed && parsed.successful === false) {
                error = parsed.error ?? 'The capability failed.';
                result = null;
            }
        }

        await this.request(`/api/jarvis/devices/calls/${call.id}`, {
            method: 'POST',
            body: { result, error },
        });

        this.onEvent({ type: 'call.finished', call, result, error });

        return { result, error };
    }

    /**
     * Ask the server to take over, pause, resume, or stop the active session.
     */
    async control(action) {
        const payload = await this.request('/api/jarvis/control', {
            method: 'POST',
            body: { action },
        });

        this.session = payload.session ?? null;
        this.onEvent({ type: 'control', action, session: this.session });

        return payload;
    }

    /**
     * Poll for work until the agent is stopped.
     */
    async loop() {
        while (this.running) {
            try {
                if (!this.session) {
                    await this.heartbeat();
                }

                await this.pollOnce();
            } catch (error) {
                this.logger(`jarvis: poll failed: ${error.message}`);
                this.onEvent({ type: 'error', error: error.message });
            }

            await sleep(this.session && this.session.allows_execution === false
                ? this.pausedPollIntervalMs
                : this.pollIntervalMs);
        }
    }

    /**
     * Perform an authenticated request against the Jarvis server.
     */
    async request(path, options = {}) {
        const response = await this.fetchImpl(`${this.baseUrl}${path}`, {
            method: options.method ?? 'GET',
            headers: {
                'X-Jarvis-Device-Token': this.token,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: options.body === undefined ? undefined : JSON.stringify(options.body),
        });

        if (!response.ok) {
            throw new Error(`${options.method ?? 'GET'} ${path} failed with status ${response.status}`);
        }

        const text = await response.text();

        return text === '' ? {} : JSON.parse(text);
    }

    /**
     * Parse a capability result into an object when possible.
     */
    parse(result) {
        if (typeof result !== 'string') {
            return null;
        }

        try {
            return JSON.parse(result);
        } catch {
            return null;
        }
    }
}

module.exports = { DeviceAgent };
