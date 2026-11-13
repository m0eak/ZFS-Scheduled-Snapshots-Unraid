const ZSS_LOCALE = window.ZSS_LOCALE || document.body?.dataset?.locale || 'en';
const ZSS_LOCALE_PREFERENCE = window.ZSS_LOCALE_PREFERENCE || 'auto';

/* ---- Per-browser theme control ----
   Preference lives in localStorage under zss_theme (strict whitelist:
   auto/light/dark; anything else falls back to auto). The anti-flash
   script in shell.php already applied it to <html> before stylesheets
   loaded; this module mirrors it onto <body>, drives the Settings select
   and the topbar toggle, and only re-resolves auto when the OS scheme
   changes (explicit light/dark never follow the OS).
*/
const ZSS_THEME_VALUES = ['auto', 'light', 'dark'];
const ZSS_THEME_ICONS = {
    light: '<svg class="zss-svg-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>',
    dark: '<svg class="zss-svg-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5 7 7 0 1 0 20.5 14.5Z"/></svg>',
};

function getStoredTheme() {
    let stored = null;
    try {
        stored = window.localStorage.getItem('zss_theme');
    } catch (error) {
        stored = null;
    }
    return ZSS_THEME_VALUES.indexOf(stored) !== -1 ? stored : 'auto';
}

function getEffectiveTheme(theme = getStoredTheme()) {
    if (theme === 'light' || theme === 'dark') {
        return theme;
    }
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme, options = {}) {
    const safeTheme = ZSS_THEME_VALUES.indexOf(theme) !== -1 ? theme : 'auto';
    const effective = getEffectiveTheme(safeTheme);

    try {
        window.localStorage.setItem('zss_theme', safeTheme);
    } catch (error) {}

    const root = document.documentElement;
    root.dataset.theme = safeTheme;
    root.dataset.effectiveTheme = effective;
    root.style.colorScheme = effective;

    if (document.body) {
        document.body.dataset.theme = safeTheme;
        document.body.dataset.effectiveTheme = effective;
        document.body.style.colorScheme = effective;
    }

    window.ZSS_THEME = safeTheme;
    window.ZSS_EFFECTIVE_THEME = effective;

    if (typeof options.onApplied === 'function') {
        options.onApplied(safeTheme, effective);
    }

    syncThemeControls(safeTheme, effective);
    return effective;
}

function syncThemeControls(theme, effective) {
    const toggle = document.getElementById('global-theme-toggle');
    if (toggle) {
        const mode = effective === 'dark' ? t('settings.theme.option.dark', 'Dark') : t('settings.theme.option.light', 'Light');
        toggle.setAttribute('aria-label', `${t('settings.theme.toggle', 'Toggle theme')} — ${mode}`);
        toggle.setAttribute('title', `${t('settings.theme.toggle', 'Toggle theme')} — ${mode}`);
    }
    const icon = document.getElementById('global-theme-toggle-icon');
    if (icon) {
        icon.innerHTML = ZSS_THEME_ICONS[effective] || ZSS_THEME_ICONS.light;
    }
    const select = document.getElementById('settings-theme');
    if (select && select.value !== theme) {
        select.value = theme;
    }
    const preview = document.getElementById('effective-theme-preview');
    if (preview) {
        preview.textContent = effective === 'dark'
            ? t('settings.theme.option.dark', 'Dark')
            : t('settings.theme.option.light', 'Light');
    }
}

function cycleThemePreference() {
    const order = ['auto', 'light', 'dark'];
    const current = getStoredTheme();
    const next = order[(order.indexOf(current) + 1) % order.length];
    applyTheme(next, {
        onApplied(theme, effective) {
            const feedback = document.getElementById('settings-theme-feedback');
            if (feedback) {
                feedback.textContent = t('settings.theme.saved', 'Theme preference saved');
            }
        },
    });
}
window.cycleThemePreference = cycleThemePreference;

function handleSettingsThemeChange(theme) {
    applyTheme(theme, {
        onApplied(theme, effective) {
            const feedback = document.getElementById('settings-theme-feedback');
            if (feedback) {
                feedback.textContent = t('settings.theme.saved', 'Theme preference saved');
            }
        },
    });
}
window.handleSettingsThemeChange = handleSettingsThemeChange;

function withLang(url) {
    try {
        const parsed = new URL(url, window.location.href);
        if (ZSS_LOCALE_PREFERENCE !== 'auto' && !parsed.searchParams.get('lang')) {
            parsed.searchParams.set('lang', ZSS_LOCALE);
        }
        return parsed.pathname + parsed.search + parsed.hash;
    } catch (error) {
        if (ZSS_LOCALE_PREFERENCE === 'auto') return url;
        const separator = url.includes('?') ? '&' : '?';
        return `${url}${separator}lang=${encodeURIComponent(ZSS_LOCALE)}`;
    }
}

function setLocale(locale) {
    const url = new URL(window.location.href);
    if (locale === 'auto') {
        document.cookie = 'zss_lang=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
        url.searchParams.delete('lang');
    } else {
        document.cookie = `zss_lang=${encodeURIComponent(locale)}; path=/; max-age=31536000; SameSite=Lax`;
        url.searchParams.set('lang', locale);
    }
    // Drop the resource tree cache before the full-page navigation so
    // translated tree markup and aria-labels never leak across languages.
    clearResourceTreeCache();
    window.location.href = url.toString();
}

function t(key, fallback = null, replace = {}) {
    const value = (window.ZSS_TRANSLATIONS && window.ZSS_TRANSLATIONS[key]) || fallback || key;
    return Object.keys(replace).reduce((text, name) => text.replaceAll(`{${name}}`, replace[name]), value);
}

function escapeHtml(value) {
    return String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function frequencyLabel(value) { return t(`frequency.${value}`, value || '-'); }

function formatTimestamp(timestamp) {
    if (!timestamp) return '-';
    const date = new Date(Number(timestamp) * 1000);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString(ZSS_LOCALE === 'zh-CN' ? 'zh-CN' : 'en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function formatBytes(bytes) {
    const value = Number(bytes);
    if (!Number.isFinite(value) || value < 0) return '-';
    if (value === 0) return '0 B';
    const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
    const index = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
    const scaled = value / (1024 ** index);
    const digits = scaled >= 100 || index === 0 ? 0 : scaled >= 10 ? 1 : 2;
    return `${scaled.toFixed(digits)} ${units[index]}`;
}

async function fetchData(url, options = {}) {
    try {
        const fetchOptions = { headers: { 'Accept': 'application/json' } };
        if (options && options.signal) {
            fetchOptions.signal = options.signal;
        }
        const response = await fetch(withLang(url), fetchOptions);
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Expected JSON but received: ${text.slice(0, 160)}`);
        }
        const data = await response.json();
        if (!response.ok || data?.ok === false) throw new Error(data?.error?.message || `HTTP ${response.status}`);
        return data;
    } catch (error) {
        // Aborted switches are routine (fast dataset hopping); let the caller
        // drop them silently instead of surfacing a fake load failure.
        if (error && error.name === 'AbortError') throw error;
        console.error('Fetch error:', error);
        return { ok: false, error: { message: error.message || t('common.request_failed', 'Request failed') } };
    }
}

/* ---- Cross-page data cache (stale-while-revalidate) ----
   The five pages are separate documents, so an in-memory cache dies on every
   navigation. Successful API payloads are mirrored into sessionStorage so the
   next page can paint the previous data immediately, then replace it when the
   network settles. Only successful payloads are stored; errors are never
   cached. */
const ZSS_DATA_CACHE_PREFIX = 'zss_data_v2_';
const ZSS_DATA_CACHE_TTL_MS = 5 * 60 * 1000;

function zssCacheKey(key) {
    return ZSS_DATA_CACHE_PREFIX + key;
}

function zssCacheGet(key) {
    try {
        const raw = window.sessionStorage.getItem(zssCacheKey(key));
        if (!raw) return null;
        const entry = JSON.parse(raw);
        if (!entry || typeof entry !== 'object') return null;
        if (typeof entry.savedAt !== 'number' || !entry.payload) return null;
        if (Date.now() - entry.savedAt > ZSS_DATA_CACHE_TTL_MS) return null;
        return entry.payload;
    } catch (error) {
        return null;
    }
}

function zssCacheSet(key, payload) {
    try {
        window.sessionStorage.setItem(zssCacheKey(key), JSON.stringify({ savedAt: Date.now(), payload }));
    } catch (error) {}
}

/* Cache invalidation epoch. Every write action clears the payloads it makes
   stale; a request that started before that clear must not write its response
   back afterwards (it may predate the write). zssLoadCached captures the epoch
   before the request and drops both the cache write and the paint when it
   changed in the meantime. */
let zssCacheEpoch = 0;

/* Page scripts that talk to the API outside zssLoadCached (the shared datasets
   request) capture this before their request and drop the response if a write
   invalidated the payloads in the meantime. */
function zssCacheEpochValue() {
    return zssCacheEpoch;
}

window.zssCacheEpochValue = zssCacheEpochValue;

function zssCacheClear(prefix) {
    zssCacheEpoch++;
    try {
        const full = zssCacheKey(prefix || '');
        const doomed = [];
        for (let i = 0; i < window.sessionStorage.length; i++) {
            const key = window.sessionStorage.key(i);
            if (key && key.indexOf(full) === 0) doomed.push(key);
        }
        doomed.forEach(key => window.sessionStorage.removeItem(key));
    } catch (error) {}
}

/* Drops every payload a snapshot or dataset write can invalidate: the snapshot
   timelines, the dataset list (snapshot counts), and the overview aggregates
   (protected counts, space usage, recency). */
function zssInvalidateDataCache() {
    zssCacheClear('snapshots:');
    zssCacheClear('datasets');
    zssCacheClear('overview');
}

window.zssInvalidateDataCache = zssInvalidateDataCache;

/**
 * Stale-while-revalidate loader.
 * - Paints the cached payload first (if any and still within TTL).
 * - Always fetches fresh data; on success paints again and refreshes the cache.
 * - On failure keeps whatever was painted and reports it, so the page can show
 *   a stale notice instead of blanking the workspace.
 * render(payload, { stale }) is called at most twice.
 * Returns { ok, data, error, stale, hadCache, painted }.
 */
async function zssLoadCached(url, cacheKey, render, options = {}) {
    const cached = zssCacheGet(cacheKey);
    let painted = false;
    if (cached && typeof render === 'function') {
        try {
            render(cached, { stale: true, cacheKey });
            painted = true;
        } catch (error) {}
    }

    const epochAtStart = zssCacheEpoch;
    try {
        const fresh = await fetchData(url, options.signal ? { signal: options.signal } : {});
        if (zssCacheEpoch !== epochAtStart) {
            // A write invalidated the payloads while this request was in flight.
            // Its response may describe the pre-write state, so drop it rather
            // than repainting stale rows or poisoning the cache.
            return { ok: false, error: null, hadCache: !!cached, painted, invalidated: true };
        }
        if (fresh && fresh.ok) {
            zssCacheSet(cacheKey, fresh);
            zssMarkFresh(cacheKey);
            const changed = !cached || JSON.stringify(cached) !== JSON.stringify(fresh);
            // Always hand the confirmed payload to the page. A stale paint may
            // have rendered read-only controls that only a confirmed response
            // can lift; the page decides whether that means a full rebuild or
            // just unlocking the existing rows.
            if (typeof render === 'function') {
                render(fresh, { stale: false, changed, cacheKey });
            }
            return { ok: true, data: fresh, stale: false, hadCache: !!cached, changed, painted, invalidated: false };
        }
        if (cached) zssMarkStale(cacheKey);
        return { ok: false, error: fresh?.error, hadCache: !!cached, painted, invalidated: false };
    } catch (error) {
        if (error && error.name === 'AbortError') throw error;
        if (cached) zssMarkStale(cacheKey);
        return { ok: false, error: { message: error.message }, hadCache: !!cached, painted, invalidated: false };
    }
}

window.zssCacheGet = zssCacheGet;
window.zssCacheClear = zssCacheClear;

/* Tells the operator that the workspace is still showing the previous payload
   because the refresh failed, instead of silently leaving stale numbers on
   screen. Shown at most once per page load so a burst of failed requests
   cannot stack toasts on top of each other. */
let zssStaleNoticeShown = false;

function zssNotifyStale(error) {
    if (zssStaleNoticeShown) return;
    zssStaleNoticeShown = true;
    zssToast({
        type: 'error',
        title: t('common.stale_data', 'Showing cached data; refresh failed.'),
        message: error?.message || '',
    });
}

window.zssNotifyStale = zssNotifyStale;

/* Persistent staleness marker. The one-shot toast disappears; the banner stays
   in the workspace until the affected payload refreshes successfully, so an
   operator who walks away and comes back still sees that the numbers are
   cached. Keys are tracked per payload, so one recovered half clears only its
   own notice. */
const zssStaleKeys = new Set();
let zssActiveSnapshotCacheKey = null;

function zssRenderStaleBanner() {
    let banner = document.getElementById('zss-stale-banner');
    const hasRelevantStaleKey = [...zssStaleKeys].some(key =>
        !String(key).startsWith('snapshots:') || key === zssActiveSnapshotCacheKey
    );
    if (!hasRelevantStaleKey) {
        if (banner) banner.remove();
        return;
    }
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'zss-stale-banner';
        banner.className = 'zss-stale-banner';
        banner.setAttribute('role', 'status');
        const content = document.querySelector('.zss-content');
        if (content) content.insertBefore(banner, content.firstChild);
        else document.body.appendChild(banner);
    }
    banner.textContent = t('common.stale_data', 'Showing cached data; refresh failed.');
}

function zssSetActiveSnapshotCacheKey(cacheKey) {
    zssActiveSnapshotCacheKey = cacheKey || null;
    zssRenderStaleBanner();
}

function zssMarkStale(cacheKey) {
    zssStaleKeys.add(cacheKey);
    zssRenderStaleBanner();
}

function zssMarkFresh(cacheKey) {
    if (zssStaleKeys.delete(cacheKey)) zssRenderStaleBanner();
}

window.zssMarkStale = zssMarkStale;
window.zssMarkFresh = zssMarkFresh;

async function postJson(url, payload = {}) {
    const token = (typeof window !== 'undefined' && window.ZSS_CSRF) || '';
    if (!token) {
        throw new Error(t('common.csrf_missing', 'CSRF token unavailable; cannot perform write action. Refresh the page and try again.'));
    }

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-ZSS-Action': '1',
            'X-CSRF-Token': token,
        },
        body: JSON.stringify({ ...payload, csrf_token: token }),
    });
    const contentType = response.headers.get('content-type') || '';
    const text = await response.text();

    // A successful write endpoint always returns JSON. An empty or non-JSON
    // response means the request was rejected before reaching the plugin
    // (e.g. Unraid's CSRF guard terminated it), so never report false success.
    if (text.trim() === '' || !contentType.includes('application/json')) {
        throw new Error(
            text.trim() === ''
                ? t('common.request_rejected', 'Request was rejected by the server (CSRF or access check failed).')
                : `Expected JSON but received: ${text.slice(0, 160)}`
        );
    }

    const data = JSON.parse(text);
    if (!response.ok || data?.ok === false) {
        return {
            ok: false,
            error: data?.error || { code: 'HTTP_ERROR', message: `HTTP ${response.status}` },
        };
    }

    return data;
}

function renderTableMessage(tbodyId, message, colspan, className = 'zss-table-message') {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="${className}">${escapeHtml(message)}</td></tr>`;
}

function zssConfirmAction(options = {}) {
    return new Promise(resolve => {
        const overlay = document.createElement('div');
        const hasInput = Object.prototype.hasOwnProperty.call(options, 'inputLabel');
        overlay.className = 'zss-action-modal';
        overlay.innerHTML = `
            <div class="zss-action-dialog" role="dialog" aria-modal="true">
                <div class="zss-action-dialog-header">
                    <h2>${escapeHtml(options.title || t('common.confirm', 'Confirm'))}</h2>
                    <button class="zss-icon-button" type="button" data-zss-confirm-cancel aria-label="${escapeHtml(t('common.close', 'Close'))}">×</button>
                </div>
                <p>${escapeHtml(options.message || '')}</p>
                ${options.detail ? `<div class="zss-action-detail">${escapeHtml(options.detail)}</div>` : ''}
                ${hasInput ? `
                    <label class="zss-action-field">
                        <span>${escapeHtml(options.inputLabel || '')}</span>
                        <input class="zss-input" type="text" data-zss-confirm-input value="${escapeHtml(options.inputValue || '')}" autocomplete="off">
                    </label>
                ` : ''}
                <div class="zss-action-dialog-footer">
                    <button class="zss-btn zss-btn-secondary" type="button" data-zss-confirm-cancel>${escapeHtml(options.cancelText || t('common.cancel', 'Cancel'))}</button>
                    <button class="zss-btn ${options.danger ? 'zss-btn-danger' : 'zss-btn-primary'}" type="button" data-zss-confirm-ok>${escapeHtml(options.confirmText || t('common.confirm', 'Confirm'))}</button>
                </div>
            </div>
        `;

        const close = value => {
            document.removeEventListener('keydown', onKeyDown);
            overlay.classList.remove('is-open');
            window.setTimeout(() => overlay.remove(), 200);
            resolve(value);
        };

        const onKeyDown = event => {
            if (event.key === 'Escape') {
                close(false);
            }
            if (event.key === 'Enter' && hasInput && event.target && event.target.matches('[data-zss-confirm-input]')) {
                close(event.target.value);
            }
        };

        overlay.addEventListener('click', event => {
            if (event.target === overlay || event.target.closest('[data-zss-confirm-cancel]')) {
                close(false);
            }
            if (event.target.closest('[data-zss-confirm-ok]')) {
                const input = overlay.querySelector('[data-zss-confirm-input]');
                close(input ? input.value : true);
            }
        });

        document.body.appendChild(overlay);
        document.addEventListener('keydown', onKeyDown);
        window.requestAnimationFrame(() => overlay.classList.add('is-open'));
        const input = overlay.querySelector('[data-zss-confirm-input]');
        if (input) {
            window.setTimeout(() => input.focus(), 0);
        }
    });
}

function zssToast(options = {}) {
    let root = document.getElementById('zss-toast-root');
    if (!root) {
        root = document.createElement('div');
        root.id = 'zss-toast-root';
        root.className = 'zss-toast-root';
        document.body.appendChild(root);
    }

    const toast = document.createElement('div');
    const type = options.type || 'info';
    toast.className = `zss-toast zss-toast-${type}`;
    toast.innerHTML = `
        <strong>${escapeHtml(options.title || '')}</strong>
        ${options.message ? `<span>${escapeHtml(options.message)}</span>` : ''}
        <button type="button" aria-label="${escapeHtml(t('common.close', 'Close'))}">×</button>
    `;

    // Exit: fade/slide out before removal so the toast does not blink away.
    const dismiss = () => {
        if (!toast.isConnected) return;
        toast.classList.add('is-leaving');
        window.setTimeout(() => toast.remove(), 180);
    };

    toast.querySelector('button').addEventListener('click', dismiss);
    root.appendChild(toast);
    window.setTimeout(dismiss, options.timeout || 3600);
}

function zssSetButtonBusy(button, label) {
    if (!button) {
        return function() {};
    }

    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = `<span class="zss-spinner"></span>${escapeHtml(label || t('common.loading', 'Loading...'))}`;

    return function restoreButton() {
        button.disabled = false;
        button.innerHTML = originalHtml;
    };
}

function zssFlashRow(element) {
    // Timeline rows (.zss-event) are not table rows; fall back so action
    // feedback still flashes after hold/release/delete/rollback.
    const row = element ? (element.closest('tr') || element.closest('.zss-event')) : null;
    if (!row) return;

    row.classList.add('zss-row-flash');
    window.setTimeout(() => row.classList.remove('zss-row-flash'), 900);
}

/* ---- Shared datasets API cache (single request per page lifecycle) ----
   The sidebar resource tree and every page table/selector read the same
   datasets payload; sharing one cached promise keeps a page load to a
   single real network request regardless of how many readers fire.
   Mutations must call invalidateDatasetsCache() so subsequent readers
   refetch fresh data instead of replaying the stale response. */
let datasetsCachePromise = null;

function fetchDatasetsShared() {
    if (!datasetsCachePromise) {
        const epochAtStart = zssCacheEpochValue();
        const request = fetchData('../api/datasets.php');
        datasetsCachePromise = request;
        request.then(data => {
            if (data && data.ok && zssCacheEpochValue() === epochAtStart) {
                zssCacheSet('datasets', data);
                if (data.meta && data.meta.overview) {
                    zssCacheSet('overview', { ok: true, data: data.meta.overview });
                }
            }
            if ((!data || !data.ok) && datasetsCachePromise === request) {
                datasetsCachePromise = null;
            }
        }, () => {
            if (datasetsCachePromise === request) datasetsCachePromise = null;
        });
    }
    return datasetsCachePromise;
}

function invalidateDatasetsCache() {
    datasetsCachePromise = null;
}

/* ---- Sidebar resource tree (dataset navigation, driven by the datasets API) ---- */

function buildDatasetTree(datasets) {
    const nodes = new Map();
    const ensureNode = name => {
        if (nodes.has(name)) return nodes.get(name);
        const node = { name, children: [], ds: null, is_root: true, synthetic: true, snapshot_count: 0, enabled: false };
        nodes.set(name, node);
        return node;
    };

    datasets.forEach(ds => {
        const node = ensureNode(ds.name);
        node.ds = ds;
        node.is_root = !!ds.is_root;
        node.snapshot_count = ds.snapshot_count || 0;
        node.enabled = !!ds.enabled;
        node.synthetic = false;
    });

    const roots = [];
    nodes.forEach(node => {
        const slash = node.name.lastIndexOf('/');
        if (slash === -1) {
            roots.push(node);
            return;
        }
        const parent = ensureNode(node.name.slice(0, slash));
        node.is_root = false;
        parent.children.push(node);
    });

    const pruneEmpty = node => {
        node.children = node.children.filter(child => !(child.synthetic && child.children.length === 0));
        node.children.forEach(pruneEmpty);
    };
    roots.forEach(pruneEmpty);

    return roots.filter(node => !(node.synthetic && node.children.length === 0));
}

const ZSS_TREE_COLLAPSED_KEY = 'zss_tree_collapsed';

function getCollapsedTreeNodes() {
    try {
        const stored = JSON.parse(window.localStorage.getItem(ZSS_TREE_COLLAPSED_KEY) || '[]');
        return new Set(Array.isArray(stored) ? stored.filter(name => typeof name === 'string') : []);
    } catch (error) {
        return new Set();
    }
}

function saveCollapsedTreeNodes(collapsed) {
    try {
        window.localStorage.setItem(ZSS_TREE_COLLAPSED_KEY, JSON.stringify(Array.from(collapsed)));
    } catch (error) {}
}

/* ---- Sidebar resource tree session cache (per-tab, cross-page restore)
   The five pages are separate PHP documents; navigating between them destroys
   #zss-resource-tree and re-renders it from the datasets API. A session cache
   lets the next page restore the previous tree synchronously (no Loading
   flash) and skip innerHTML rebuilds while the dataset payload fingerprint is
   unchanged. The cache is per-tab and per-session, so it never leaks across
   browser profiles or restarts. ---- */
const ZSS_TREE_CACHE_KEY = 'zss_tree_cache';

function resourceTreeLocale() {
    return (document.body && document.body.dataset && document.body.dataset.locale) || ZSS_LOCALE || 'en';
}

function readResourceTreeCache() {
    try {
        const raw = window.sessionStorage.getItem(ZSS_TREE_CACHE_KEY);
        if (!raw) return null;
        const cached = JSON.parse(raw);
        if (!cached || typeof cached !== 'object') return null;
        if (typeof cached.locale !== 'string' || typeof cached.fingerprint !== 'string' || typeof cached.html !== 'string') return null;
        return cached;
    } catch (error) {
        return null;
    }
}

function writeResourceTreeCache(cache) {
    try {
        window.sessionStorage.setItem(ZSS_TREE_CACHE_KEY, JSON.stringify(cache));
    } catch (error) {}
}

function clearResourceTreeCache() {
    try {
        window.sessionStorage.removeItem(ZSS_TREE_CACHE_KEY);
    } catch (error) {}
}

function resourceTreeFingerprint(datasets) {
    return (datasets || [])
        .slice()
        .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')))
        .map(ds => {
            const name = String(ds.name || '');
            const enabled = ds.enabled ? '1' : '0';
            const snapshotCount = Number(ds.snapshot_count) || 0;
            const isRoot = ds.is_root ? '1' : '0';
            return `${name}|${enabled}|${snapshotCount}|${isRoot}`;
        })
        .join(';');
}

function persistResourceTreeCache(container, fingerprint) {
    if (!container.querySelector('.zss-tree-list')) return;
    writeResourceTreeCache({
        locale: resourceTreeLocale(),
        fingerprint,
        html: container.innerHTML,
        scrollTop: container.scrollTop || 0,
    });
}

function syncTreeActiveFromUrl(container, currentDataset) {
    container.querySelectorAll('.zss-tree-link').forEach(link => {
        let matches = false;
        if (currentDataset) {
            try {
                const href = link.getAttribute('href') || '';
                matches = new URL(href, window.location.href).searchParams.get('dataset') === currentDataset;
            } catch (error) {}
        }
        link.classList.toggle('is-active', matches);
    });
}

function persistResourceTreeScroll(container) {
    if (!container.querySelector('.zss-tree-list')) return;
    try {
        const raw = window.sessionStorage.getItem(ZSS_TREE_CACHE_KEY);
        if (!raw) return;
        const cached = JSON.parse(raw);
        if (!cached || typeof cached !== 'object' || typeof cached.html !== 'string') return;
        cached.scrollTop = container.scrollTop || 0;
        cached.html = container.innerHTML;
        window.sessionStorage.setItem(ZSS_TREE_CACHE_KEY, JSON.stringify(cached));
    } catch (error) {}
}

if (typeof window.addEventListener === 'function') {
    window.addEventListener('pagehide', function() {
        const container = document.getElementById('zss-resource-tree');
        if (container) persistResourceTreeScroll(container);
    });
}

function treeNodeContainsDataset(node, datasetName) {
    return node.name === datasetName || node.children.some(child => treeNodeContainsDataset(child, datasetName));
}

function renderTreeNodes(nodes, currentDataset, collapsedNodes = getCollapsedTreeNodes(), depth = 0) {
    const items = nodes
        .slice()
        .sort((a, b) => a.name.toLowerCase().localeCompare(b.name.toLowerCase()))
        .map(node => {
            const isActive = node.name === currentDataset;
            const isRoot = depth === 0 && node.is_root;
            const hasChildren = node.children.length > 0;
            const isCollapsed = hasChildren && collapsedNodes.has(node.name) && !treeNodeContainsDataset(node, currentDataset);
            const bulletClass = isRoot ? 'is-pool' : (node.enabled ? '' : 'is-disabled');
            const label = node.name.split('/').pop();
            const count = node.snapshot_count > 0 ? String(node.snapshot_count) : '';
            const childrenHtml = hasChildren ? `<ul>${renderTreeNodes(node.children, currentDataset, collapsedNodes, depth + 1)}</ul>` : '';
            const inner = `
                <span class="zss-tree-bullet ${bulletClass}"></span>
                <span class="zss-tree-name">${escapeHtml(label)}</span>
                ${count ? `<span class="zss-tree-count">${escapeHtml(count)}</span>` : ''}
            `;
            const linkClass = `zss-tree-link${isActive ? ' is-active' : ''}${isRoot ? ' is-root' : ''}`;
            const link = node.ds
                ? `<a class="${linkClass}" href="${escapeHtml(withLang(`snapshots.php?dataset=${encodeURIComponent(node.name)}`))}">${inner}</a>`
                : `<span class="${linkClass}">${inner}</span>`;
            const toggle = hasChildren
                ? `<button class="zss-tree-toggle" type="button" data-tree-name="${escapeHtml(node.name)}" aria-expanded="${isCollapsed ? 'false' : 'true'}" aria-label="${escapeHtml(isCollapsed ? t('tree.expand', 'Expand') : t('tree.collapse', 'Collapse'))}"><span aria-hidden="true"></span></button>`
                : '<span class="zss-tree-toggle-placeholder" aria-hidden="true"></span>';
            return `<li class="zss-tree-item${isCollapsed ? ' is-collapsed' : ''}"><div class="zss-tree-row">${toggle}${link}</div>${childrenHtml}</li>`;
        })
        .join('');
    return `<ul class="zss-tree-list">${items}</ul>`;
}

function bindResourceTree(container) {
    if (container.dataset.zssTreeBound) return;
    container.dataset.zssTreeBound = '1';
    container.addEventListener('click', event => {
        const toggle = event.target.closest('.zss-tree-toggle');
        if (!toggle || !container.contains(toggle)) return;

        const item = toggle.closest('.zss-tree-item');
        const name = toggle.dataset.treeName || '';
        if (!item || !name) return;

        const collapsed = getCollapsedTreeNodes();
        const willCollapse = !item.classList.contains('is-collapsed');
        item.classList.toggle('is-collapsed', willCollapse);
        toggle.setAttribute('aria-expanded', willCollapse ? 'false' : 'true');
        toggle.setAttribute('aria-label', willCollapse ? t('tree.expand', 'Expand') : t('tree.collapse', 'Collapse'));
        if (willCollapse) collapsed.add(name);
        else collapsed.delete(name);
        saveCollapsedTreeNodes(collapsed);
    });
}

async function loadResourceTree(preloadedData) {
    const container = document.getElementById('zss-resource-tree');
    if (!container) return;

    const data = preloadedData || await fetchDatasetsShared();
    if (!data || !data.ok) {
        // A synchronous session-cache restore may already have painted the
        // tree; a later API failure must not blank it into an error state.
        if (!container.querySelector('.zss-tree-list')) {
            container.innerHTML = `<div class="zss-tree-message" role="alert">${escapeHtml(t('tree.error', 'Failed to load datasets'))}</div>`;
        }
        return;
    }

    const datasets = data.data || [];
    if (datasets.length === 0) {
        container.innerHTML = `<div class="zss-tree-message">${escapeHtml(t('datasets.empty', 'No datasets'))}</div>`;
        clearResourceTreeCache();
        return;
    }

    const fingerprint = resourceTreeFingerprint(datasets);
    const currentDataset = new URLSearchParams(window.location.search).get('dataset') || '';
    const restoredList = container.querySelector('.zss-tree-list');
    const cached = readResourceTreeCache();

    // Same locale + same dataset payload: the restored tree is still current,
    // so sync the active highlight and refresh the cached scroll position
    // instead of recreating the DOM (which would drop collapse/scroll state).
    if (restoredList && cached && cached.fingerprint === fingerprint && cached.locale === resourceTreeLocale()) {
        syncTreeActiveFromUrl(container, currentDataset);
        bindResourceTree(container);
        persistResourceTreeCache(container, fingerprint);
        return;
    }

    const tree = buildDatasetTree(datasets);
    container.innerHTML = renderTreeNodes(tree, currentDataset);
    bindResourceTree(container);
    persistResourceTreeCache(container, fingerprint);
}

function refreshResourceTree(preloadedData) {
    return loadResourceTree(preloadedData);
}
window.refreshResourceTree = refreshResourceTree;

document.addEventListener('DOMContentLoaded', function() {
    // Mirror the anti-flash html attributes onto the body element so the
    // .zss-next[data-effective-theme=...] overrides apply even if the user
    // never interacts with the theme controls on this page.
    applyTheme(getStoredTheme());

    if (window.matchMedia) {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onSystemThemeChange = function() {
            // Only the auto preference follows the OS scheme. Explicit
            // light/dark choices must stay pinned regardless of system changes.
            if (getStoredTheme() === 'auto') {
                applyTheme('auto');
            }
        };
        if (typeof media.addEventListener === 'function') {
            media.addEventListener('change', onSystemThemeChange);
        } else if (typeof media.addListener === 'function') {
            media.addListener(onSystemThemeChange);
        }
    }

    loadResourceTree();
});
