// 简单的工具函数
const ZSS_LOCALE = window.ZSS_LOCALE || document.body?.dataset?.locale || 'en';
const ZSS_LOCALE_PREFERENCE = window.ZSS_LOCALE_PREFERENCE || 'auto';
const ZSS_THEME = window.ZSS_THEME || localStorage.getItem('zss_theme') || 'auto';

function getEffectiveTheme(theme = ZSS_THEME) {
    if (theme === 'dark' || theme === 'light') {
        return theme;
    }

    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme = ZSS_THEME) {
    const effectiveTheme = getEffectiveTheme(theme);
    document.documentElement.dataset.theme = theme;
    document.documentElement.dataset.effectiveTheme = effectiveTheme;
    document.documentElement.style.colorScheme = effectiveTheme;
    document.body.dataset.theme = theme;
    document.body.dataset.effectiveTheme = effectiveTheme;
    window.ZSS_THEME = theme;
    return effectiveTheme;
}

document.addEventListener('DOMContentLoaded', function() {
    applyTheme(ZSS_THEME);
    document.body.classList.add('theme-ready');

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
        if (ZSS_LOCALE_PREFERENCE !== 'auto' && !parsed.searchParams.get('lang')) {
            parsed.searchParams.set('lang', ZSS_LOCALE);
        }
        return parsed.pathname + parsed.search + parsed.hash;
    } catch (error) {
        if (ZSS_LOCALE_PREFERENCE === 'auto') {
            return url;
        }

        const separator = url.includes('?') ? '&' : '?';
        return `${url}${separator}lang=${encodeURIComponent(ZSS_LOCALE)}`;
    }
}

function setLocale(locale, options = {}) {
    const url = new URL(window.location.href);

    if (locale === 'auto') {
        document.cookie = 'zss_lang=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
        url.searchParams.delete('lang');
    } else {
        document.cookie = `zss_lang=${encodeURIComponent(locale)}; path=/; max-age=31536000; SameSite=Lax`;
        url.searchParams.set('lang', locale);
    }

    if (typeof options.onSaved === 'function') {
        options.onSaved(locale);
    }

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
            throw new Error(data?.error?.message || t('common.api_returned_error', 'API returned error'));
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

async function postJson(url, payload = {}) {
    const parsed = new URL(withLang(url), window.location.href);
    Object.entries(payload).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
            parsed.searchParams.set(key, String(value));
        }
    });

    const response = await fetch(parsed.pathname + parsed.search + parsed.hash, {
        method: 'GET',
        credentials: 'include',
        headers: {
            'Accept': 'application/json',
            'X-ZSS-Action': '1'
        }
    });

    const contentType = response.headers.get('content-type') || '';
    const text = await response.text();

    if (text.trim() === '') {
        throw new Error(`Empty response from ${url} (HTTP ${response.status})`);
    }

    if (!contentType.includes('application/json')) {
        throw new Error(`Expected JSON but received: ${text.slice(0, 200)}`);
    }

    let data;
    try {
        data = JSON.parse(text);
    } catch (error) {
        throw new Error(`Invalid JSON response: ${text.slice(0, 200)}`);
    }

    if (!response.ok) {
        throw new Error(data?.error?.message || `HTTP ${response.status}`);
    }

    if (data && data.ok === false) {
        throw new Error(data?.error?.message || t('common.api_returned_error', 'API returned error'));
    }

    return data;
}

function renderTableMessage(tbodyId, message, colspan = 1, className = 'table-message') {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="${className}">${message}</td></tr>`;
}
