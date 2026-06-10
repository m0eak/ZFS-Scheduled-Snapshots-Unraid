document.addEventListener('DOMContentLoaded', async function() {
    const overview = await fetchData('../api/overview.php');
    if (overview && overview.ok) {
        const data = overview.data || {};
        document.getElementById('dataset-count').textContent = data.dataset_count || 0;
        document.getElementById('enabled-count').textContent = data.enabled_count || 0;
        document.getElementById('snapshot-count').textContent = data.snapshot_count || 0;
        document.getElementById('readonly-count').textContent = data.readonly_snapshot_count || 0;
        document.getElementById('snapshot-used-bytes').textContent = formatBytes(data.snapshot_used_bytes);
        document.getElementById('last-snapshot').textContent = data.last_snapshot_at ? formatTimestamp(data.last_snapshot_at) : '-';
        document.getElementById('last-dataset').textContent = data.last_snapshot_dataset || '-';
    } else {
        document.getElementById('last-snapshot').textContent = t('common.load_failed', 'Load failed');
        document.getElementById('last-dataset').textContent = overview?.error?.message || t('common.api_error', 'API error');
    }

    const datasets = await fetchData('../api/datasets.php');
    if (!datasets || !datasets.ok) {
        renderTableMessage('datasets-table', `${t('common.load_failed', 'Load failed')}: ${datasets?.error?.message || t('common.api_error', 'API error')}`, 7);
        return;
    }

    const tbody = document.getElementById('datasets-table');
    tbody.innerHTML = '';

    if (!datasets.data || datasets.data.length === 0) {
        renderTableMessage('datasets-table', t('datasets.empty', 'No datasets'), 7);
        return;
    }

    datasets.data.forEach(ds => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(ds.name)}</td>
            <td>${renderEnabledBadge(ds.enabled)}</td>
            <td>${escapeHtml(frequencyLabel(ds.frequency))}</td>
            <td>${escapeHtml(ds.keep ?? '-')}</td>
            <td>${renderReadonlyBadge(ds.readonly)}</td>
            <td>${escapeHtml(ds.snapshot_count ?? 0)}</td>
            <td><a class="zss-btn zss-btn-secondary" href="${withLang(`snapshots.php?dataset=${encodeURIComponent(ds.name)}`)}">${escapeHtml(t('snapshots.view', 'View snapshots'))}</a></td>
        `;
        tbody.appendChild(row);
    });
});

function renderEnabledBadge(enabled) {
    const className = enabled ? 'zss-badge-success' : 'zss-badge-muted';
    const label = enabled ? t('common.enabled', 'Enabled') : t('common.disabled', 'Disabled');
    return `<span class="zss-badge ${className}">${escapeHtml(label)}</span>`;
}

function renderReadonlyBadge(readonly) {
    const className = readonly ? 'zss-badge-info' : 'zss-badge-muted';
    const label = readonly ? t('common.yes', 'Yes') : t('common.no', 'No');
    return `<span class="zss-badge ${className}">${escapeHtml(label)}</span>`;
}
