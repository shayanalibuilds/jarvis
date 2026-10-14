'use strict';

const { app, BrowserWindow, Menu, Tray, globalShortcut, ipcMain, nativeImage, net, shell } = require('electron');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const { createLocalExecutor } = require('./src/local-executor');
const { DeviceAgent } = require('./src/device-agent');

const TRAY_ICON = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAASElEQVR4nGNojPrPQAnGKvjGY+Z/bJigAbg04jMIq+bGqP9YMTZDMAzApRndEBQDiNWMzZBRA6hlAMXRSJWERHFSpkpmIgcDANF+eZ8+1+NFAAAAAElFTkSuQmCC';

const DEFAULT_CONFIG = {
    url: process.env.JARVIS_URL ?? 'http://localhost:8000',
    token: null,
    deviceName: `${os.hostname()} (${os.platform()})`,
    shortcuts: {
        takeOver: 'CommandOrControl+Shift+J',
        pause: 'CommandOrControl+Shift+Space',
        stop: 'CommandOrControl+Shift+Backspace',
    },
};

let mainWindow = null;
let tray = null;
let agent = null;
let config = { ...DEFAULT_CONFIG };
let status = { state: 'starting', session: null, message: 'Starting Jarvis...' };

function configPath() {
    return path.join(app.getPath('userData'), 'jarvis.json');
}

function loadConfig() {
    try {
        const stored = JSON.parse(fs.readFileSync(configPath(), 'utf8'));

        config = { ...DEFAULT_CONFIG, ...stored, shortcuts: { ...DEFAULT_CONFIG.shortcuts, ...(stored.shortcuts ?? {}) } };
    } catch {
        config = { ...DEFAULT_CONFIG };
    }
}

function saveConfig() {
    fs.mkdirSync(path.dirname(configPath()), { recursive: true });
    fs.writeFileSync(configPath(), JSON.stringify(config, null, 4));
}

function publish(next) {
    status = { ...status, ...next };

    if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.webContents.send('jarvis:status', status);
    }

    refreshTrayMenu();
}

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1280,
        height: 860,
        minWidth: 900,
        minHeight: 600,
        title: 'Jarvis',
        backgroundColor: '#18181b',
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    mainWindow.loadURL(config.url);

    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        shell.openExternal(url);

        return { action: 'deny' };
    });

    mainWindow.webContents.on('did-finish-load', () => {
        pairIfPossible().catch((error) => publish({ message: `Pairing failed: ${error.message}` }));
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

/**
 * Register the global shortcuts that always win, even when Jarvis is working.
 */
function registerShortcuts() {
    globalShortcut.unregisterAll();

    const bindings = [
        [config.shortcuts.takeOver, 'take_over', 'Take over'],
        [config.shortcuts.pause, 'pause', 'Pause / resume'],
        [config.shortcuts.stop, 'stop', 'Stop'],
    ];

    for (const [accelerator, action, label] of bindings) {
        const registered = globalShortcut.register(accelerator, () => {
            control(action).catch((error) => publish({ message: `${label} failed: ${error.message}` }));
        });

        if (!registered) {
            console.warn(`jarvis: could not register ${accelerator}`);
        }
    }
}

/**
 * Ask the server to take over, pause, resume, or stop the active session.
 */
async function control(action) {
    if (!config.token) {
        publish({ message: 'Sign in to Jarvis first so this computer can be paired.' });

        return null;
    }

    const response = await net.fetch(`${config.url}/api/jarvis/control`, {
        method: 'POST',
        headers: {
            'X-Jarvis-Device-Token': config.token,
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action }),
    });

    if (!response.ok) {
        throw new Error(`control(${action}) failed with status ${response.status}`);
    }

    const payload = await response.json();

    publish({ session: payload.session ?? null, message: payload.message ?? null });

    return payload;
}

/**
 * Pair this computer with the signed in account, once.
 */
async function pairIfPossible() {
    if (config.token) {
        startAgent();

        return;
    }

    const csrfToken = await mainWindow?.webContents.executeJavaScript(
        "document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') ?? null",
    );

    if (!csrfToken) {
        publish({ message: 'Sign in to Jarvis to pair this computer.' });

        return;
    }

    const response = await net.fetch(`${config.url}/jarvis/devices`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ name: config.deviceName, platform: os.platform() }),
    });

    if (!response.ok) {
        throw new Error(`pairing failed with status ${response.status}`);
    }

    const payload = await response.json();

    config.token = payload.device?.token ?? null;
    saveConfig();

    publish({ message: `Paired as ${payload.device?.name ?? config.deviceName}.` });

    startAgent();
}

/**
 * Start executing tool calls on this computer.
 */
function startAgent() {
    if (!config.token) {
        return;
    }

    agent?.stop();

    agent = new DeviceAgent({
        baseUrl: config.url,
        token: config.token,
        executor: createLocalExecutor({ logger: (message) => console.log(message) }),
        fetchImpl: (url, options) => net.fetch(url, options),
        onEvent: (event) => {
            if (event.type === 'heartbeat' || event.type === 'control') {
                publish({ state: event.session ? 'connected' : 'idle', session: event.session ?? null });
            }

            if (event.type === 'call.started') {
                publish({ state: 'working', message: `Running ${event.call.capability}` });
            }

            if (event.type === 'call.finished') {
                publish({ state: 'connected', message: null });
            }
        },
    });

    agent.start();
    publish({ state: 'connected', message: 'This computer is connected to Jarvis.' });
}

function refreshTrayMenu() {
    if (!tray) {
        return;
    }

    const session = status.session;
    const label = session ? `${session.status_label}${session.current_tool ? ` (${session.current_tool})` : ''}` : 'No active session';

    tray.setToolTip(`Jarvis - ${label}`);

    tray.setContextMenu(Menu.buildFromTemplate([
        { label: `Jarvis: ${label}`, enabled: false },
        { type: 'separator' },
        { label: `Take over  (${config.shortcuts.takeOver})`, click: () => control('take_over').catch(() => {}) },
        { label: `Pause / resume  (${config.shortcuts.pause})`, click: () => control('pause').catch(() => {}) },
        { label: `Stop  (${config.shortcuts.stop})`, click: () => control('stop').catch(() => {}) },
        { type: 'separator' },
        { label: 'Open Jarvis', click: () => { mainWindow?.show(); mainWindow?.focus(); } },
        { label: 'Quit Jarvis', click: () => { agent?.stop(); app.quit(); } },
    ]));
}

function createTray() {
    tray = new Tray(nativeImage.createFromDataURL(TRAY_ICON));
    tray.on('click', () => { mainWindow?.show(); mainWindow?.focus(); });
    refreshTrayMenu();
}

app.whenReady().then(() => {
    loadConfig();

    ipcMain.handle('jarvis:status', () => status);
    ipcMain.handle('jarvis:shortcuts', () => config.shortcuts);
    ipcMain.handle('jarvis:control', (_event, action) => control(action));
    ipcMain.handle('jarvis:pair', () => pairIfPossible());

    createWindow();
    createTray();
    registerShortcuts();
});

app.on('activate', () => {
    if (mainWindow === null) {
        createWindow();
    }
});

app.on('window-all-closed', () => {
    // Jarvis keeps running in the tray so the take-over shortcuts stay available.
});

app.on('will-quit', () => {
    globalShortcut.unregisterAll();
    agent?.stop();
});
