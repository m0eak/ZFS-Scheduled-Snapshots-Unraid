/* ---- Motion helpers (B ring sweep / C bar growth) ----
   The shared page entrance (A) is armed by the shell in the initial HTML
   (data-zss-entered on .zss-content, data-zss-entrance on each surface),
   so it plays once per page load, decoupled from async data. This module
   only drives the data-specific animations (ring sweep, bar growth) that
   play once real data arrives. */

function zssPrefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// B · Ring sweep + count-up. rAF eases the conic-gradient percentage and the
// center number together; reduced motion or a zero denominator jumps straight
// to the final state. One-shot via dataset.zssSwept, but a later refresh always
// wins: each call claims a new token so a cached paint cannot be overwritten by
// the tail of an animation started for an older payload.
let protectionRingToken = 0;

function animateProtectionRing(ring, valueEl, targetPercent) {
    const token = ++protectionRingToken;

    const paint = percent => {
        if (token !== protectionRingToken) return false;
        ring.style.background = `conic-gradient(var(--green-800, #127a05) 0 ${percent}%, var(--zss-viz-track) ${percent}% 100%)`;
        valueEl.textContent = `${Math.round(percent)}%`;
        return true;
    };
    const finalize = () => {
        if (!paint(targetPercent)) return;
        ring.setAttribute('aria-label', `${t('overview.console.protected_ring', 'Protection window')} — ${targetPercent}%`);
    };

    // Already swept on this page load: skip the entrance animation but still
    // repaint the arc, otherwise the number would update while the ring keeps
    // the percentage from the cached payload.
    if (ring.dataset.zssSwept) {
        finalize();
        return;
    }
    ring.dataset.zssSwept = '1';

    if (zssPrefersReducedMotion()) {
        finalize();
        return;
    }

    const startedAt = performance.now();
    const duration = 950;
    // ease-out cubic, matches --zss-ease-out intent from the motion study.
    const easeOutCubic = x => 1 - Math.pow(1 - x, 3);
    const frame = now => {
        if (token !== protectionRingToken) return;
        const progress = Math.min((now - startedAt) / duration, 1);
        if (!paint(targetPercent * easeOutCubic(progress))) return;
        if (progress < 1) {
            window.requestAnimationFrame(frame);
        } else {
            finalize();
        }
    };
    window.requestAnimationFrame(frame);
}

// C · Overview space bars. Rows rendered while data-zss-grown is absent are
// born at width 0 with their target stored in --zss-bar-width; arming the
// attribute once lets CSS transition them up. Later refreshes see the
// attribute already present and render rows directly at full width.
function armSpaceBarsOnce(list) {
    if (!list || list.dataset.zssGrown) return;
    list.dataset.zssGrown = '1';
}

document.addEventListener('DOMContentLoaded', async function() {
    const cachedDatasets = zssCacheGet('datasets');
    const cachedOverview = zssCacheGet('overview');
    if (cachedDatasets && cachedDatasets.ok) {
        renderOverviewDatasets(cachedDatasets);
    }
    if (cachedOverview && cachedOverview.ok) {
        renderOverview(cachedOverview);
    }

    const epochAtStart = zssCacheEpochValue();
    const datasets = await fetchDatasetsShared();
    if (zssCacheEpochValue() !== epochAtStart) {
        return;
    }
    if (!datasets || !datasets.ok) {
        if (!(cachedDatasets && cachedDatasets.ok)) {
            renderTableMessage('datasets-table', `${t('common.load_failed', 'Load failed')}: ${datasets?.error?.message || t('common.api_error', 'API error')}`, 7);
            renderSpaceUsage(null);
        } else {
            zssNotifyStale(datasets?.error);
            zssMarkStale('datasets');
        }
        if (!(cachedOverview && cachedOverview.ok)) {
            document.getElementById('last-snapshot').textContent = t('common.load_failed', 'Load failed');
            document.getElementById('last-dataset').textContent = datasets?.error?.message || t('common.api_error', 'API error');
        }
        return;
    }

    zssMarkFresh('datasets');
    zssCacheSet('datasets', datasets);
    renderOverviewDatasets(datasets);
    if (datasets.meta && datasets.meta.overview) {
        const overview = { ok: true, data: datasets.meta.overview };
        zssCacheSet('overview', overview);
        renderOverview(overview);
    }
});

// Renders the metric strip and the protection ring from one overview payload.
function renderOverview(payload) {
    const data = (payload && payload.data) || {};
    document.getElementById('dataset-count').textContent = data.dataset_count || 0;
    document.getElementById('enabled-count').textContent = data.enabled_count || 0;
    document.getElementById('snapshot-count').textContent = data.snapshot_count || 0;
    document.getElementById('readonly-count').textContent = data.readonly_snapshot_count || 0;
    const space = data.snapshot_space;
    const knownBytes = space && typeof space.known_bytes === 'number' && Number.isFinite(space.known_bytes) && space.known_bytes >= 0
        ? space.known_bytes
        : (space ? null : data.snapshot_used_bytes);
    document.getElementById('snapshot-used-bytes').textContent = knownBytes === null
        ? t('overview.space.unknown_total', 'Total unavailable')
        : formatBytes(knownBytes);
    const spaceStatus = document.getElementById('snapshot-used-status');
    if (spaceStatus) {
        spaceStatus.textContent = space?.status === 'partial'
            ? t('overview.space.partial_total', 'Partial: {count} dataset total(s) unavailable', {
                count: Number.isFinite(space.unknown_datasets) ? space.unknown_datasets : '?',
            })
            : '';
    }
    document.getElementById('last-snapshot').textContent = data.last_snapshot_at ? formatTimestamp(data.last_snapshot_at) : '-';
    document.getElementById('last-dataset').textContent = data.last_snapshot_dataset || '-';
    renderProtectionRing(data);
}

// Renders the space list, the inventory counter, and the dataset table. Only
// the tbody is rebuilt, so the panel shell (and its one-shot motion flags)
// survives the stale → fresh repaint.
function renderOverviewDatasets(payload) {
    const datasets = (payload && payload.data) || [];

    renderSpaceUsage(datasets);
    updateDatasetInventoryCount(datasets);

    const tbody = document.getElementById('datasets-table');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (datasets.length === 0) {
        renderTableMessage('datasets-table', t('datasets.empty', 'No datasets'), 7);
        return;
    }

    datasets.forEach(ds => {
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
}

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

    animateProtectionRing(ring, valueEl, percent);
}

function renderSpaceUsage(datasets) {
    const list = document.getElementById('space-list');
    const totalEl = document.getElementById('space-total');
    const totalValue = document.getElementById('space-total-value');
    const totalStatus = document.getElementById('space-total-status');
    if (!list) return;

    const byteValue = value => (
        typeof value === 'number' && Number.isFinite(value) && value >= 0
            ? value
            : null
    );
    const displayBytes = value => value === null
        ? t('overview.space.unavailable_value', '—')
        : formatBytes(value);
    const origins = [
        ['autosnap', t('overview.space.autosnap', 'Auto')],
        ['plugin_manual', t('overview.space.plugin_manual', 'Plugin manual')],
        ['external', t('overview.space.external', 'External')],
        ['shared', t('overview.space.shared', 'Shared or unattributed')],
    ];
    const rows = (datasets || []).map(ds => {
        const space = ds && ds.snapshot_space && typeof ds.snapshot_space === 'object'
            ? ds.snapshot_space
            : null;
        const totalBytes = space
            ? byteValue(space.total_bytes)
            : byteValue(ds && ds.snapshot_used_bytes);
        const exclusive = space && space.exclusive_bytes && typeof space.exclusive_bytes === 'object'
            ? space.exclusive_bytes
            : null;
        const values = exclusive
            ? {
                autosnap: byteValue(exclusive.autosnap),
                plugin_manual: byteValue(exclusive.plugin_manual),
                external: byteValue(exclusive.external),
                shared: byteValue(space.shared_bytes),
            }
            : null;
        const allSegmentsKnown = values && Object.values(values).every(value => value !== null);
        const segmentTotal = allSegmentsKnown
            ? Object.values(values).reduce((sum, value) => sum + value, 0)
            : null;
        let status = space?.status || (values ? 'unknown' : 'unknown');
        if (totalBytes === null) {
            status = 'unknown';
        } else if (status === 'ok' && (!allSegmentsKnown || segmentTotal !== totalBytes)) {
            status = 'inconsistent';
        } else if (status !== 'inconsistent' && status !== 'ok') {
            status = 'unknown';
        }
        const showSegments = status === 'ok' && totalBytes !== null && allSegmentsKnown;
        return {
            name: String((ds && ds.name) || ''),
            totalBytes,
            values,
            status,
            showSegments,
        };
    }).sort((a, b) => {
        if (a.totalBytes === null) return b.totalBytes === null ? a.name.localeCompare(b.name) : 1;
        if (b.totalBytes === null) return -1;
        return b.totalBytes - a.totalBytes || a.name.localeCompare(b.name);
    });

    if (rows.length === 0) {
        list.innerHTML = `<p class="zss-console-empty">${escapeHtml(t('overview.console.space_usage', 'Snapshot space usage'))}: ${escapeHtml(t('common.none', 'None'))}</p>`;
        if (totalEl) totalEl.hidden = true;
        return;
    }

    const knownRows = rows.filter(row => row.totalBytes !== null);
    const maxBytes = knownRows.reduce((max, row) => Math.max(max, row.totalBytes), 0);
    const knownTotalBytes = knownRows.reduce((sum, row) => sum + row.totalBytes, 0);
    const unknownDatasets = rows.length - knownRows.length;
    const percentText = ratio => {
        const percent = ratio * 100;
        return `${Number(percent.toFixed(4))}%`;
    };
    const statusLabel = status => status === 'inconsistent'
        ? t('overview.space.inconsistent', 'Inconsistent source totals')
        : status === 'unknown'
            ? t('overview.space.unknown', 'Source breakdown unavailable')
            : '';
    const legend = `<div class="zss-space-legend" aria-label="${escapeHtml(t('overview.space.legend', 'Exclusive-space source estimates'))}">${origins.map(([key, label]) => `<span class="zss-space-legend-item"><i class="zss-space-swatch zss-space-swatch--${key}" aria-hidden="true"></i>${escapeHtml(label)}</span>`).join('')}</div>`;
    list.innerHTML = rows.map(row => {
        const totalRatio = maxBytes > 0 && row.totalBytes !== null ? row.totalBytes / maxBytes : 0;
        const segments = row.showSegments
            ? origins.map(([key, label]) => {
                const bytes = key === 'shared' ? row.values.shared : row.values[key];
                const ratio = row.totalBytes > 0 ? bytes / row.totalBytes : 0;
                return `<span class="zss-space-segment zss-space-segment--${key}" data-source="${key}" title="${escapeHtml(`${label}: ${formatBytes(bytes)}`)}" style="--zss-segment-width:${percentText(ratio)}"></span>`;
            }).join('')
            : row.totalBytes === null
                ? ''
                : '<span class="zss-space-segment zss-space-segment--unclassified" data-source="unclassified" style="--zss-segment-width:100%"></span>';
        const sourceValues = row.values
            ? origins.map(([key, label]) => {
                const bytes = key === 'shared' ? row.values.shared : row.values[key];
                return `<span class="zss-space-breakdown-value">${escapeHtml(label)}: ${escapeHtml(displayBytes(bytes))}</span>`;
            }).join('')
            : '';
        const rowValue = row.totalBytes === null
            ? t('overview.space.unknown_total', 'Total unavailable')
            : displayBytes(row.totalBytes);
        return `
            <div class="zss-space-row" data-space-status="${row.status}">
                <span class="zss-space-name" title="${escapeHtml(row.name)}">${escapeHtml(row.name)}</span>
                <span class="zss-bar" role="img" aria-label="${escapeHtml(`${row.name}: ${rowValue}`)}"><span class="zss-bar-fill" style="--zss-bar-width:${percentText(totalRatio)}">${segments}</span></span>
                <span class="zss-space-value">${escapeHtml(rowValue)}</span>
                <span class="zss-space-row-status">${escapeHtml(statusLabel(row.status))}</span>
                ${sourceValues ? `<span class="zss-space-breakdown">${sourceValues}</span>` : ''}
            </div>
        `;
    }).join('') + legend;

    armSpaceBarsOnce(list);

    if (totalEl && totalValue) {
        totalValue.textContent = formatBytes(knownTotalBytes);
        if (totalStatus) {
            totalStatus.textContent = unknownDatasets > 0
                ? t('overview.space.partial_total', 'Partial: {count} dataset total(s) unavailable', { count: unknownDatasets })
                : t('overview.space.complete_total', 'Complete dataset total');
        }
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

// Aggregates the dataset inventory header counter (e.g. "4 visible · 3 enabled
// · 27 snapshots") from the already-loaded dataset list. No new API call:
// the same payload drives the protection ring, space list, and table.
function updateDatasetInventoryCount(datasets) {
    const countEl = document.getElementById('dataset-inventory-count');
    if (!countEl) return;

    const visible = (datasets || []).length;
    const enabled = (datasets || []).filter(ds => ds && ds.enabled).length;
    const totalSnapshots = (datasets || []).reduce((sum, ds) => sum + (Number(ds && ds.snapshot_count) || 0), 0);

    countEl.textContent = t('overview.inventory.count', '{visible} visible · {enabled} enabled · {total} snapshots', {
        visible: visible,
        enabled: enabled,
        total: totalSnapshots,
    });
}
