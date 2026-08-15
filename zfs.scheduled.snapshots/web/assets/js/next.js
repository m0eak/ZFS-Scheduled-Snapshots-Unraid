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

async function fetchData(url) {
    try {
        const response = await fetch(withLang(url), { headers: { 'Accept': 'application/json' } });
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Expected JSON but received: ${text.slice(0, 160)}`);
        }
        const data = await response.json();
        if (!response.ok || data?.ok === false) throw new Error(data?.error?.message || `HTTP ${response.status}`);
        return data;
    } catch (error) {
        console.error('Fetch error:', error);
        return { ok: false, error: { message: error.message || t('common.request_failed', 'Request failed') } };
    }
}

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
            window.setTimeout(() => overlay.remove(), 140);
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

    toast.querySelector('button').addEventListener('click', () => toast.remove());
    root.appendChild(toast);
    window.setTimeout(() => toast.remove(), options.timeout || 3600);
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
    const row = element ? element.closest('tr') : null;
    if (!row) return;

    row.classList.add('zss-row-flash');
    window.setTimeout(() => row.classList.remove('zss-row-flash'), 900);
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

function renderTreeNodes(nodes, currentDataset, depth = 0) {
    const items = nodes
        .slice()
        .sort((a, b) => a.name.toLowerCase().localeCompare(b.name.toLowerCase()))
        .map(node => {
            const isActive = node.name === currentDataset;
            const isRoot = depth === 0 && node.is_root;
            const bulletClass = isRoot ? 'is-pool' : (node.enabled ? '' : 'is-disabled');
            const label = node.name.split('/').pop();
            const count = node.snapshot_count > 0 ? String(node.snapshot_count) : '';
            const childrenHtml = node.children.length ? `<ul>${renderTreeNodes(node.children, currentDataset, depth + 1)}</ul>` : '';
            const inner = `
                <span class="zss-tree-bullet ${bulletClass}"></span>
                <span class="zss-tree-name">${escapeHtml(label)}</span>
                ${count ? `<span class="zss-tree-count">${escapeHtml(count)}</span>` : ''}
            `;
            const linkClass = `zss-tree-link${isActive ? ' is-active' : ''}${isRoot ? ' is-root' : ''}`;
            const link = node.ds
                ? `<a class="${linkClass}" href="${escapeHtml(withLang(`snapshots.php?dataset=${encodeURIComponent(node.name)}`))}">${inner}</a>`
                : `<span class="${linkClass}">${inner}</span>`;
            return `<li class="zss-tree-item">${link}${childrenHtml}</li>`;
        })
        .join('');
    return `<ul class="zss-tree-list">${items}</ul>`;
}

async function loadResourceTree(preloadedData) {
    const container = document.getElementById('zss-resource-tree');
    if (!container) return;

    const data = preloadedData || await fetchData('../api/datasets.php');
    if (!data || !data.ok) {
        container.innerHTML = `<div class="zss-tree-message" role="alert">${escapeHtml(t('tree.error', 'Failed to load datasets'))}</div>`;
        return;
    }

    const datasets = data.data || [];
    if (datasets.length === 0) {
        container.innerHTML = `<div class="zss-tree-message">${escapeHtml(t('datasets.empty', 'No datasets'))}</div>`;
        return;
    }

    const currentDataset = new URLSearchParams(window.location.search).get('dataset') || '';
    const tree = buildDatasetTree(datasets);
    container.innerHTML = renderTreeNodes(tree, currentDataset);
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
