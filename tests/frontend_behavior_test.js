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
];

class Element {
    constructor(id = '') {
        this.id = id;
        this.innerHTML = '';
        this.textContent = '';
        this.hidden = false;
        this.disabled = false;
        this.value = '';
        this.dataset = {};
        this.style = { setProperty() {}, removeProperty() {} };
        this.attributes = new Map();
        this.children = [];
        this.listeners = {};
        this.classList = {
            add() {},
            remove() {},
            toggle() {},
            contains() { return false; },
        };
    }
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
        if (selector === 'button') {
            const button = new Element();
            button.addEventListener = () => {};
            return button;
        }
        return null;
    }
    querySelectorAll(selector) {
        if (!selector.includes('button') || !this.innerHTML) return [];
        const buttons = [];
        for (const match of this.innerHTML.matchAll(/<button\b[^>]*>/g)) {
            const attributes = new Map();
            for (const attr of match[0].matchAll(/([\w-]+)(?:="([^"]*)")?/g)) {
                attributes.set(attr[1], attr[2] ?? '');
            }
            const button = new Element();
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
        return buttons;
    }
    closest() { return null; }
    focus() {}
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

function boot() {
    const elements = new Map();
    const documentListeners = {};
    const windowListeners = {};
    const storage = new Map();
    const frames = [];
    const toasts = [];
    const body = new Element('body');
    body.dataset = { locale: 'en' };
    const document = {
        body,
        title: 'Snapshots',
        documentElement: new Element('html'),
        createElement: () => new Element(),
        getElementById(id) {
            const child = body.children.find((item) => item.id === id);
            if (child) return child;
            return elements.get(id) || null;
        },
        querySelector() { return null; },
        querySelectorAll() { return []; },
        addEventListener(name, callback) { (documentListeners[name] ||= []).push(callback); },
    };
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
    ]) elements.set(id, new Element(id));
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
        location: { href: 'http://localhost/web/index.php', search: '' },
        history: { pushState() {}, replaceState() {} },
        localStorage: storageApi,
        sessionStorage: storageApi,
        matchMedia: () => ({ matches: true, addEventListener() {} }),
        addEventListener(name, callback) { (windowListeners[name] ||= []).push(callback); },
        removeEventListener() {},
        requestAnimationFrame(callback) { frames.push(callback); return frames.length; },
        cancelAnimationFrame() {},
        setTimeout() { return 1; },
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
        vm.runInContext(fs.readFileSync(path.join(root, relative), 'utf8'), context, { filename: relative });
    }
    return {
        context,
        document,
        window,
        elements,
        getElement(id) {
            if (!elements.has(id)) elements.set(id, new Element(id));
            return elements.get(id);
        },
        storage,
        frames,
        toasts,
        fetchCalls,
        fireDocument(name) {
            for (const callback of documentListeners[name] || []) callback();
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
    assert.ok(banner.textContent);
    await app.run('loadDatasets()');
    assert.equal(attempts, 2, 'a failed shared request must allow a later refresh request');
    banner = app.staleBanner();
    assert.equal(banner.textContent, '', `remaining stale keys: ${app.run('[...zssStaleKeys].join(",")')}`);
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
    assert.ok(banner.textContent);
    await app.run("switchSnapshotDataset('B')");
    banner = app.staleBanner();
    assert.equal(app.run("zssStaleKeys.has('snapshots:A')"), true);
    assert.equal(banner.textContent, '');

    app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [] },
    }));
    app.setFetch(async () => response({ ok: false, error: { message: 'metadata offline' } }));
    await app.run('loadDatasets()');
    assert.ok(app.staleBanner().textContent);
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
    assert.equal(app.staleBanner().textContent, '');

    app.storage.set(app.run("zssCacheKey('datasets')"), JSON.stringify({
        savedAt: Date.now(),
        payload: { ok: true, data: [{ name: 'tank/a' }] },
    }));
    app.setFetch(async () => response({ ok: false, error: { message: 'metadata offline' } }));
    await app.run('loadDatasets()');
    assert.ok(app.staleBanner().textContent);
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
