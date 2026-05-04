// 简单的工具函数
const ZSS_LOCALE = window.ZSS_LOCALE || document.body?.dataset?.locale || 'en';
const ZSS_THEME = window.ZSS_THEME || localStorage.getItem('zss_theme') || 'auto';

function getEffectiveTheme(theme = ZSS_THEME) {
    if (theme === 'dark' || theme === 'light') {
        return theme;
    }

    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme = ZSS_THEME) {
    const effectiveTheme = getEffectiveTheme(theme);
    document.body.dataset.theme = theme;
    document.body.dataset.effectiveTheme = effectiveTheme;
    window.ZSS_THEME = theme;
    return effectiveTheme;
}

document.addEventListener('DOMContentLoaded', function() {
    applyTheme(ZSS_THEME);
    console.log('ZFS Scheduled Snapshots WebUI loaded');

    if (window.matchMedia) {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const syncTheme = function() {
            if ((window.ZSS_THEME || 'auto') === 'auto') {
                applyTheme('auto');
            }
        };

        if (typeof media.addEventListener === 'function') {
            media.addEventListener('change', syncTheme);
        } else if (typeof media.addListener === 'function') {
            media.addListener(syncTheme);
        }
    }
});

function withLang(url) {
    try {
        const parsed = new URL(url, window.location.href);
        if (!parsed.searchParams.get('lang')) {
            parsed.searchParams.set('lang', ZSS_LOCALE);
        }
        return parsed.pathname + parsed.search + parsed.hash;
    } catch (error) {
        const separator = url.includes('?') ? '&' : '?';
        return `${url}${separator}lang=${encodeURIComponent(ZSS_LOCALE)}`;
    }
}

function setLocale(locale, options = {}) {
    document.cookie = `zss_lang=${encodeURIComponent(locale)}; path=/; max-age=31536000; SameSite=Lax`;

    if (typeof options.onSaved === 'function') {
        options.onSaved(locale);
    }

    const url = new URL(window.location.href);
    url.searchParams.set('lang', locale);
    window.location.href = url.toString();
}

function saveThemePreference(theme, options = {}) {
    localStorage.setItem('zss_theme', theme);
    const effectiveTheme = applyTheme(theme);

    if (typeof options.onSaved === 'function') {
        options.onSaved(theme, effectiveTheme);
    }

    return effectiveTheme;
}

function t(key, fallback = null, replace = {}) {
    const value = (window.ZSS_TRANSLATIONS && window.ZSS_TRANSLATIONS[key]) || fallback || key;
    return Object.keys(replace).reduce((text, name) => {
        return text.replaceAll(`{${name}}`, replace[name]);
    }, value);
}

function frequencyLabel(value) {
    return t(`frequency.${value}`, value);
}

function weekdayLabel(value) {
    return t(`weekday.${value}`, String(value));
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

// 格式化时间戳
function formatTimestamp(timestamp) {
    if (!timestamp) return '-';
    const date = new Date(timestamp * 1000);
    return date.toLocaleString(ZSS_LOCALE === 'zh-CN' ? 'zh-CN' : 'en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

// 加载数据
async function fetchData(url) {
    try {
        const response = await fetch(withLang(url), {
            headers: {
                'Accept': 'application/json'
            }
        });

        const contentType = response.headers.get('content-type') || '';
        let data = null;

        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            throw new Error(`Expected JSON but received: ${text.slice(0, 200)}`);
        }

        if (!response.ok) {
            throw new Error(data?.error?.message || `HTTP ${response.status}`);
        }

        if (data && data.ok === false) {
            throw new Error(data?.error?.message || 'API returned error');
        }

        return data;
    } catch (error) {
        console.error('Fetch error:', error);
        return {
            ok: false,
            error: {
                message: error.message || t('common.request_failed', 'Request failed')
            }
        };
    }
}

function renderTableMessage(tbodyId, message, colspan = 1, className = 'table-message') {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="${className}">${message}</td></tr>`;
}
