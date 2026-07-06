const ZSS_LOCALE = window.ZSS_LOCALE || document.body?.dataset?.locale || 'en';
const ZSS_LOCALE_PREFERENCE = window.ZSS_LOCALE_PREFERENCE || 'auto';
const ZSS_THEME = window.ZSS_THEME || localStorage.getItem('zss_theme') || 'auto';
const ZSS_ACCENT = window.ZSS_ACCENT || localStorage.getItem('zss_accent') || 'blue';

function getEffectiveTheme(theme = window.ZSS_THEME || ZSS_THEME) {
    if (theme === 'dark' || theme === 'light') return theme;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme = window.ZSS_THEME || ZSS_THEME) {
    const effectiveTheme = getEffectiveTheme(theme);
    document.documentElement.dataset.theme = theme;
    document.documentElement.dataset.effectiveTheme = effectiveTheme;
    document.documentElement.style.colorScheme = effectiveTheme;
    document.body.dataset.theme = theme;
    document.body.dataset.effectiveTheme = effectiveTheme;
    window.ZSS_THEME = theme;
    updateThemeControls(theme, effectiveTheme);
    return effectiveTheme;
}

function applyAccent(accent = window.ZSS_ACCENT || ZSS_ACCENT) {
    document.documentElement.dataset.accent = accent;
    document.body.dataset.accent = accent;
    window.ZSS_ACCENT = accent;
    updateAccentControls(accent);
    return accent;
}

function updateThemeControls(theme = window.ZSS_THEME || 'auto', effectiveTheme = getEffectiveTheme(theme)) {
    const select = document.getElementById('global-theme-switcher');
    if (select) select.value = theme;
    const toggleIcon = document.getElementById('theme-toggle-icon');
    if (toggleIcon) {
        toggleIcon.innerHTML = effectiveTheme === 'dark'
            ? '<svg class="zss-svg-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>'
            : '<svg class="zss-svg-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5 7 7 0 1 0 20.5 14.5Z"/></svg>';
    }
}

function updateAccentControls(accent = window.ZSS_ACCENT || 'blue') {
    document.querySelectorAll('[data-accent-choice]').forEach(button => {
        button.classList.toggle('is-active', button.dataset.accentChoice === accent);
    });
}

function handleThemePreferenceChange(theme) {
    localStorage.setItem('zss_theme', theme);
    applyTheme(theme);
}

function toggleThemePreference() {
    const effectiveTheme = getEffectiveTheme(window.ZSS_THEME || 'auto');
    handleThemePreferenceChange(effectiveTheme === 'dark' ? 'light' : 'dark');
}

function saveThemePreference(theme, options = {}) {
    localStorage.setItem('zss_theme', theme);
    const effectiveTheme = applyTheme(theme);
    if (typeof options.onSaved === 'function') options.onSaved(theme, effectiveTheme);
    return effectiveTheme;
}

function saveAccentPreference(accent, options = {}) {
    localStorage.setItem('zss_accent', accent);
    const currentAccent = applyAccent(accent);
    if (typeof options.onSaved === 'function') options.onSaved(currentAccent);
    return currentAccent;
}

document.addEventListener('DOMContentLoaded', function() {
    applyTheme(window.ZSS_THEME || ZSS_THEME);
    applyAccent(window.ZSS_ACCENT || ZSS_ACCENT);
    document.body.classList.add('theme-ready');

    if (window.matchMedia) {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const syncTheme = function() {
            if ((window.ZSS_THEME || 'auto') === 'auto') applyTheme('auto');
        };
        if (typeof media.addEventListener === 'function') media.addEventListener('change', syncTheme);
        else if (typeof media.addListener === 'function') media.addListener(syncTheme);
    }
});

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
    const parsed = new URL(withLang(url), window.location.href);
    Object.entries(payload).forEach(([key, value]) => { if (value !== undefined && value !== null) parsed.searchParams.set(key, String(value)); });
    const response = await fetch(parsed.pathname + parsed.search + parsed.hash, { method: 'GET', credentials: 'include', headers: { 'Accept': 'application/json', 'X-ZSS-Action': '1' } });
    const contentType = response.headers.get('content-type') || '';
    const text = await response.text();
    if (text.trim() === '') {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return { ok: true, data: null };
    }
    if (!contentType.includes('application/json')) throw new Error(`Expected JSON but received: ${text.slice(0, 160)}`);
    const data = JSON.parse(text);
    if (!response.ok || data?.ok === false) throw new Error(data?.error?.message || `HTTP ${response.status}`);
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
