function renderLogLevel(level) {
    const normalized = String(level || '').toUpperCase();
    const className = normalized === 'ERROR'
        ? 'zss-badge-danger'
        : normalized === 'WARN'
            ? 'zss-badge-warning'
            : 'zss-badge-success';
    return `<span class="zss-badge ${className}">${escapeHtml(level || '-')}</span>`;
}

function renderLogStatus(data) {
    const box = document.getElementById('log-status');
    const status = data?.data?.status;
    const readError = data?.data?.read_error;

    if (!status) {
        box.innerHTML = `<div class="zss-status-box zss-status-warning">${escapeHtml(t('logs.status_missing', 'Unable to load log status'))}</div>`;
        return;
    }

    const path = escapeHtml(status.path || '');

    if (readError) {
        box.innerHTML = `<div class="zss-status-box zss-status-error">${escapeHtml(t('logs.status_read_failed', 'Failed to read logs: {error}', { error: readError }))}<br><code>${path}</code></div>`;
        return;
    }

    if (!status.exists) {
        const dirState = t('logs.status_dir_state', 'Directory exists: {dir_exists}; writable: {dir_writable}', {
            dir_exists: status.dir_exists ? t('common.yes', 'Yes') : t('common.no', 'No'),
            dir_writable: status.dir_writable ? t('common.yes', 'Yes') : t('common.no', 'No')
        });
        box.innerHTML = `<div class="zss-status-box zss-status-warning">${escapeHtml(t('logs.status_missing_file', 'Log file does not exist yet: {path}', { path: status.path || '' }))}<br>${escapeHtml(dirState)}</div>`;
        return;
    }

    if (!status.readable) {
        box.innerHTML = `<div class="zss-status-box zss-status-error">${escapeHtml(t('logs.status_unreadable', 'Log file exists but is not readable: {path}', { path: status.path || '' }))}</div>`;
        return;
    }

    box.innerHTML = `<div class="zss-status-box zss-status-ok">${escapeHtml(t('logs.status_ok', 'Log file is available: {path}', { path: status.path || '' }))}</div>`;
}

async function loadLogs() {
    const level = document.getElementById('log-level').value;
    const data = await fetchData(`../api/logs.php?level=${encodeURIComponent(level)}&limit=200`);
    renderLogStatus(data);

    if (!data || !data.ok) {
        renderTableMessage('logs-table', `${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.unknown_error', 'Unknown error')}`, 3);
        return;
    }

    const tbody = document.getElementById('logs-table');
    tbody.innerHTML = '';

    if (!data.data.logs || data.data.logs.length === 0) {
        const status = data.data.status;
        renderTableMessage('logs-table', status && !status.exists ? t('logs.file_missing', 'Log file has not been created yet') : t('logs.no_logs', 'No logs yet'), 3);
        return;
    }

    data.data.logs.forEach(log => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(log.timestamp || '-')}</td>
            <td>${renderLogLevel(log.level)}</td>
            <td class="zss-log-message">${escapeHtml(log.message || '')}</td>
        `;
        tbody.appendChild(row);
    });
}

async function clearLogs() {
    if (!confirm(t('logs.confirm_clear', 'Clear all logs?'))) return;

    try {
        const result = await postJson('../api/logs.php?action=clear');

        if (result.ok) {
            alert(t('logs.clear_success', 'Logs cleared'));
            loadLogs();
        } else {
            alert(`${t('logs.clear_failed', 'Failed to clear logs')}: ${result.error?.message || t('common.unknown_error', 'Unknown error')}`);
        }
    } catch (error) {
        alert(`${t('common.request_failed', 'Request failed')}: ${error.message}`);
    }
}

document.getElementById('log-level').addEventListener('change', loadLogs);
document.addEventListener('DOMContentLoaded', loadLogs);
