const ZSS_LOCALE = window.ZSS_LOCALE || document.body?.dataset?.locale || 'en';
const ZSS_LOCALE_PREFERENCE = window.ZSS_LOCALE_PREFERENCE || 'auto';

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
    return Object.keys(replace).reduce((text, name) => {
        return text.replaceAll(`{${name}}`, replace[name]);
    }, value);
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function frequencyLabel(value) {
    return t(`frequency.${value}`, value || '-');
}

function formatTimestamp(timestamp) {
    if (!timestamp) return '-';
    const date = new Date(Number(timestamp) * 1000);
    if (Number.isNaN(date.getTime())) return '-';
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

async function fetchData(url) {
    try {
        const response = await fetch(withLang(url), {
            headers: {
                'Accept': 'application/json'
            }
        });
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Expected JSON but received: ${text.slice(0, 160)}`);
        }

        const data = await response.json();
        if (!response.ok || data?.ok === false) {
            throw new Error(data?.error?.message || `HTTP ${response.status}`);
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
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return { ok: true, data: null };
    }

    if (!contentType.includes('application/json')) {
        throw new Error(`Expected JSON but received: ${text.slice(0, 160)}`);
    }

    const data = JSON.parse(text);
    if (!response.ok || data?.ok === false) {
        throw new Error(data?.error?.message || `HTTP ${response.status}`);
    }

    return data;
}

function renderTableMessage(tbodyId, message, colspan, className = 'zss-table-message') {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="${className}">${escapeHtml(message)}</td></tr>`;
}
