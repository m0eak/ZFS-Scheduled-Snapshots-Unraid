'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const scripts = [
    'zfs.scheduled.snapshots/web/assets/js/next.js',
    'zfs.scheduled.snapshots/web/assets/js/datasets.js',
    'zfs.scheduled.snapshots/web/assets/js/snapshots.js',
    'zfs.scheduled.snapshots/web/assets/js/overview.js',
    'zfs.scheduled.snapshots/web/assets/js/logs.js',
    'zfs.scheduled.snapshots/web/assets/js/snapshot-inspector.js',
];

class Element {
    constructor(id = '') {
        this.id = id;
        this._innerHTML = '';
        this._parsedNodes = null;
        this.textContent = '';
        this.hidden = false;
        this._disabled = false;
        Object.defineProperty(this, 'disabled', {
            configurable: true,
            get: () => this._disabled,
            set: (value) => {
                this._disabled = Boolean(value);
                if (this._disabled && this.ownerDocument?.activeElement === this) {
                    this.ownerDocument.activeElement = this.ownerDocument.body;
                }
            },
        });
        this.value = '';
        this.tagName = '';
        this.dataset = {};
        this.style = { setProperty() {}, removeProperty() {} };
        this.attributes = new Map();
        this.children = [];
        this.listeners = {};
        const classes = new Set();
        this.classList = {
            add(...names) { names.forEach((name) => classes.add(name)); },
            remove(...names) { names.forEach((name) => classes.delete(name)); },
            toggle(name, force) {
                if (force === undefined ? !classes.has(name) : force) classes.add(name);
                else classes.delete(name);
                return classes.has(name);
            },
            contains(name) { return classes.has(name); },
        };
    }
    get innerHTML() { return this._innerHTML; }
    set innerHTML(value) { this._innerHTML = String(value); this._parsedNodes = null; }
    setAttribute(name, value) { this.attributes.set(name, String(value)); }
    getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
    removeAttribute(name) { this.attributes.delete(name); }
    appendChild(child) {
        this.children.push(child);
        child.parentNode = this;
        if (typeof child.innerHTML === 'string') this.innerHTML += child.innerHTML;
        return child;
    }
    remove() {
        this.removed = true;
        if (this.parentNode) this.parentNode.children = this.parentNode.children.filter((child) => child !== this);
    }
    addEventListener(name, callback) { (this.listeners[name] ||= []).push(callback); }
    querySelector(selector) {
        return this.querySelectorAll(selector)[0] || null;
    }
    querySelectorAll(selector) {
        if (selector === 'button' || selector.startsWith('button[')) {
            const buttons = [];
            for (const match of this.innerHTML.matchAll(/<button\b[^>]*>/g)) {
                const attributes = new Map();
                for (const attr of match[0].matchAll(/([\w-]+)(?:="([^"]*)")?/g)) {
                    attributes.set(attr[1], attr[2] ?? '');
                }
                const button = new Element();
                button.tagName = 'BUTTON';
                button.ownerDocument = this.ownerDocument;
                button.attributes = attributes;
                let markup = match[0];
                const replaceMarkup = (nextMarkup) => {
                    this.innerHTML = this.innerHTML.replace(markup, nextMarkup);
                    markup = nextMarkup;
                };
                let isDisabled = attributes.has('disabled');
                Object.defineProperty(button, 'disabled', {
                    get: () => isDisabled,
                    set: (value) => {
                        isDisabled = Boolean(value);
                        if (!isDisabled) {
                            button.attributes.delete('disabled');
                            replaceMarkup(markup.replace(/\sdisabled(?:="[^"]*")?/, ''));
                        }
                    },
                });
                button.removeAttribute = (name) => {
                    button.attributes.delete(name);
                    if (name === 'disabled') button.disabled = false;
                    else replaceMarkup(markup.replace(new RegExp(`\\s${name}(?:="[^"]*")?`), ''));
                };
                buttons.push(button);
            }
            return selector === 'button' ? buttons : buttons.filter((button) => matchesSelector(button, selector));
        }
        if (!this._parsedNodes) {
            this._parsedNodes = [];
            const pattern = /<(button|input|select|textarea|a|section|div)\b([^>]*)>/gi;
            for (const match of this.innerHTML.matchAll(pattern)) {
                const node = new Element();
                node.tagName = match[1].toUpperCase();
                node.ownerDocument = this.ownerDocument;
                for (const attr of match[2].matchAll(/([\w-]+)(?:="([^"]*)")?/g)) {
                    node.setAttribute(attr[1], attr[2] ?? '');
                }
                node.disabled = node.attributes.has('disabled');
                node.href = node.getAttribute('href') || '';
                node.matches = (query) => matchesSelector(node, query);
                node.closest = (query) => node.matches(query) ? node : null;
                this._parsedNodes.push(node);
            }
        }
        const queries = selector.split(',').map((part) => part.trim());
        return this._parsedNodes.filter((node) => queries.some((query) => matchesSelector(node, query)));
    }
    matches(selector) { return matchesSelector(this, selector); }
    closest(selector) { return this.matches(selector) ? this : null; }
    contains(element) {
        return element === this || this.querySelectorAll('button, input, select, textarea, a[href], [tabindex]').includes(element);
    }
    focus() {
        if (this.ownerDocument && !this.disabled) this.ownerDocument.activeElement = this;
    }
}

function matchesSelector(node, selector) {
    const tag = selector.match(/^[a-z]+/i)?.[0];
    if (tag && node.tagName.toLowerCase() !== tag.toLowerCase()) return false;
    for (const match of selector.matchAll(/\.([\w-]+)|\[([\w-]+)(?:=["']?([^"'\]]+)["']?)?\]/g)) {
        if (match[1] && !(node.getAttribute('class') || '').split(/\s+/).includes(match[1])) return false;
        if (match[2] && (!node.attributes.has(match[2]) || (match[3] && node.getAttribute(match[2]) !== match[3]))) return false;
    }
    if (selector.includes(':not([disabled])') && node.disabled) return false;
    if (selector.includes('[href]') && !node.href) return false;
    return Boolean(tag || selector.startsWith('.') || selector.startsWith('['));
}

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}

function response(payload) {
    return {
        ok: true,
        status: 200,
        headers: { get: () => 'application/json' },
        json: async () => payload,
    };
}

async function flushMicrotasks() {
    await Promise.resolve();
    await Promise.resolve();
}

function boot(scriptNames = null) {
    const elements = new Map();
    const documentListeners = {};
    const windowListeners = {};
    const storage = new Map();
    const frames = [];
    const timers = [];
    const toasts = [];
    const body = new Element('body');
    body.dataset = { locale: 'en' };
    const document = {
        body,
        title: 'Snapshots',
        activeElement: null,
        documentElement: new Element('html'),
        createElement: () => { const element = new Element(); element.ownerDocument = document; return element; },
        getElementById(id) {
            const child = body.children.find((item) => item.id === id);
            if (child) return child;
            return elements.get(id) || null;
        },
        querySelector() { return null; },
        querySelectorAll() { return []; },
        addEventListener(name, callback) { (documentListeners[name] ||= []).push(callback); },
        removeEventListener(name, callback) {
            documentListeners[name] = (documentListeners[name] || []).filter((item) => item !== callback);
        },
    };
    body.ownerDocument = document;
    for (const id of [
        'datasets-table', 'snapshots-timeline', 'snapshots-table', 'activity-panel',
        'protection-ring', 'protection-ring-value', 'last-snapshot', 'last-dataset',
        'snapshot-dataset-title', 'snapshot-inventory-count', 'inventory-count',
        'config-day-monthly', 'config-day-weekly', 'config-frequency', 'config-time',
        'config-keep', 'config-enabled', 'config-readonly', 'config-retain-days',
        'dataset-name', 'edit-modal', 'row-time', 'row-day', 'label-day',
        'new-dataset-mount',
        'new-dataset-mountpoint', 'new-dataset-parent', 'new-dataset-child',
        'new-dataset-default-mountpoint', 'dataset-count', 'enabled-count',
        'snapshot-count', 'readonly-count', 'snapshot-used-bytes',
        'new-dataset-atime', 'new-dataset-casesensitivity', 'new-dataset-compression',
        'new-dataset-quota', 'new-dataset-quota-unit',
        'log-level', 'log-status', 'logs-inventory-count', 'logs-table',
    ]) {
        const element = new Element(id);
        element.ownerDocument = document;
        elements.set(id, element);
    }
    const storageApi = {
        get length() { return storage.size; },
        key(index) { return [...storage.keys()][index] ?? null; },
        getItem(key) { return storage.get(String(key)) ?? null; },
        setItem(key, value) { storage.set(String(key), String(value)); },
        removeItem(key) { storage.delete(String(key)); },
        clear() { storage.clear(); },
    };
    const fetchCalls = [];
    const window = {
        location: { href: 'http://localhost/web/index.php', pathname: '/web/index.php', search: '' },
        history: { pushState() {}, replaceState() {} },
        localStorage: storageApi,
        sessionStorage: storageApi,
        matchMedia: () => ({ matches: true, addEventListener() {} }),
        addEventListener(name, callback) { (windowListeners[name] ||= []).push(callback); },
        removeEventListener() {},
        requestAnimationFrame(callback) { frames.push(callback); return frames.length; },
        cancelAnimationFrame() {},
        setTimeout(callback) { timers.push(callback); return timers.length; },
        scrollTo() {},
        ZSS_LOCALE: 'en',
        ZSS_LOCALE_PREFERENCE: 'auto',
    };
    const sandbox = {
        window,
        document,
        localStorage: storageApi,
        sessionStorage: storageApi,
        URL,
        URLSearchParams,
        AbortController,
        Intl,
        Map,
        Set,
        Date,
        Math,
        JSON,
        Promise,
        console: { warn() {}, error() {}, log() {} },
        requestAnimationFrame: window.requestAnimationFrame,
        cancelAnimationFrame: window.cancelAnimationFrame,
        fetch: (...args) => {
            fetchCalls.push(args);
            throw new Error(`Unexpected network request: ${args[0]}`);
        },
        t: (key, fallback, values) => {
            if (values && typeof values === 'object') {
                return Object.entries(values).reduce((text, [name, value]) =>
                    text.replaceAll(`{${name}}`, String(value)), fallback || key);
            }
            return fallback || key;
        },
        zssToast: (toast) => toasts.push(toast),
        CSS: { escape: (value) => String(value) },
        HTMLElement: Element,
        Element,
        performance: { now: () => 100 },
        getComputedStyle: () => ({ getPropertyValue: () => '0ms' }),
    };
    sandbox.globalThis = sandbox;
    const context = vm.createContext(sandbox);
    window.URLSearchParams = URLSearchParams;
    window.URL = URL;
    window.fetch = sandbox.fetch;
    sandbox.encodeURIComponent = encodeURIComponent;
    sandbox.decodeURIComponent = decodeURIComponent;
    for (const relative of scripts) {
        if (scriptNames && !scriptNames.some((name) => relative.endsWith(`/${name}`))) continue;
        const sourceCopyPath = process.env.FRONTEND_SOURCE_ROOT
            ? path.join(process.env.FRONTEND_SOURCE_ROOT, relative)
            : '';
        const sourcePath = relative.endsWith('/logs.js') && process.env.FRONTEND_LOGS_SOURCE
            ? process.env.FRONTEND_LOGS_SOURCE
            : sourceCopyPath && fs.existsSync(sourceCopyPath)
                ? sourceCopyPath
                : path.join(root, relative);
        vm.runInContext(fs.readFileSync(sourcePath, 'utf8'), context, { filename: relative });
    }
    return {
        context,
        document,
        window,
        elements,
        getElement(id) {
            if (!elements.has(id)) {
                const element = new Element(id);
                element.ownerDocument = document;
                elements.set(id, element);
            }
            return elements.get(id);
        },
        storage,
        frames,
        timers,
        toasts,
        fetchCalls,
        fireDocument(name) {
            for (const callback of documentListeners[name] || []) callback();
        },
        fireKey(key, target = document.activeElement, shiftKey = false) {
            const event = {
                key, target, shiftKey, defaultPrevented: false,
                preventDefault() { this.defaultPrevented = true; },
            };
            for (const callback of documentListeners.keydown || []) callback(event);
            for (const callback of body.listeners.keydown || []) callback(event);
            return event;
        },
        fireWindow(name, event) {
            for (const callback of windowListeners[name] || []) callback(event);
        },
        setFetch(handler) { sandbox.fetch = handler; window.fetch = handler; },
        staleBanner() {
            return body.children.find((child) => child.id === 'zss-stale-banner')
                || elements.get('zss-stale-banner')
                || new Element('missing-banner');
        },
        run(source) { return vm.runInContext(source, context); },
    };
}

function prepareWriteForm(app, operation) {
    const values = {
        'new-dataset-parent': 'tank',
        'new-dataset-child': 'child',
        'new-dataset-mount': 'yes',
        'new-dataset-mountpoint': '/mnt/tank/child',
        'new-dataset-atime': 'on',
        'new-dataset-casesensitivity': 'sensitive',
        'new-dataset-compression': 'lz4',
        'new-dataset-quota': '0',
        'new-dataset-quota-unit': 'G',
        'dataset-name': 'tank/a',
        'config-frequency': 'daily',
        'config-keep': '31',
        'config-time': '00:00',
        'config-day-weekly': '1',
        'config-day-monthly': '1',
        'config-retain-days': '0',
    };
    for (const [id, value] of Object.entries(values)) app.getElement(id).value = value;
    for (const id of ['config-enabled', 'config-readonly']) app.getElement(id).checked = true;
    if (operation === 'createSnapshot') app.run("activeDataset = 'tank/a'");
}

test('write operations lock before confirmation and release for retry', { timeout: 10000 }, async () => {
    for (const operation of ['createSnapshot', 'createDataset', 'clearLogs']) {
        const app = boot();
        prepareWriteForm(app, operation);
        const confirmation = deferred();
        app.context.confirmationPromise = confirmation.promise;
        app.run('globalThis.confirmations = 0; zssConfirmAction = () => { globalThis.confirmations++; return globalThis.confirmationPromise; }');
        const button = app.getElement(`${operation}-button`);
        button.disabled = true;
        button.setAttribute('aria-label', 'Original action');
        button.setAttribute('aria-disabled', 'true');
        button.innerHTML = 'Original action';
        app.context.writeButton = button;

        const first = app.run(`${operation}(globalThis.writeButton)`);
        await flushMicrotasks();
        const second = app.run(`${operation}()`);
        await flushMicrotasks();
        assert.equal(app.context.confirmations, 1, `${operation} should ignore duplicate calls during confirmation`);
        assert.equal(button.disabled, true);
        assert.match(button.innerHTML, /zss-spinner/);
        assert.equal(button.getAttribute('aria-label'), 'Original action');
        assert.equal(button.getAttribute('aria-disabled'), 'true');
        confirmation.resolve(false);
        await Promise.all([first, second]);
        assert.equal(button.disabled, true, `${operation} should restore original disabled state`);
        assert.equal(button.innerHTML, 'Original action');
        assert.equal(button.getAttribute('aria-label'), 'Original action');
        assert.equal(button.getAttribute('aria-disabled'), 'true');

        const retryConfirmation = deferred();
        app.context.confirmationPromise = retryConfirmation.promise;
        const retry = app.run(`${operation}(globalThis.writeButton)`);
        await flushMicrotasks();
        assert.equal(app.context.confirmations, 2, `${operation} should allow retry after cancellation`);
        retryConfirmation.resolve(false);
        await retry;
    }
});

test('createDataset captures its submitted fields before confirmation waits', { timeout: 10000 }, async () => {
    const app = boot();
    prepareWriteForm(app, 'createDataset');
    const confirmation = deferred();
    app.context.confirmationPromise = confirmation.promise;
    app.run('zssConfirmAction = () => globalThis.confirmationPromise; loadDatasets = async () => {}; updateDefaultMountpointHint = () => {}');
    app.run('postJson = (...args) => { globalThis.datasetCreateArgs = args; return Promise.resolve({ ok: true }); }');
    const operation = app.run('createDataset()');
    app.getElement('new-dataset-child').value = 'changed-after-confirm-open';
    app.getElement('new-dataset-mountpoint').value = '/changed';
    confirmation.resolve(true);
    await operation;
    assert.equal(app.context.datasetCreateArgs[1].child, 'child');
    assert.equal(app.context.datasetCreateArgs[1].mountpoint, '/mnt/tank/child');
});

test('late save success only closes the edit session that submitted it', { timeout: 10000 }, async () => {
    for (const reopenedName of ['tank/b', 'tank/a']) {
        const app = boot();
        const datasets = ['tank/a', 'tank/b'].map((name) => ({
            name, enabled: true, frequency: 'daily', keep: '31', time: '00:00', day: '1',
            readonly: false, retain_days: '0',
        }));
        app.context.testDatasets = datasets;
        app.run('currentDatasets = globalThis.testDatasets');
        app.run('globalThis.refreshCount = 0; globalThis.successToasts = []; zssToast = (toast) => successToasts.push(toast); loadDatasets = async () => { globalThis.refreshCount++; }; window.refreshResourceTree = () => {};');
        const pending = deferred();
        app.context.pendingRequest = pending.promise;
        app.run('postJson = () => globalThis.pendingRequest');
        const button = app.getElement('saveConfig-button');
        button.innerHTML = 'Save';
        app.context.writeButton = button;

        app.run("openEdit('tank/a')");
        const save = app.run('saveConfig(globalThis.writeButton)');
        await flushMicrotasks();
        app.fireKey('Escape');
        assert.equal(app.getElement('edit-modal').style.display, 'none');
        app.run(`openEdit('${reopenedName}')`);
        app.getElement('config-keep').value = '55';

        pending.resolve({ ok: true });
        await save;

        assert.equal(app.getElement('edit-modal').style.display, 'flex', `${reopenedName} edit session should stay open`);
        assert.equal(app.getElement('config-keep').value, '55', 'new session draft should be preserved');
        assert.equal(app.context.refreshCount, 1, 'success should still refresh datasets');
        assert.equal(app.context.successToasts.filter((toast) => toast.type === 'success').length, 1, 'success should still show a toast');
        assert.equal(button.disabled, false, 'save button should be available in the new edit session');
        assert.equal(app.run('saveConfigPending'), false);
    }
});

test('write operations lock during POST and release after API and network failures', { timeout: 10000 }, async () => {
    for (const operation of ['createSnapshot', 'createDataset', 'saveConfig', 'clearLogs']) {
        const app = boot();
        prepareWriteForm(app, operation);
        app.run('zssConfirmAction = async () => true; globalThis.requestCount = 0');
        app.run('loadDatasets = async () => {}; loadLogs = () => {}; updateDefaultMountpointHint = () => {}; closeModal = () => {}');
        let pending = deferred();
        app.context.pendingRequest = pending.promise;
        app.run('postJson = (...args) => { globalThis.requestCount++; globalThis.writeArgs = args; return globalThis.pendingRequest; }');
        const button = app.getElement(`${operation}-button`);
        button.innerHTML = 'Run';
        app.context.writeButton = button;

        const first = app.run(`${operation}(globalThis.writeButton)`);
        await flushMicrotasks();
        const second = app.run(`${operation}()`);
        await flushMicrotasks();
        assert.equal(app.context.requestCount, 1, `${operation} should send one request while pending`);
        assert.match(button.innerHTML, /zss-spinner/);
        pending.resolve({ ok: false, error: { message: 'expected failure' } });
        await Promise.all([first, second]);
        assert.equal(button.disabled, false);
        assert.equal(button.innerHTML, 'Run');

        pending = deferred();
        app.context.pendingRequest = pending.promise;
        const retry = app.run(`${operation}(globalThis.writeButton)`);
        await flushMicrotasks();
        assert.equal(app.context.requestCount, 2, `${operation} should allow retry after API failure`);
        pending.reject(new Error('network failure'));
        await retry;
        assert.equal(button.disabled, false);
        assert.equal(button.innerHTML, 'Run');

        pending = deferred();
        app.context.pendingRequest = pending.promise;
        const successRetry = app.run(`${operation}(globalThis.writeButton)`);
        await flushMicrotasks();
        assert.equal(app.context.requestCount, 3, `${operation} should allow retry after network failure`);
        pending.resolve({ ok: true });
        await successRetry;
        assert.equal(button.disabled, false);
        assert.equal(button.innerHTML, 'Run');

        pending = deferred();
        app.context.pendingRequest = pending.promise;
        if (operation === 'createDataset') app.getElement('new-dataset-child').value = 'child-again';
        const retryAfterSuccess = app.run(`${operation}(globalThis.writeButton)`);
        await flushMicrotasks();
        assert.equal(app.context.requestCount, 4, `${operation} should allow retry after success`);
        pending.resolve({ ok: true });
        await retryAfterSuccess;
        assert.equal(button.disabled, false);
        assert.equal(button.innerHTML, 'Run');
    }
});

test('saveConfig locks during POST and releases after success for another save', { timeout: 10000 }, async () => {
    const app = boot();
    prepareWriteForm(app, 'saveConfig');
    app.run('loadDatasets = async () => {}; closeModal = () => {}; globalThis.requestCount = 0');
    let pending = deferred();
    app.context.pendingRequest = pending.promise;
    app.run('postJson = () => { globalThis.requestCount++; return globalThis.pendingRequest; }');
    const button = app.getElement('saveConfig-button');
    button.innerHTML = 'Save';
    app.context.writeButton = button;

    const first = app.run('saveConfig(globalThis.writeButton)');
    await flushMicrotasks();
    const duplicate = app.run('saveConfig()');
    await flushMicrotasks();
    assert.equal(app.context.requestCount, 1);
    assert.match(button.innerHTML, /zss-spinner/);
    pending.resolve({ ok: true });
    await Promise.all([first, duplicate]);
    assert.equal(button.disabled, false);
    assert.equal(button.innerHTML, 'Save');

    pending = deferred();
    app.context.pendingRequest = pending.promise;
    const retry = app.run('saveConfig(globalThis.writeButton)');
    await flushMicrotasks();
    assert.equal(app.context.requestCount, 2, 'saveConfig should allow another save after success');
    pending.resolve({ ok: true });
    await retry;
    assert.equal(button.disabled, false);
    assert.equal(button.innerHTML, 'Save');
});

test('saveConfig rejects invalid schedule fields before POST and restores state for retry', async () => {
    const invalidCases = [
        { field: 'dataset-name', value: '   ', message: 'Enter a dataset name.' },
        { field: 'config-frequency', value: 'invalid', message: 'Choose a valid snapshot frequency.' },
        { field: 'config-keep', value: '', message: 'Keep must be a whole number of at least 1.' },
        { field: 'config-keep', value: '1.5', message: 'Keep must be a whole number of at least 1.' },
        { field: 'config-retain-days', value: '-1', message: 'Protection days must be a whole number of 0 or more.' },
        { field: 'config-retain-days', value: 'nope', message: 'Protection days must be a whole number of 0 or more.' },
        { field: 'config-time', value: '', message: 'Enter a valid time in HH:MM format.' },
        { field: 'config-time', value: '24:00', message: 'Enter a valid time in HH:MM format.' },
        { field: 'config-day-weekly', frequency: 'weekly', value: '0', message: 'Choose a weekday from 1 to 7.' },
        { field: 'config-day-weekly', frequency: 'weekly', value: '8', message: 'Choose a weekday from 1 to 7.' },
        { field: 'config-day-monthly', frequency: 'monthly', value: '0', message: 'Choose a day from 1 to 31.' },
        { field: 'config-day-monthly', frequency: 'monthly', value: '32', message: 'Choose a day from 1 to 31.' },
    ];

    for (const invalid of invalidCases) {
        const app = boot();
        prepareWriteForm(app, 'saveConfig');
        app.run('globalThis.requestCount = 0; globalThis.validationToasts = []; zssToast = (toast) => validationToasts.push(toast); postJson = async () => { globalThis.requestCount++; return { ok: false }; }');
        if (invalid.frequency) app.getElement('config-frequency').value = invalid.frequency;
        app.getElement(invalid.field).value = invalid.value;
        const button = app.getElement('saveConfig-button');
        button.innerHTML = 'Save';
        app.context.writeButton = button;

        await app.run('saveConfig(globalThis.writeButton)');

        assert.equal(app.context.requestCount, 0, `${invalid.field} should be rejected before POST`);
        assert.equal(app.context.validationToasts.length, 1, `${invalid.field} should show one toast`);
        assert.equal(app.context.validationToasts[0].type, 'error');
        assert.equal(app.context.validationToasts[0].message, invalid.message);
        assert.equal(button.disabled, false, `${invalid.field} should leave the button enabled`);
        assert.equal(button.innerHTML, 'Save', `${invalid.field} should restore the button label`);

        app.getElement(invalid.field).value = invalid.field === 'dataset-name' ? 'tank/a'
            : invalid.field === 'config-frequency' ? 'daily'
                : invalid.field === 'config-keep' ? '31'
                    : invalid.field === 'config-retain-days' ? '0'
                        : invalid.field === 'config-time' ? '00:00'
                            : '1';
        await app.run('saveConfig(globalThis.writeButton)');
        assert.equal(app.context.requestCount, 1, `${invalid.field} should release pending state for retry`);
        assert.equal(button.disabled, false);
        assert.equal(button.innerHTML, 'Save');
    }
});

test('snapshot creation posts to the captured dataset and refreshes the current selection', { timeout: 10000 }, async () => {
    const app = boot();
    app.run("activeDataset = 'tank/a'");
    const confirmation = deferred();
    const request = deferred();
    app.context.confirmationPromise = confirmation.promise;
    app.context.pendingRequest = request.promise;
    app.run('zssConfirmAction = (options) => { globalThis.confirmDetail = options.detail; return globalThis.confirmationPromise; }');
    app.run('postJson = (...args) => { globalThis.snapshotArgs = args; return globalThis.pendingRequest; }');
    app.context.refreshed = [];
    app.run('loadSnapshots = (name) => globalThis.refreshed.push(name)');

    const operation = app.run('createSnapshot()');
    app.run("activeDataset = 'tank/b'");
    confirmation.resolve(true);
    await flushMicrotasks();
    assert.equal(app.context.confirmDetail, 'tank/a');
    assert.equal(app.context.snapshotArgs[1].name, 'tank/a');
    request.resolve({ ok: true });
    await operation;
    app.run("activeDataset = 'tank/c'");
    for (const callback of app.timers) callback();
    assert.deepEqual(Array.from(app.context.refreshed), ['tank/c']);
});

test('confirmed identical cached dataset payload unlocks actual table actions', { timeout: 10000 }, async () => {
    const app = boot();
    const cached = { ok: true, data: [{
        name: 'tank/a', enabled: true, frequency: 'daily', readonly: false,
    }] };
    app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
        savedAt: Date.now(),
        payload: cached,
    }));
    const pending = deferred();
    app.setFetch(() => pending.promise);
    const loading = app.run('loadDatasets()');
    await flushMicrotasks();
    const table = app.getElement('datasets-table');
    assert.match(table.innerHTML, /disabled/);
    pending.resolve(response(cached));
    await loading;
    assert.doesNotMatch(table.innerHTML, /data-action="edit-dataset"[^>]*\sdisabled(?:\s|>)/);
});
test('fresh dataset schedule values reach the actual weekly edit form', { timeout: 10000 }, async () => {
    const app = boot();
    const cached = { ok: true, data: [{
        name: 'tank/a', enabled: true, frequency: 'daily', readonly: false, time: '00:00', day: 1,
    }] };
    app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
        savedAt: Date.now(),
        payload: cached,
    }));
    app.setFetch(async () => response({ ok: true, data: [{
        name: 'tank/a', enabled: true, frequency: 'weekly', readonly: true, time: '03:45', day: 5,
    }] }));
    await app.run('loadDatasets()');
    assert.equal(app.run('currentDatasets[0].frequency'), 'weekly');
    assert.equal(app.run('currentDatasets[0].readonly'), true);
    app.run("openEdit('tank/a')");
    assert.equal(app.getElement('config-time').value, '03:45');
    assert.equal(app.getElement('config-day-weekly').value, 5);
});

test('invalidation during an old response prevents it from repainting or recaching', { timeout: 10000 }, async () => {
    const app = boot();
    const old = deferred();
    app.setFetch(() => old.promise);
    const renders = [];
    const request = app.run(`zssLoadCached('/api/datasets.php', 'datasets', (payload) => {
        document.getElementById('datasets-table').textContent = payload.data[0].name;
        renders.push(payload.data[0].name);
    })`);
    await Promise.resolve();
    app.run("zssInvalidateDataCache('datasets')");
    old.resolve(response({ ok: true, data: [{ name: 'old' }] }));
    const result = await request;
    assert.equal(result.invalidated, true);
    assert.deepEqual(renders, []);
    assert.equal(app.getElement('datasets-table').textContent, '');
});

test('snapshot page loader keeps dataset B when A resolves last', { timeout: 10000 }, async () => {
    const app = boot();
    const a = deferred();
    const b = deferred();
    app.setFetch((url) => String(url).includes('name=A') ? a.promise : b.promise);
    const loadA = app.run("loadSnapshots('A')");
    const loadB = app.run("loadSnapshots('B')");
    b.resolve(response({ ok: true, data: [] }));
    await loadB;
    a.resolve(response({ ok: true, data: [{ name: 'A@old' }] }));
    await loadA;
    const timeline = app.getElement('snapshots-timeline');
    assert.doesNotMatch(timeline.innerHTML, /A@old/);
    assert.match(timeline.innerHTML, /No snapshots/);
});

test('cached empty A cannot leave Loading over uncached empty B', { timeout: 10000 }, async () => {
    const app = boot();
    app.setFetch(async () => response({ ok: true, data: [] }));
    await app.run("loadSnapshots('A')");
    app.run("activeDataset = 'A'");
    const b = deferred();
    app.setFetch(() => b.promise);
    const loadB = app.run("switchSnapshotDataset('B')");
    assert.match(app.getElement('snapshots-timeline').innerHTML, /Loading/);
    b.resolve(response({ ok: true, data: [] }));
    await loadB;
    const timeline = app.getElement('snapshots-timeline');
    assert.doesNotMatch(timeline.innerHTML, /Loading/);
    assert.match(timeline.innerHTML, /No snapshots/);
});

test('stale snapshot banner persists through partial failure and clears on successful refresh', { timeout: 10000 }, async () => {
    const app = boot();
    const cacheKey = app.run("zssCacheKey('datasets')");
    app.storage.set(cacheKey, JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [{ name: 'tank/a' }] },
    }));
    let attempts = 0;
    app.setFetch(async () => {
        attempts++;
        return response(attempts === 1
            ? { ok: false, error: { message: 'offline' } }
            : { ok: true, data: [{ name: 'tank/a' }] });
    });
    await app.run('loadDatasets()');
    let banner = app.staleBanner();
    assert.match(banner.innerHTML, /Showing cached data/);
    await app.run('loadDatasets()');
    assert.equal(attempts, 2, 'a failed shared request must allow a later refresh request');
    banner = app.staleBanner();
    assert.equal(banner.innerHTML, '', `remaining stale keys: ${app.run('[...zssStaleKeys].join(",")')}`);
});

test('stale banner retries the active page payload once and keeps cached content on failure', { timeout: 10000 }, async () => {
    const app = boot();
    app.window.location.pathname = '/web/snapshots.php';
    assert.equal(typeof app.run('window.zssStaleRetryHandlers.overview'), 'function');
    assert.equal(typeof app.run('window.zssStaleRetryHandlers.datasets'), 'function');
    assert.equal(typeof app.run('window.zssStaleRetryHandlers.snapshots'), 'function');
    app.storage.set(app.run("zssCacheKey('snapshots:tank/a')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [{ name: 'tank/a@cached' }] },
    }));
    let attempts = 0;
    app.setFetch(async () => {
        attempts++;
        return response({ ok: false, error: { message: 'offline' } });
    });
    app.run("activeDataset = 'tank/a'");
    await app.run("loadSnapshots('tank/a')");

    const banner = app.staleBanner();
    assert.match(banner.innerHTML, /<button[^>]*data-zss-stale-retry[^>]*>Refresh<\/button>/);
    const button = banner.querySelector('button[data-zss-stale-retry]');
    const click = banner.listeners.click[0];
    assert.equal(banner.listeners.click.length, 1);
    app.run("zssMarkStale('snapshots:tank/a')");
    assert.equal(banner.listeners.click.length, 1, 'redrawing the banner does not add another click listener');
    const cachedTimeline = app.getElement('snapshots-timeline').innerHTML;
    const retry = click({ target: button });
    const duplicateRetry = click({ target: button });
    assert.match(banner.innerHTML, /\sdisabled>/, 'the retry button is disabled while its request is pending');
    await Promise.all([retry, duplicateRetry]);

    assert.equal(banner.listeners.click.length, 1, 'busy-state redraw keeps the delegated listener bound once');
    assert.equal(attempts, 2, 'one click retries only the active dataset payload');
    assert.equal(app.run("zssStaleKeys.has('snapshots:tank/a')"), true, 'a failed retry keeps its stale marker');
    assert.equal(app.getElement('snapshots-timeline').innerHTML, cachedTimeline, 'a failed retry preserves the cached timeline');
    assert.doesNotMatch(banner.innerHTML, /\sdisabled>/, 'the retry button becomes available after failure');

    app.setFetch(async () => response({ ok: true, data: [] }));
    await banner.listeners.click[0]({ target: banner.querySelector('button[data-zss-stale-retry]') });
    assert.equal(app.run("zssStaleKeys.has('snapshots:tank/a')"), false, 'a successful retry clears its stale marker');
});

test('expired cached snapshot retry preserves the displayed timeline and stale banner', { timeout: 10000 }, async () => {
    const app = boot();
    app.window.location.pathname = '/web/snapshots.php';
    const cachedTimeline = { ok: true, data: [] };
    app.storage.set(app.run("zssCacheKey('snapshots:tank/a')"), JSON.stringify({
        savedAt: Date.now(),
        payload: cachedTimeline,
    }));
    app.setFetch(async () => response({ ok: false, error: { message: 'offline' } }));
    app.run("activeDataset = 'tank/a'");
    await app.run("loadSnapshots('tank/a')");
    const beforeRetry = app.getElement('snapshots-timeline').innerHTML;
    assert.match(beforeRetry, /No snapshots/);
    assert.match(app.staleBanner().innerHTML, /Showing cached data/);

    app.storage.set(app.run("zssCacheKey('snapshots:tank/a')"), JSON.stringify({
        savedAt: Date.now() - 301000,
        payload: cachedTimeline,
    }));
    const banner = app.staleBanner();
    await banner.listeners.click[0]({ target: banner.querySelector('button[data-zss-stale-retry]') });

    assert.equal(app.getElement('snapshots-timeline').innerHTML, beforeRetry, 'retry failure keeps the content already drawn in this timeline');
    assert.match(app.staleBanner().innerHTML, /Showing cached data/, 'the persistent banner remains tied to the displayed timeline');
});

test('expired snapshot stale key stays hidden after switching away and returning cold', { timeout: 10000 }, async () => {
    const app = boot();
    app.window.location.pathname = '/web/snapshots.php';
    const cachedA = { ok: true, data: [] };
    app.storage.set(app.run("zssCacheKey('snapshots:tank/a')"), JSON.stringify({
        savedAt: Date.now(),
        payload: cachedA,
    }));
    app.setFetch(async () => response({ ok: false, error: { message: 'offline' } }));
    app.run("activeDataset = 'tank/a'");
    await app.run("loadSnapshots('tank/a')");
    assert.match(app.getElement('snapshots-timeline').innerHTML, /No snapshots/);
    assert.match(app.staleBanner().innerHTML, /Showing cached data/);

    app.storage.set(app.run("zssCacheKey('snapshots:tank/a')"), JSON.stringify({
        savedAt: Date.now() - 301000,
        payload: cachedA,
    }));
    await app.run("switchSnapshotDataset('tank/b')");
    assert.match(app.getElement('snapshots-timeline').innerHTML, /Load failed/);
    assert.equal(app.staleBanner().innerHTML, '');

    await app.run("switchSnapshotDataset('tank/a')");
    assert.match(app.getElement('snapshots-timeline').innerHTML, /Load failed/);
    assert.equal(app.run("zssStaleKeys.has('snapshots:tank/a')"), true, 'the unrelated stale marker may remain stored');
    assert.equal(app.staleBanner().innerHTML, '', 'a retained key cannot claim cached content in a cold timeline');
});

test('switching to a valid cached snapshot timeline shows its stale banner after refresh failure', { timeout: 10000 }, async () => {
    const app = boot();
    app.window.location.pathname = '/web/snapshots.php';
    app.storage.set(app.run("zssCacheKey('snapshots:tank/b')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [] },
    }));
    app.setFetch(async () => response({ ok: false, error: { message: 'offline' } }));
    app.run("activeDataset = 'tank/a'");
    await app.run("switchSnapshotDataset('tank/b')");

    assert.match(app.getElement('snapshots-timeline').innerHTML, /No snapshots/, 'B cached timeline remains visible');
    assert.match(app.staleBanner().innerHTML, /Showing cached data/, 'B is marked stale only after its cache paints and refresh fails');
    assert.ok(app.staleBanner().querySelector('button[data-zss-stale-retry]'), 'the stale banner exposes retry for B');
});

test('cache invalidation clears stale claims before a normal refresh can fail', { timeout: 10000 }, async () => {
    const app = boot();
    app.window.location.pathname = '/web/datasets.php';
    app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [{ name: 'tank/a' }] },
    }));
    app.setFetch(async () => response({ ok: false, error: { message: 'offline' } }));
    await app.run('loadDatasets()');
    assert.match(app.staleBanner().innerHTML, /Showing cached data/);

    app.run('zssInvalidateDataCache()');
    await app.run('loadDatasets()');

    assert.equal(app.staleBanner().innerHTML, '', 'invalidated content cannot retain a cached-data claim');
    assert.equal(app.run("zssStaleKeys.has('datasets')"), false);
});

test('expired datasets, overview, and snapshot-list retries preserve their displayed cache', { timeout: 10000 }, async () => {
    const pages = [
        { path: '/web/datasets.php', loader: 'loadDatasets()', contentId: 'datasets-table' },
        { path: '/web/index.php', loader: 'loadOverview()', contentId: 'datasets-table' },
        { path: '/web/snapshots.php', loader: 'loadDatasetList()', contentId: 'snapshots-table' },
    ];

    for (const page of pages) {
        const app = boot();
        app.window.location.pathname = page.path;
        const datasetPayload = { ok: true, data: [{ name: 'tank/cached', enabled: true }] };
        app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
            savedAt: Date.now(),
            payload: datasetPayload,
        }));
        if (page.loader === 'loadOverview()') {
            app.storage.set(app.run("zssCacheKey('overview')"), JSON.stringify({
                savedAt: Date.now(),
                payload: { ok: true, data: { last_snapshot_dataset: 'tank/cached', last_snapshot_at: 'cached-time' } },
            }));
        }
        app.setFetch(async () => response({ ok: false, error: { message: 'offline' } }));
        await app.run(page.loader);
        const content = app.getElement(page.contentId).innerHTML;
        assert.match(content, /tank\/cached/, `${page.path} initially paints cached content`);
        assert.match(app.staleBanner().innerHTML, /Showing cached data/);

        for (const key of ['datasets', 'overview']) {
            const fullKey = app.run(`zssCacheKey('${key}')`);
            const stored = app.storage.get(fullKey);
            if (stored) {
                const entry = JSON.parse(stored);
                entry.savedAt = Date.now() - 301000;
                app.storage.set(fullKey, JSON.stringify(entry));
            }
        }
        const banner = app.staleBanner();
        await banner.listeners.click[0]({ target: banner.querySelector('button[data-zss-stale-retry]') });

        assert.equal(app.getElement(page.contentId).innerHTML, content, `${page.path} retry keeps the already displayed cache`);
        assert.match(app.staleBanner().innerHTML, /Showing cached data/, `${page.path} keeps its retry banner`);
    }
});

test('stale snapshot retry follows the active dataset and clears only its successful key', { timeout: 10000 }, async () => {
    const app = boot();
    app.window.location.pathname = '/web/snapshots.php';
    for (const dataset of ['tank/a', 'tank/b']) {
        app.storage.set(app.run(`zssCacheKey('snapshots:${dataset}')`), JSON.stringify({
            savedAt: Date.now(),
            payload: { ok: true, data: [] },
        }));
    }
    app.run("activeDataset = 'tank/a'");
    app.setFetch(async (url) => String(url).includes('tank/a')
        ? response({ ok: false, error: { message: 'offline' } })
        : response({ ok: true, data: [] }));
    app.run("zssMarkStale('snapshots:tank/a'); zssMarkStale('snapshots:tank/b')");
    app.run("activeDataset = 'tank/a'; zssSetActiveSnapshotCacheKey('snapshots:tank/a')");
    app.run("zssMarkStale('datasets')");
    assert.equal(app.run('activeDataset'), 'tank/a');
    assert.equal(app.run('zssActiveSnapshotCacheKey'), 'snapshots:tank/a');
    assert.equal(app.run("zssStaleKeys.has('snapshots:tank/a')"), true);
    assert.equal(app.run("zssStaleKeys.has('snapshots:tank/b')"), true);
    assert.equal(app.run("zssStaleKeys.has('datasets')"), true);

    let attempts = 0;
    app.setFetch(async (url) => {
        attempts++;
        assert.match(String(url), /tank\/a/);
        return response({ ok: true, data: [] });
    });
    const banner = app.staleBanner();
    await banner.listeners.click[0]({ target: banner.querySelector('button[data-zss-stale-retry]') });
    assert.equal(attempts, 1);
    assert.equal(app.run("zssStaleKeys.has('snapshots:tank/a')"), true);
    assert.equal(app.run("zssStaleKeys.has('snapshots:tank/b')"), true);
    assert.equal(app.run("zssStaleKeys.has('datasets')"), true, 'a detail-page retry leaves the datasets payload for its own loader');
});

test('snapshots list retry refreshes only the stale datasets payload', { timeout: 10000 }, async () => {
    const app = boot();
    app.window.location.pathname = '/web/snapshots.php';
    app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [{ name: 'tank/cached' }] },
    }));
    let attempts = 0;
    app.setFetch(async () => {
        attempts++;
        return response(attempts === 1
            ? { ok: false, error: { message: 'offline' } }
            : { ok: true, data: [{ name: 'tank/fresh' }] });
    });

    await app.run('loadDatasetList()');
    assert.equal(app.run("zssStaleKeys.has('datasets')"), true);
    assert.match(app.staleBanner().innerHTML, /Showing cached data/);

    const banner = app.staleBanner();
    await banner.listeners.click[0]({ target: banner.querySelector('button[data-zss-stale-retry]') });

    assert.equal(attempts, 2, 'retry reloads the list payload once');
    assert.equal(app.run("zssStaleKeys.has('datasets')"), false);
    assert.match(app.getElement('snapshots-table').innerHTML, /tank\/fresh/);
});

test('datasets page retry refreshes its stale shared payload and clears the marker', { timeout: 10000 }, async () => {
    const app = boot();
    app.window.location.pathname = '/web/datasets.php';
    const tree = app.getElement('zss-resource-tree');
    app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [{ name: 'tank/cached' }] },
    }));
    const cachedPayload = { ok: true, data: [{ name: 'tank/cached' }] };
    app.context.cachedTreePayload = cachedPayload;
    await app.run('loadResourceTree(globalThis.cachedTreePayload)');

    app.run(`
        globalThis.treeRefreshPayloads = [];
        globalThis.originalRefreshResourceTree = refreshResourceTree;
        refreshResourceTree = async (payload) => {
            treeRefreshPayloads.push(payload);
            return originalRefreshResourceTree(payload);
        };
    `);

    let attempts = 0;
    const requestUrls = [];
    const freshPayload = { ok: true, data: [{ name: 'tank/fresh' }] };
    app.setFetch(async (url) => {
        attempts++;
        requestUrls.push(String(url));
        return response(attempts === 1
            ? { ok: false, error: { message: 'offline' } }
            : attempts === 2
                ? { ok: false, error: { message: 'still offline' } }
                : freshPayload);
    });
    await app.run('loadDatasets()');
    const banner = app.staleBanner();
    await banner.listeners.click[0]({ target: banner.querySelector('button[data-zss-stale-retry]') });
    assert.equal(attempts, 2);
    assert.equal(app.context.treeRefreshPayloads.length, 0, 'a failed retry leaves the sidebar alone');
    assert.equal(app.run("zssStaleKeys.has('datasets')"), true, 'a failed retry keeps the stale marker');

    await banner.listeners.click[0]({ target: banner.querySelector('button[data-zss-stale-retry]') });
    assert.equal(attempts, 3);
    assert.equal(app.context.treeRefreshPayloads.length, 1, 'the sidebar refresh runs once');
    assert.equal(app.context.treeRefreshPayloads[0], freshPayload, 'the sidebar receives the successful retry payload');
    assert.deepEqual(requestUrls, ['/api/datasets.php', '/api/datasets.php', '/api/datasets.php'], 'sidebar refresh reuses each retry response');
    assert.match(tree.innerHTML, /zss-tree-name">fresh/, 'the sidebar is updated from the retry response');
    assert.equal(app.run("zssStaleKeys.has('datasets')"), false);
    assert.match(app.getElement('datasets-table').innerHTML, /tank\/fresh/);
});

test('ordinary datasets loading does not invoke the stale-retry sidebar refresh', { timeout: 10000 }, async () => {
    const app = boot();
    app.window.location.pathname = '/web/datasets.php';
    const tree = app.getElement('zss-resource-tree');
    app.run(`
        globalThis.treeRefreshPayloads = [];
        globalThis.originalRefreshResourceTree = refreshResourceTree;
        refreshResourceTree = async (payload) => {
            treeRefreshPayloads.push(payload);
            return originalRefreshResourceTree(payload);
        };
    `);
    let attempts = 0;
    const freshPayload = { ok: true, data: [{ name: 'tank/initial' }] };
    app.setFetch(async () => {
        attempts++;
        return response(freshPayload);
    });

    const ordinaryLoad = app.run('loadDatasets()');
    const treeLoad = app.run('loadResourceTree()');
    await Promise.all([ordinaryLoad, treeLoad]);

    assert.equal(attempts, 1, 'the page and initial sidebar share one datasets request');
    assert.equal(app.context.treeRefreshPayloads.length, 0, 'ordinary loading does not trigger a second sidebar pass');
    assert.match(tree.innerHTML, /zss-tree-name">initial/, 'the normal tree load still renders the shared response');
});

test('overview retry calls its data loader without replaying DOM initialization', { timeout: 10000 }, async () => {
    const app = boot();
    app.window.location.pathname = '/web/index.php';
    app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [{ name: 'tank/cached' }] },
    }));
    let attempts = 0;
    app.setFetch(async () => {
        attempts++;
        return response(attempts === 1
            ? { ok: false, error: { message: 'offline' } }
            : { ok: true, data: [] });
    });
    await app.run('loadOverview()');
    const banner = app.staleBanner();
    await banner.listeners.click[0]({ target: banner.querySelector('button[data-zss-stale-retry]') });
    assert.equal(attempts, 2);
    assert.equal(app.run("zssStaleKeys.has('datasets')"), false);
});

test('space chart renders truthful stacked source proportions and bilingual text values', () => {
    const app = boot();
    app.getElement('space-list');
    app.getElement('space-total');
    app.getElement('space-total-value');
    app.getElement('space-total-status');
    app.run(`renderSpaceUsage([
        { name: 'tank/a', snapshot_used_bytes: 1000, snapshot_space: {
            status: 'ok', total_bytes: 1000,
            exclusive_bytes: { autosnap: 100, plugin_manual: 200, external: 300 }, shared_bytes: 400
        } },
        { name: 'tank/zero', snapshot_used_bytes: 0, snapshot_space: {
            status: 'ok', total_bytes: 0,
            exclusive_bytes: { autosnap: 0, plugin_manual: 0, external: 0 }, shared_bytes: 0
        } },
        { name: 'tank/b', snapshot_used_bytes: 250, snapshot_space: {
            status: 'unknown', total_bytes: 250,
            exclusive_bytes: { autosnap: null, plugin_manual: null, external: null }, shared_bytes: null
        } },
        { name: 'tank/c', snapshot_used_bytes: null, snapshot_space: {
            status: 'unknown', total_bytes: null,
            exclusive_bytes: { autosnap: null, plugin_manual: null, external: null }, shared_bytes: null
        } },
        { name: 'tank/d', snapshot_used_bytes: 20, snapshot_space: {
            status: 'inconsistent', total_bytes: 20,
            exclusive_bytes: { autosnap: 30, plugin_manual: 0, external: 0 }, shared_bytes: null
        } }
    ])`);
    const markup = app.getElement('space-list').innerHTML;
    assert.ok(markup.includes('tank/a'), `space markup: ${markup}`);
    assert.match(markup, /data-source="autosnap"[^>]*style="--zss-segment-width:10%"/);
    assert.match(markup, /data-source="plugin_manual"[^>]*style="--zss-segment-width:20%"/);
    assert.match(markup, /data-source="external"[^>]*style="--zss-segment-width:30%"/);
    assert.match(markup, /data-source="shared"[^>]*style="--zss-segment-width:40%"/);
    assert.match(markup, /Auto: 100 B/);
    assert.match(markup, /Auto: 0 B/);
    assert.match(markup, /Auto: —/);
    assert.match(markup, /Shared or unattributed: —/);
    assert.match(markup, /0\.1 KiB|100 B/);
    assert.match(markup, /unknown|Unknown/);
    assert.match(markup, /inconsistent|Inconsistent/);
    assert.doesNotMatch(markup, /zss-bar-fill (?:ok|mid|high)/);
    assert.match(markup, /data-space-status="inconsistent"[\s\S]*?data-source="unclassified"/);
    assert.ok(markup.includes('tank/c'));
    assert.ok(markup.includes('tank/zero'));
    assert.equal(app.getElement('space-total-value').textContent.includes('1'), true);
});

test('space chart keeps legacy cached rows and labels partial aggregate explicitly', () => {
    const app = boot();
    app.getElement('space-list');
    app.getElement('space-total');
    app.getElement('space-total-value');
    app.getElement('space-total-status');
    app.run(`renderSpaceUsage([
        { name: 'tank/legacy', snapshot_used_bytes: 512 }
    ])`);
    assert.ok(app.getElement('space-list').innerHTML.includes('tank/legacy'), `legacy markup: ${app.getElement('space-list').innerHTML}`);
    app.run(`renderSpaceUsage([
        { name: 'tank/known', snapshot_used_bytes: 512 },
        { name: 'tank/unknown', snapshot_used_bytes: null, snapshot_space: { status: 'unknown', total_bytes: null } }
    ])`);
    assert.match(app.getElement('space-total-status').textContent, /Partial|部分/);
});

test('old browser cache entries are ignored after the dataset payload contract changes', () => {
    const app = boot();
    app.storage.set('zss_data_v1_datasets', JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [{ name: 'tank/old', snapshot_used_bytes: 123 }] },
    }));
    assert.equal(app.run("zssCacheKey('datasets')"), 'zss_data_v2_datasets');
    assert.equal(app.run("zssCacheGet('datasets')"), null);
});

test('overview metric uses known aggregate bytes and labels partial totals without coercing null to zero', () => {
    const app = boot();
    for (const id of [
        'dataset-count', 'enabled-count', 'snapshot-count', 'readonly-count',
        'snapshot-used-bytes', 'snapshot-used-status', 'last-snapshot', 'last-dataset',
    ]) {
        app.getElement(id);
    }
    app.run(`renderOverview({ data: {
        snapshot_count: 0, readonly_snapshot_count: 0, snapshot_used_bytes: null,
        snapshot_space: { status: 'partial', known_bytes: 12884901888, unknown_datasets: 1 }
    } })`);
    assert.equal(app.getElement('snapshot-used-bytes').textContent, '12.0 GiB');
    assert.match(app.getElement('snapshot-used-status').textContent, /Partial: 1/);
});

test('stale A failure does not label verified B stale, while metadata staleness remains relevant', { timeout: 10000 }, async () => {
    const app = boot();
    app.storage.set(app.run("zssCacheKey('snapshots:A')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [] },
    }));
    app.setFetch(async (url) => String(url).includes('name=A')
        ? response({ ok: false, error: { message: 'A offline' } })
        : response({ ok: true, data: [] }));
    await app.run("loadSnapshots('A')");
    app.run("activeDataset = 'A'");
    let banner = app.staleBanner();
    assert.match(banner.innerHTML, /Showing cached data/);
    await app.run("switchSnapshotDataset('B')");
    banner = app.staleBanner();
    assert.equal(app.run("zssStaleKeys.has('snapshots:A')"), true);
    assert.equal(banner.innerHTML, '');

    app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [] },
    }));
    app.setFetch(async () => response({ ok: false, error: { message: 'metadata offline' } }));
    await app.run('loadDatasets()');
    assert.match(app.staleBanner().innerHTML, /Showing cached data/);
});

test('late stale A failure does not label verified B stale, while metadata staleness remains relevant', { timeout: 10000 }, async () => {
    const app = boot();
    app.storage.set(app.run("zssCacheKey('snapshots:A')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [{ name: 'tank/a', snapshot: 'cached-a' }] },
    }));
    const a = deferred();
    const b = deferred();
    app.setFetch((url) => String(url).includes('name=A') ? a.promise : b.promise);

    const loadA = app.run("loadSnapshots('A')");
    const loadB = app.run("loadSnapshots('B')");
    b.resolve(response({ ok: true, data: [] }));
    await loadB;
    a.resolve(response({ ok: false, error: { message: 'A offline' } }));
    await loadA;
    assert.equal(app.staleBanner().innerHTML, '');

    app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [{ name: 'tank/a' }] },
    }));
    app.setFetch(async () => response({ ok: false, error: { message: 'metadata offline' } }));
    await app.run('loadDatasets()');
    assert.match(app.staleBanner().innerHTML, /Showing cached data/);
});

test('synthetic button aria-disabled removal preserves native disabled state', { timeout: 10000 }, async () => {
    const container = new Element();
    container.innerHTML = '<button disabled aria-disabled="true">Action</button>';
    const [button] = container.querySelectorAll('button');

    button.removeAttribute('aria-disabled');

    assert.equal(button.disabled, true);
    assert.equal(button.getAttribute('disabled'), '');
    assert.equal(button.getAttribute('aria-disabled'), null);

    button.removeAttribute('disabled');
    assert.equal(button.disabled, false);
    assert.doesNotMatch(container.innerHTML, /\sdisabled(?:\s|>)/);
});

test('overview and tree share one datasets response and persist both cache payloads', { timeout: 10000 }, async () => {
    const app = boot();
    const calls = [];
    let resolveDatasets;
    app.setFetch((url) => {
        calls.push(url);
        if (url.includes('/api/datasets.php')) {
            return new Promise((resolve) => { resolveDatasets = resolve; });
        }
        if (url.includes('/api/overview.php')) {
            return response({ ok: true, data: {} });
        }
        return response({ ok: true, data: [] });
    });

    // Start the same shared request a tree consumer uses, then boot overview.
    const treeRequest = app.run('fetchDatasetsShared()');
    app.fireDocument('DOMContentLoaded');
    await flushMicrotasks();
    assert.equal(calls.filter((url) => url.includes('/api/datasets.php')).length, 1);
    assert.equal(calls.filter((url) => url.includes('/api/overview.php')).length, 0);

    const payload = {
        ok: true,
        data: [{ name: 'tank/a', enabled: true, frequency: 'weekly', time: '03:45', day: 5 }],
        meta: { overview: { dataset_count: 1, enabled_count: 1, snapshot_count: 3, readonly_snapshot_count: 1 } },
    };
    resolveDatasets(response(payload));
    await treeRequest;
    await flushMicrotasks();
    const cachedDatasets = JSON.parse(app.storage.get(app.run("zssCacheKey('datasets')")));
    const cachedOverview = JSON.parse(app.storage.get(app.run("zssCacheKey('overview')")));
    assert.deepEqual(cachedDatasets.payload.data, payload.data);
    assert.deepEqual(cachedOverview.payload.data, payload.meta.overview);
});

function logsPayload(level, message = `${level} log`) {
    return {
        ok: true,
        data: {
            logs: [{ timestamp: '2026-11-18 12:00:00', level, message }],
            status: {
                path: `/var/log/${level.toLowerCase()}.log`,
                exists: true,
                readable: true,
            },
            read_error: null,
        },
    };
}

test('latest log filter response owns the table and inventory after out-of-order resolution', { timeout: 10000 }, async () => {
    const app = boot();
    const info = deferred();
    const error = deferred();
    app.setFetch((url) => {
        if (url.includes('level=INFO')) return info.promise;
        if (url.includes('level=ERROR')) return error.promise;
        throw new Error(`Unexpected logs URL: ${url}`);
    });

    app.getElement('log-level').value = 'INFO';
    const infoLoad = app.run('loadLogs()');
    app.getElement('log-level').value = 'ERROR';
    const errorLoad = app.run('loadLogs()');

    error.resolve(response(logsPayload('ERROR')));
    await errorLoad;
    info.resolve(response(logsPayload('INFO')));
    await infoLoad;

    assert.match(app.getElement('logs-table').innerHTML, /ERROR log/);
    assert.doesNotMatch(app.getElement('logs-table').innerHTML, /INFO log/);
    assert.match(app.getElement('logs-inventory-count').textContent, /\/var\/log\/error\.log/);
    assert.match(app.getElement('log-status').innerHTML, /\/var\/log\/error\.log/);
});

test('clear-triggered log reload wins over a delayed pre-clear filter response', { timeout: 10000 }, async () => {
    const app = boot();
    const preClear = deferred();
    const reload = deferred();
    let readCount = 0;
    app.setFetch((url) => {
        if (!url.includes('api/logs.php')) throw new Error(`Unexpected URL: ${url}`);
        readCount += 1;
        return readCount === 1 ? preClear.promise : reload.promise;
    });
    app.getElement('log-level').value = 'WARN';
    const oldLoad = app.run('loadLogs()');
    app.run('zssConfirmAction = async () => true; postJson = async () => ({ ok: true })');

    await app.run('clearLogs()');
    assert.equal(readCount, 2, 'successful clear should start its log reload');
    reload.resolve(response(logsPayload('INFO', 'post-clear log')));
    await flushMicrotasks();
    preClear.resolve(response(logsPayload('WARN', 'pre-clear log')));
    await oldLoad;
    await flushMicrotasks();

    assert.match(app.getElement('logs-table').innerHTML, /post-clear log/);
    assert.doesNotMatch(app.getElement('logs-table').innerHTML, /pre-clear log/);
    assert.match(app.getElement('logs-inventory-count').textContent, /\/var\/log\/info\.log/);
});

test('aborted log request does not show a failure table message or toast', { timeout: 10000 }, async () => {
    const app = boot();
    const abortError = new Error('request superseded');
    abortError.name = 'AbortError';
    app.setFetch(() => Promise.reject(abortError));

    await assert.doesNotReject(app.run('loadLogs()'));
    assert.doesNotMatch(app.getElement('logs-table').innerHTML, /Load failed|request superseded/);
    assert.equal(app.toasts.length, 0);
});

test('overview renders ring graphic and text and paints cached sections independently', { timeout: 10000 }, async () => {
    const app = boot();
    app.getElement('protection-ring').style = { setProperty(name, value) { this[name] = value; } };
    app.getElement('protection-ring-value').textContent = '';
    app.run("renderProtectionRing({ snapshot_count: 100, readonly_snapshot_count: 62 })");
    assert.equal(app.getElement('protection-ring-value').textContent, '62%');
    assert.match(app.getElement('protection-ring').style.background, /62%/);
    const ring = app.getElement('protection-ring');
    const overview = { ok: true, data: { snapshot_count: 100, readonly_snapshot_count: 84 } };
    const datasets = { ok: true, data: [{ name: 'tank/cached', enabled: true, frequency: 'daily' }] };
    for (const [key, payload] of [['overview', overview], ['datasets', datasets]]) {
        app.storage.set(app.run(`zssCacheKey('${key}')`), JSON.stringify({
            savedAt: Date.now(),
            payload,
        }));
    }
    const requests = [deferred(), deferred()];
    let requestIndex = 0;
    app.setFetch(() => requests[requestIndex++].promise);
    app.fireDocument('DOMContentLoaded');
    await flushMicrotasks();
    assert.equal(app.getElement('protection-ring-value').textContent, '84%');
    assert.match(ring.style.background, /84%/);
    assert.match(app.getElement('datasets-table').innerHTML, /tank\/cached/);
    requests.forEach((request) => request.resolve(response({ ok: true, data: [] })));
});

test('confirmation dialog focuses its input and traps Tab in both directions', () => {
    const app = boot();
    const opener = app.getElement('open-confirm');
    app.document.activeElement = opener;
    app.run(`globalThis.confirmResult = zssConfirmAction({ inputLabel: 'Type name', inputValue: 'tank/a' })`);
    const overlay = app.document.body.children.at(-1);
    app.timers.splice(0).forEach((callback) => callback());
    const input = overlay.querySelector('[data-zss-confirm-input]');
    assert.equal(app.document.activeElement, input);
});

test('confirmation dialog focuses the confirm button when it has no input', () => {
    const app = boot();
    app.run(`globalThis.confirmResult = zssConfirmAction({ title: 'Confirm' })`);
    const overlay = app.document.body.children.at(-1);
    app.timers.splice(0).forEach((callback) => callback());
    assert.equal(app.document.activeElement, overlay.querySelector('[data-zss-confirm-ok]'));
});

test('danger confirmation dialog focuses the footer cancel button when it has no input', () => {
    const app = boot();
    app.run(`globalThis.confirmResult = zssConfirmAction({ title: 'Delete snapshot', danger: true })`);
    const overlay = app.document.body.children.at(-1);
    app.timers.splice(0).forEach((callback) => callback());
    assert.equal(app.document.activeElement.getAttribute('data-zss-confirm-footer-cancel'), '');
});

test('confirmation dialog traps Tab and Shift+Tab across its controls', () => {
    const app = boot();
    app.run(`globalThis.confirmResult = zssConfirmAction({ title: 'Confirm' })`);
    const overlay = app.document.body.children.at(-1);
    const controls = overlay.querySelectorAll('button, input');
    assert.equal(controls.length, 3);
    app.document.activeElement = controls.at(-1);
    assert.equal(app.fireKey('Tab').defaultPrevented, true);
    assert.equal(app.document.activeElement, controls[0]);
    app.document.activeElement = controls[0];
    assert.equal(app.fireKey('Tab', app.document.activeElement, true).defaultPrevented, true);
    assert.equal(app.document.activeElement, controls.at(-1));
});

test('confirmation Escape resolves once and restores its opener', async () => {
    const app = boot();
    const opener = app.getElement('open-confirm');
    app.document.activeElement = opener;
    app.run(`globalThis.confirmResult = zssConfirmAction({ title: 'Confirm' }); globalThis.confirmCount = 0; confirmResult.then(() => confirmCount++)`);
    app.document.activeElement = app.document.body.children.at(-1).querySelectorAll('button')[0];
    app.fireKey('Escape');
    const overlay = app.document.body.children.at(-1);
    for (const callback of overlay.listeners.click || []) callback({
        target: overlay,
        closest: () => null,
    });
    await flushMicrotasks();
    app.timers.splice(0).forEach((callback) => callback());
    assert.equal(await app.context.confirmResult, false);
    assert.equal(app.context.confirmCount, 1);
    assert.equal(app.document.activeElement, opener);
});

test('write confirmation cancellation restores disabled opener focus without POST', { timeout: 10000 }, async () => {
    const operations = ['clearLogs', 'createDataset', 'createSnapshot'];
    for (const operation of operations) {
        for (const cancelWith of ['Escape', 'click']) {
            const app = boot();
            const opener = app.getElement(`${operation}-button`);
            app.document.activeElement = opener;
            opener.focusAttempts = [];
            const originalFocus = opener.focus.bind(opener);
            opener.focus = () => {
                opener.focusAttempts.push(opener.disabled);
                originalFocus();
            };
            app.context.opener = opener;
            app.run(`
                globalThis.writePostCount = 0;
                postJson = async () => { writePostCount++; return { ok: true }; };
                if (typeof activeDataset !== 'undefined') activeDataset = 'tank/a';
            `);
            if (operation === 'createDataset') {
                app.getElement('new-dataset-parent').value = 'tank';
                app.getElement('new-dataset-child').value = 'child';
            }

            const pending = app.run(`${operation}(globalThis.opener)`);
            assert.equal(opener.disabled, true, `${operation} marks the trigger busy before opening confirmation`);
            assert.equal(app.document.activeElement, app.document.body, 'disabling the focused trigger moves focus to BODY');

            const overlay = app.document.body.children.at(-1);
            if (cancelWith === 'Escape') {
                app.fireKey('Escape');
            } else {
                const cancel = overlay.querySelector('[data-zss-confirm-cancel]');
                for (const callback of overlay.listeners.click || []) callback({
                    target: cancel,
                    closest: (selector) => selector === '[data-zss-confirm-cancel]' ? cancel : null,
                });
            }

            await pending;
            await flushMicrotasks();
            app.timers.splice(0).forEach((callback) => callback());
            assert.equal(app.context.writePostCount, 0, `${operation} ${cancelWith} cancellation must not POST`);
            assert.equal(opener.disabled, false, `${operation} restores the trigger busy state`);
            assert.deepEqual(opener.focusAttempts, [false], 'focus is attempted after the trigger is re-enabled');
            assert.equal(app.document.activeElement, opener, `${operation} ${cancelWith} cancellation restores trigger focus`);
        }
    }
});

test('dataset edit dialog opens focused, traps Tab, and restores focus after Escape or backdrop close', () => {
    const app = boot();
    const modal = app.getElement('edit-modal');
    modal.innerHTML = '<div><input id="config-enabled"><select id="config-frequency"></select><button>Cancel</button><button>Save</button></div>';
    const opener = app.getElement('edit-trigger');
    app.document.activeElement = opener;
    app.run(`currentDatasets = [{name:'tank/a',enabled:true,frequency:'daily',keep:31,retain_days:0}]; openEdit('tank/a')`);
    const controls = modal.querySelectorAll('input, select, button');
    assert.equal(app.document.activeElement.id, 'config-enabled');
});

test('dataset edit dialog traps Tab and Shift+Tab within its controls', () => {
    const app = boot();
    const modal = app.getElement('edit-modal');
    modal.innerHTML = '<div><input id="config-enabled"><select id="config-frequency"></select><button>Cancel</button><button>Save</button></div>';
    app.run(`currentDatasets = [{name:'tank/a',enabled:true,frequency:'daily',keep:31,retain_days:0}]; openEdit('tank/a')`);
    const controls = modal.querySelectorAll('input, select, button');
    app.document.activeElement = controls.at(-1);
    assert.equal(app.fireKey('Tab').defaultPrevented, true);
    assert.equal(app.document.activeElement, controls[0]);
    app.document.activeElement = controls[0];
    assert.equal(app.fireKey('Tab', app.document.activeElement, true).defaultPrevented, true);
    assert.equal(app.document.activeElement, controls.at(-1));
});

test('dataset edit Escape and backdrop close restore focus to the opener', () => {
    const app = boot();
    const modal = app.getElement('edit-modal');
    modal.innerHTML = '<div><input id="config-enabled"><button>Cancel</button></div>';
    const opener = app.getElement('edit-trigger');
    app.document.activeElement = opener;
    app.run(`currentDatasets = [{name:'tank/a',enabled:true,frequency:'daily',keep:31,retain_days:0}]; openEdit('tank/a')`);
    app.document.activeElement = modal.querySelector('input');
    app.fireKey('Escape');
    assert.equal(modal.style.display, 'none');
    assert.equal(app.document.activeElement, opener);
    app.run(`openEdit('tank/a')`);
    app.document.activeElement = modal.querySelector('input');
    app.fireWindow('click', { target: modal });
    assert.equal(modal.style.display, 'none');
    assert.equal(app.document.activeElement, opener);
});

test('snapshot inspector traps Tab while open and releases the trap after close', async () => {
    const app = boot();
    const trigger = app.getElement('inspector-trigger');
    trigger.dataset.inspectorName = 'tank/a';
    app.context.inspectorTrigger = trigger;
    app.document.activeElement = trigger;
    app.setFetch(async () => response({ ok: true, data: [] }));
    await app.run(`window.ZSSSnapshotInspector.open(globalThis.inspectorTrigger, 'tank/a')`);
    const overlay = app.document.body.children.find((element) => element.id === 'zss-snapshot-inspector');
    const controls = overlay.querySelectorAll('button, a[href]');
    assert.equal(app.document.activeElement, overlay.querySelector('.zss-inspector-close'));
    app.document.activeElement = controls.at(-1);
    assert.equal(app.fireKey('Tab').defaultPrevented, true);
    assert.equal(app.document.activeElement, controls[0]);
    app.run('window.ZSSSnapshotInspector.close()');
    const event = app.fireKey('Tab', trigger);
    assert.equal(event.defaultPrevented, false);
    assert.equal(app.document.activeElement, trigger);
});
