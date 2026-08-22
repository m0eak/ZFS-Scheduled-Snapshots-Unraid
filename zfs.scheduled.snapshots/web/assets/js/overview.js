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
        renderProtectionRing(data);
    } else {
        document.getElementById('last-snapshot').textContent = t('common.load_failed', 'Load failed');
        document.getElementById('last-dataset').textContent = overview?.error?.message || t('common.api_error', 'API error');
        renderTableMessage('datasets-table', `${t('common.load_failed', 'Load failed')}: ${overview?.error?.message || t('common.api_error', 'API error')}`, 7);
        return;
    }

    const datasets = await fetchData('../api/datasets.php');
    if (!datasets || !datasets.ok) {
        renderTableMessage('datasets-table', `${t('common.load_failed', 'Load failed')}: ${datasets?.error?.message || t('common.api_error', 'API error')}`, 7);
        renderSpaceUsage(null);
        return;
    }

    renderSpaceUsage(datasets.data || []);

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

function renderProtectionRing(data) {
    const ring = document.getElementById('protection-ring');
    const valueEl = document.getElementById('protection-ring-value');
    if (!ring || !valueEl) return;

    const total = Number(data.snapshot_count) || 0;
    const held = Number(data.readonly_snapshot_count) || 0;
    // readonly_snapshot_count counts every snapshot with at least one hold
    // tag, so the ring is a two-segment good/track gauge (no per-tag split
    // is available from the API).
    const percent = total > 0 ? Math.min(100, Math.max(0, Math.round((held / total) * 100))) : 0;

    ring.style.background = `conic-gradient(var(--green-800, #127a05) 0 ${percent}%, var(--zss-viz-track) ${percent}% 100%)`;
    valueEl.textContent = `${percent}%`;
    ring.setAttribute('aria-label', `${t('overview.console.protected_ring', 'Protection window')} — ${percent}%`);
}

function renderSpaceUsage(datasets) {
    const list = document.getElementById('space-list');
    const totalEl = document.getElementById('space-total');
    const totalValue = document.getElementById('space-total-value');
    if (!list) return;

    const rows = (datasets || [])
        .map(ds => ({ name: ds.name || '', bytes: Number(ds.snapshot_used_bytes) || 0 }))
        .filter(row => row.bytes > 0)
        .sort((a, b) => b.bytes - a.bytes);

    if (rows.length === 0) {
        list.innerHTML = `<p class="zss-console-empty">${escapeHtml(t('overview.console.space_usage', 'Snapshot space usage'))}: ${escapeHtml(t('common.none', 'None'))}</p>`;
        if (totalEl) totalEl.hidden = true;
        return;
    }

    const maxBytes = rows[0].bytes;
    const totalBytes = rows.reduce((sum, row) => sum + row.bytes, 0);
    list.innerHTML = rows.map(row => {
        // Relative to the largest consumer so the widest bar always reads as 100%.
        const percent = Math.max(2, Math.round((row.bytes / maxBytes) * 100));
        const tier = percent > 80 ? 'high' : percent >= 40 ? 'mid' : 'ok';
        return `
            <div class="zss-space-row">
                <span class="zss-space-name" title="${escapeHtml(row.name)}">${escapeHtml(row.name)}</span>
                <span class="zss-bar"><span class="zss-bar-fill ${tier}" style="width:${percent}%"></span></span>
                <span class="zss-space-value">${escapeHtml(formatBytes(row.bytes))}</span>
            </div>
        `;
    }).join('');

    if (totalEl && totalValue) {
        totalValue.textContent = formatBytes(totalBytes);
        totalEl.hidden = false;
    }
}

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
