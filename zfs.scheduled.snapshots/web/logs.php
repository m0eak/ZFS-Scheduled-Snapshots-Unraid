<?php
$currentPage = 'logs';
require_once __DIR__ . '/layout/header.php';
?>

<h2><?php echo htmlspecialchars(zss_t('logs.title')); ?></h2>

<div style="margin-bottom: 15px;">
    <select id="log-level" class="form-select" style="width: 150px; display: inline-block; margin-right: 10px;">
        <option value="all"><?php echo htmlspecialchars(zss_t('logs.level.all')); ?></option>
        <option value="INFO">INFO</option>
        <option value="ERROR">ERROR</option>
    </select>
    <button class="btn btn-small" onclick="loadLogs()"><?php echo htmlspecialchars(zss_t('common.refresh')); ?></button>
    <button class="btn btn-small btn-secondary" onclick="clearLogs()" style="margin-left: 10px;"><?php echo htmlspecialchars(zss_t('logs.clear')); ?></button>
</div>

<div id="log-status" class="log-status" style="margin-bottom: 15px;"></div>

<div class="table-wrapper" style="max-height: 600px; overflow-y: auto;">
    <table>
        <thead>
            <tr>
                <th style="width: 180px;"><?php echo htmlspecialchars(zss_t('table.created_at')); ?></th>
                <th style="width: 80px;"><?php echo htmlspecialchars(zss_t('table.level')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.message')); ?></th>
            </tr>
        </thead>
        <tbody id="logs-table">
            <tr>
                <td colspan="3"><?php echo htmlspecialchars(zss_t('common.loading')); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<script>
function renderLogStatus(data) {
    const box = document.getElementById('log-status');
    const status = data?.data?.status;
    const readError = data?.data?.read_error;

    if (!status) {
        box.innerHTML = `<div class="status-box warn">${t('logs.status_missing', 'Unable to load log status')}</div>`;
        return;
    }

    const pathCode = `<code>${escapeHtml(status.path || '')}</code>`;

    if (readError) {
        box.innerHTML = `<div class="status-box error">${t('logs.status_read_failed', 'Failed to read logs: {error}', { error: escapeHtml(readError) })}<br>${t('logs.status_path', 'Path: {path}', { path: pathCode })}</div>`;
        return;
    }

    if (!status.exists) {
        box.innerHTML = `<div class="status-box warn">${t('logs.status_missing_file', 'Log file does not exist yet: {path}', { path: pathCode })}<br>${t('logs.status_dir_state', 'Directory exists: {dir_exists}; writable: {dir_writable}', { dir_exists: status.dir_exists ? t('common.yes', 'Yes') : t('common.no', 'No'), dir_writable: status.dir_writable ? t('common.yes', 'Yes') : t('common.no', 'No') })}</div>`;
        return;
    }

    if (!status.readable) {
        box.innerHTML = `<div class="status-box error">${t('logs.status_unreadable', 'Log file exists but is not readable: {path}', { path: pathCode })}</div>`;
        return;
    }

    box.innerHTML = `<div class="status-box ok">${t('logs.status_ok', 'Log file is available: {path}', { path: pathCode })}</div>`;
}

async function loadLogs() {
    const level = document.getElementById('log-level').value;
    const tbody = document.getElementById('logs-table');
    
    const data = await fetchData(`../api/logs.php?level=${encodeURIComponent(level)}&limit=200`);
    renderLogStatus(data);
    
    if (data && data.ok) {
        tbody.innerHTML = '';
        
        if (data.data.logs.length === 0) {
            const status = data.data.status;
            if (status && !status.exists) {
                renderTableMessage('logs-table', t('logs.file_missing', 'Log file has not been created yet'), 3);
            } else {
                renderTableMessage('logs-table', t('logs.no_logs', 'No logs yet'), 3);
            }
        } else {
            data.data.logs.forEach(log => {
                const row = document.createElement('tr');
                const levelClass = log.level === 'ERROR' ? 'disabled' : 'enabled';
                row.innerHTML = `
                    <td>${log.timestamp}</td>
                    <td><span class="status ${levelClass}">${log.level}</span></td>
                    <td>${log.message}</td>
                `;
                tbody.appendChild(row);
            });
        }
    } else {
        renderTableMessage('logs-table', `${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.unknown_error', 'Unknown error')}`, 3, 'table-message error');
    }
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

document.getElementById('log-level').addEventListener('change', function() {
    loadLogs();
});

document.addEventListener('DOMContentLoaded', function() {
    loadLogs();
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
