/* ---- Motion helpers (A entrance / B ring sweep / C bar growth) ---- */

function zssPrefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// A · Entrance orchestration. Runs exactly once per page load: the flag is
// set before the first data paint, so later refreshes (refreshResourceTree,
// re-selecting a dataset, post-mutation reloads) never replay the sequence.
function zssArmEntranceOnce() {
    const content = document.querySelector('.zss-content');
    if (!content || content.dataset.zssEntered) return;
    content.dataset.zssEntered = '1';
}

// B · Ring sweep + count-up. rAF eases the conic-gradient percentage and the
// center number together; reduced motion or a zero denominator jumps straight
// to the final state. One-shot via dataset.zssSwept.
function animateProtectionRing(ring, valueEl, targetPercent) {
    if (ring.dataset.zssSwept) {
        valueEl.textContent = `${targetPercent}%`;
        ring.setAttribute('aria-label', `${t('overview.console.protected_ring', 'Protection window')} — ${targetPercent}%`);
        return;
    }
    ring.dataset.zssSwept = '1';

    const paint = percent => {
        ring.style.background = `conic-gradient(var(--green-800, #127a05) 0 ${percent}%, var(--zss-viz-track) ${percent}% 100%)`;
        valueEl.textContent = `${Math.round(percent)}%`;
    };
    const finalize = () => {
        paint(targetPercent);
        ring.setAttribute('aria-label', `${t('overview.console.protected_ring', 'Protection window')} — ${targetPercent}%`);
    };

    if (zssPrefersReducedMotion()) {
        finalize();
        return;
    }

    const startedAt = performance.now();
    const duration = 950;
    // ease-out cubic, matches --zss-ease-out intent from the motion study.
    const easeOutCubic = x => 1 - Math.pow(1 - x, 3);
    const frame = now => {
        const progress = Math.min((now - startedAt) / duration, 1);
        paint(targetPercent * easeOutCubic(progress));
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

    const datasets = await fetchDatasetsShared();
    if (!datasets || !datasets.ok) {
        renderTableMessage('datasets-table', `${t('common.load_failed', 'Load failed')}: ${datasets?.error?.message || t('common.api_error', 'API error')}`, 7);
        renderSpaceUsage(null);
        zssArmEntranceOnce();
        return;
    }

    renderSpaceUsage(datasets.data || []);
    zssArmEntranceOnce();

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

    animateProtectionRing(ring, valueEl, percent);
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
                <span class="zss-bar"><span class="zss-bar-fill ${tier}" style="--zss-bar-width:${percent}%"></span></span>
                <span class="zss-space-value">${escapeHtml(formatBytes(row.bytes))}</span>
            </div>
        `;
    }).join('');

    armSpaceBarsOnce(list);

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
