'use strict';

const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { execFile } = require('node:child_process');

const DEFAULT_BLOCKED_PATTERNS = [
    'rm -rf /',
    'mkfs',
    ':(){ :|:& };:',
    'dd if=/dev/zero',
    '> /dev/sda',
    'chmod -R 777 /',
];

const PROTECTED_PATH_PREFIXES = process.platform === 'win32'
    ? ['C:\\Windows', 'C:\\Program Files']
    : ['/etc/', '/bin/', '/sbin/', '/usr/', '/boot/', '/sys/', '/proc/', '/var/lib/'];

/**
 * Run an operating system command and resolve with its output.
 */
function defaultRunCommand(command, args, options = {}) {
    return new Promise((resolve, reject) => {
        const child = execFile(
            command,
            args,
            { timeout: options.timeout ?? 30_000, maxBuffer: 20 * 1024 * 1024, cwd: options.cwd },
            (error, stdout, stderr) => {
                if (error && error.killed) {
                    resolve({ code: null, stdout: stdout ?? '', stderr: `${stderr ?? ''}\nThe command timed out and was terminated.`, timedOut: true });

                    return;
                }

                if (error && typeof error.code === 'string') {
                    reject(new Error(`Unable to run ${command}: ${error.message}`));

                    return;
                }

                resolve({
                    code: error ? error.code ?? 1 : 0,
                    stdout: stdout ?? '',
                    stderr: stderr ?? '',
                    timedOut: false,
                });
            },
        );

        child.on('error', (error) => reject(error));
    });
}

/**
 * Create the local executor used by the desktop device agent.
 */
function createLocalExecutor(options = {}) {
    const platform = options.platform ?? process.platform;
    const runCommand = options.runCommand ?? defaultRunCommand;
    const fileSystem = options.fileSystem ?? fs;
    const blockedPatterns = options.blockedPatterns ?? DEFAULT_BLOCKED_PATTERNS;
    const protectedPaths = options.protectedPaths ?? PROTECTED_PATH_PREFIXES;
    const screenshotDirectory = options.screenshotDirectory
        ?? path.join(os.tmpdir(), 'jarvis-screenshots');
    const logger = options.logger ?? (() => {});

    function ok(payload) {
        return JSON.stringify({ successful: true, ...payload });
    }

    function fail(error) {
        return JSON.stringify({ successful: false, error });
    }

    async function executeShell(arguments_) {
        const command = String(arguments_.command ?? '').trim();

        if (command === '') {
            return fail('A command is required.');
        }

        const blocked = blockedPatterns.find((pattern) => command.toLowerCase().includes(pattern.toLowerCase()));

        if (blocked) {
            return fail(`The command [${command}] is permanently blocked because it matches the pattern [${blocked}].`);
        }

        const shell = platform === 'win32' ? 'powershell.exe' : '/bin/sh';
        const shellArgs = platform === 'win32' ? ['-NoProfile', '-Command', command] : ['-c', command];
        const timeout = Number(arguments_.timeout) > 0 ? Number(arguments_.timeout) * 1000 : 60_000;
        const startedAt = Date.now();

        try {
            const result = await runCommand(shell, shellArgs, {
                timeout,
                cwd: arguments_.working_directory ?? undefined,
            });

            const output = `${result.stdout}${result.stderr}`.trim();

            return ok({
                capability: 'run_shell_command',
                target: 'device',
                command,
                output,
                exit_code: result.code,
                timed_out: Boolean(result.timedOut),
                duration_ms: Date.now() - startedAt,
            });
        } catch (error) {
            return fail(error.message);
        }
    }
    async function executeComputerUse(arguments_) {
        const action = String(arguments_.action ?? '');

        try {
            if (action === 'screenshot') {
                await fileSystem.promises.mkdir(screenshotDirectory, { recursive: true });

                const destination = path.join(screenshotDirectory, `screen-${Date.now()}.png`);

                await captureScreenshot(destination);

                return ok({ capability: 'computer_use', target: 'device', action, driver: platform, output: { path: destination } });
            }

            if (action === 'move') {
                await moveMouse(Number(arguments_.x ?? 0), Number(arguments_.y ?? 0));

                return ok({ capability: 'computer_use', target: 'device', action, driver: platform, output: { ok: true } });
            }

            if (action === 'click') {
                await clickMouse(Number(arguments_.x ?? 0), Number(arguments_.y ?? 0), arguments_.button ?? 'left', Number(arguments_.clicks ?? 1));

                return ok({ capability: 'computer_use', target: 'device', action, driver: platform, output: { ok: true } });
            }

            if (action === 'type') {
                await typeText(String(arguments_.text ?? ''));

                return ok({ capability: 'computer_use', target: 'device', action, driver: platform, output: { ok: true } });
            }

            if (action === 'key') {
                await pressKey(String(arguments_.key ?? ''));

                return ok({ capability: 'computer_use', target: 'device', action, driver: platform, output: { ok: true } });
            }

            if (action === 'scroll') {
                await scroll(Number(arguments_.amount ?? 0));

                return ok({ capability: 'computer_use', target: 'device', action, driver: platform, output: { ok: true } });
            }

            if (action === 'open_app') {
                await openApplication(String(arguments_.application ?? ''));

                return ok({ capability: 'computer_use', target: 'device', action, driver: platform, output: { ok: true } });
            }

            return fail(`Unknown computer use action [${action}].`);
        } catch (error) {
            return fail(error.message);
        }
    }

    async function captureScreenshot(destination) {
        if (platform === 'darwin') {
            return runCommand('screencapture', ['-x', destination], { timeout: 30_000 });
        }

        if (platform === 'win32') {
            return runCommand('powershell.exe', ['-NoProfile', '-Command',
                'Add-Type -AssemblyName System.Windows.Forms,System.Drawing; '
                + '$b=[System.Windows.Forms.SystemInformation]::VirtualScreen; '
                + '$bmp=New-Object System.Drawing.Bitmap $b.Width,$b.Height; '
                + '$g=[System.Drawing.Graphics]::FromImage($bmp); '
                + '$g.CopyFromScreen($b.Left,$b.Top,0,0,$bmp.Size); '
                + `$bmp.Save('${destination}',[System.Drawing.Imaging.ImageFormat]::Png)`], { timeout: 30_000 });
        }

        return runCommand('scrot', ['-o', destination], { timeout: 30_000 });
    }

    async function moveMouse(x, y) {
        if (platform === 'darwin') {
            return runCommand('cliclick', [`m:${x},${y}`], {});
        }

        if (platform === 'win32') {
            return runCommand('powershell.exe', ['-NoProfile', '-Command',
                'Add-Type -AssemblyName System.Windows.Forms; '
                + `[System.Windows.Forms.Cursor]::Position = New-Object System.Drawing.Point(${x},${y})`], {});
        }

        return runCommand('xdotool', ['mousemove', '--sync', String(x), String(y)], {});
    }

    async function clickMouse(x, y, button, clicks) {
        const repeat = Math.max(1, clicks);

        if (platform === 'darwin') {
            const prefix = button === 'right' ? 'rc:' : (repeat > 1 ? 'cc:' : 'c:');

            return runCommand('cliclick', [`${prefix}${x},${y}`], {});
        }

        if (platform === 'win32') {
            const flag = button === 'right' ? '0x0008' : (button === 'middle' ? '0x0020' : '0x0002');

            return runCommand('powershell.exe', ['-NoProfile', '-Command',
                '$sig=\'[DllImport("user32.dll")] public static extern void mouse_event(uint f,uint dx,uint dy,int d,int e); '
                + '[DllImport("user32.dll")] public static extern bool SetCursorPos(int x,int y);\'; '
                + '$m=Add-Type -MemberDefinition $sig -Name JarvisMouse -Namespace Jarvis -PassThru; '
                + `[Jarvis.JarvisMouse]::SetCursorPos(${x},${y}); `
                + `1..${repeat} | ForEach-Object { $m::mouse_event(${flag},0,0,0,0) }`], {});
        }

        const buttonNumber = button === 'right' ? '3' : (button === 'middle' ? '2' : '1');

        await moveMouse(x, y);

        return runCommand('xdotool', ['click', '--repeat', String(repeat), buttonNumber], {});
    }

    async function typeText(text) {
        if (platform === 'darwin') {
            const escaped = text.replace(/\\/g, '\\\\').replace(/"/g, '\\"');

            return runCommand('osascript', ['-e', `tell application "System Events" to keystroke "${escaped}"`], {});
        }

        if (platform === 'win32') {
            return runCommand('powershell.exe', ['-NoProfile', '-Command',
                'Add-Type -AssemblyName System.Windows.Forms; '
                + `[System.Windows.Forms.SendKeys]::SendWait('${text.replace(/['+^%~(){}[\]]/g, ' ')}')`], {});
        }

        return runCommand('xdotool', ['type', '--delay', '12', '--', text], {});
    }

    async function pressKey(key) {
        if (platform === 'darwin') {
            const parts = key.toLowerCase().split('+').filter(Boolean);
            const keystroke = parts.pop() ?? '';
            const modifiers = parts.map((part) => ({
                cmd: 'command', command: 'command', super: 'command', meta: 'command',
                ctrl: 'control', control: 'control',
                alt: 'option', opt: 'option', option: 'option',
                shift: 'shift',
            }[part])).filter(Boolean);
            const using = modifiers.length > 0
                ? ` using {${modifiers.map((modifier) => `${modifier} down`).join(', ')}}`
                : '';

            return runCommand('osascript', ['-e', `tell application "System Events" to keystroke "${keystroke}"${using}`], {});
        }

        if (platform === 'win32') {
            const parts = key.toLowerCase().split('+').filter(Boolean);
            const keystroke = parts.pop() ?? '';
            const prefix = parts.map((part) => ({
                ctrl: '^', control: '^', cmd: '^', command: '^', super: '^', meta: '^',
                alt: '%', opt: '%', option: '%', shift: '+',
            }[part] ?? '')).join('');

            return runCommand('powershell.exe', ['-NoProfile', '-Command',
                'Add-Type -AssemblyName System.Windows.Forms; '
                + `[System.Windows.Forms.SendKeys]::SendWait('${prefix}${keystroke}')`], {});
        }

        return runCommand('xdotool', ['key', '--', key.replace(/\bcmd\b|\bcommand\b/g, 'super')], {});
    }

    async function scroll(amount) {
        if (platform === 'darwin' || platform === 'win32') {
            throw new Error(`The [scroll] action is not supported on ${platform} yet.`);
        }

        const button = amount >= 0 ? '4' : '5';

        return runCommand('xdotool', ['click', '--repeat', String(Math.max(1, Math.abs(amount))), button], {});
    }

    async function openApplication(application) {
        if (platform === 'darwin') {
            return runCommand('open', ['-a', application], {});
        }

        if (platform === 'win32') {
            return runCommand('powershell.exe', ['-NoProfile', '-Command', `Start-Process '${application}'`], {});
        }

        return runCommand('xdg-open', [application], {});
    }

    async function readFile(arguments_) {
        const target = String(arguments_.path ?? '');

        try {
            const contents = await fileSystem.promises.readFile(target, 'utf8');

            return ok({ capability: 'read_file', target: 'device', path: target, contents });
        } catch (error) {
            return fail(`Unable to read [${target}]: ${error.message}`);
        }
    }

    async function writeFile(arguments_) {
        const target = String(arguments_.path ?? '');
        const contents = String(arguments_.contents ?? '');
        const protectedPrefix = protectedPaths.find((prefix) => target.startsWith(prefix));

        if (protectedPrefix) {
            return fail(`Writing to [${target}] is blocked by the Jarvis configuration.`);
        }

        try {
            await fileSystem.promises.mkdir(path.dirname(target), { recursive: true });
            await fileSystem.promises.writeFile(target, contents, 'utf8');

            return ok({ capability: 'write_file', target: 'device', path: target, bytes: Buffer.byteLength(contents) });
        } catch (error) {
            return fail(`Unable to write [${target}]: ${error.message}`);
        }
    }

    async function listDirectory(arguments_) {
        const target = String(arguments_.path ?? '.');

        try {
            const entries = await fileSystem.promises.readdir(target, { withFileTypes: true });

            return ok({
                capability: 'list_directory',
                target: 'device',
                path: target,
                entries: entries.map((entry) => ({
                    name: entry.name,
                    path: path.join(target, entry.name),
                    type: entry.isDirectory() ? 'directory' : 'file',
                })),
            });
        } catch (error) {
            return fail(`Unable to list [${target}]: ${error.message}`);
        }
    }

    /**
     * Execute a capability requested by the Jarvis server.
     */
    async function execute(capability, arguments_ = {}) {
        logger(`jarvis: executing ${capability}`);

        switch (capability) {
            case 'run_shell_command':
                return executeShell(arguments_);
            case 'computer_use':
                return executeComputerUse(arguments_);
            case 'read_file':
                return readFile(arguments_);
            case 'write_file':
                return writeFile(arguments_);
            case 'list_directory':
                return listDirectory(arguments_);
            default:
                return fail(`Unknown capability [${capability}].`);
        }
    }

    return { execute, executeShell, executeComputerUse, readFile, writeFile, listDirectory };
}

module.exports = { createLocalExecutor, defaultRunCommand, DEFAULT_BLOCKED_PATTERNS, PROTECTED_PATH_PREFIXES };
