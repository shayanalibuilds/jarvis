'use strict';

const { contextBridge, ipcRenderer } = require('electron');

/**
 * The bridge the Jarvis web application uses to talk to the desktop shell.
 */
contextBridge.exposeInMainWorld('jarvisDesktop', {
    isDesktop: true,
    platform: process.platform,
    shortcuts: () => ipcRenderer.invoke('jarvis:shortcuts'),
    status: () => ipcRenderer.invoke('jarvis:status'),
    pair: () => ipcRenderer.invoke('jarvis:pair'),
    control: (action) => ipcRenderer.invoke('jarvis:control', action),
    onStatus: (callback) => {
        const listener = (_event, payload) => callback(payload);

        ipcRenderer.on('jarvis:status', listener);

        return () => ipcRenderer.removeListener('jarvis:status', listener);
    },
});
