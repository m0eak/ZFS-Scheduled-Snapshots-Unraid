function snapshotOriginLabel(origin) {
    if (origin === 'autosnap') {
        return t('snapshots.origin_autosnap', 'Auto');
    }

    if (origin === 'plugin_manual') {
        return t('snapshots.origin_plugin_manual', 'Plugin manual');
    }

    return t('snapshots.origin_external', 'External');
}

function renderOriginTag(origin) {
    const modifier = origin === 'autosnap' ? 'is-auto' : origin === 'plugin_manual' ? 'is-manual' : '';
    return `<span class="zss-origin-tag ${modifier}">${escapeHtml(snapshotOriginLabel(origin))}</span>`;
}

function renderHoldTagChips(snap) {
    const holdTags = Array.isArray(snap.hold_tags) ? snap.hold_tags : [];
    if (holdTags.length === 0) {
        return '<span class="zss-hold-tag zss-hold-none">—</span>';
    }

    return holdTags
        .map(tag => `<span class="zss-hold-tag" title="${escapeHtml(t('common.protected', 'Protected'))}">${escapeHtml(tag)}</span>`)
        .join('');
}

function renderSnapshotActions(snap) {
    const actions = snap.actions || {};
    const encodedName = escapeHtml(JSON.stringify(snap.name));
    const escapedOrigin = escapeHtml(snap.origin || 'external');
    const escapedHoldTags = escapeHtml(JSON.stringify(snap.hold_tags || []));
    const safe = [];
    const destructive = [];

    if (snap.operable === false) {
        return `<span class="zss-badge zss-badge-muted">${escapeHtml(t('snapshots.read_only_external', 'Read only'))}</span>`;
    }

    if (actions.release) {
        safe.push(`<button class="zss-btn zss-btn-secondary zss-btn-small" data-action="release" data-name="${encodedName}" data-hold-tags="${escapedHoldTags}">${escapeHtml(t('snapshots.release', 'Release hold'))}</button>`);
    }

    if (actions.hold) {
        safe.push(`<button class="zss-btn zss-btn-secondary zss-btn-small" data-action="hold" data-name="${encodedName}">${escapeHtml(t('snapshots.hold', 'Set read-only'))}</button>`);
    }

    if (actions.rollback) {
        destructive.push(`<button class="zss-btn zss-btn-warning zss-btn-small" data-action="rollback" data-name="${encodedName}">${escapeHtml(t('snapshots.rollback', 'Rollback'))}</button>`);
    }

    if (actions.delete) {
        destructive.push(`<button class="zss-btn zss-btn-danger zss-btn-small" data-action="delete" data-name="${encodedName}" data-origin="${escapedOrigin}">${escapeHtml(t('common.delete', 'Delete'))}</button>`);
    }

    if (safe.length === 0 && destructive.length === 0) {
        return `<span class="zss-badge zss-badge-muted">${escapeHtml(t('common.none', 'None'))}</span>`;
    }

    // Risk hierarchy: low-risk read-only actions first, then clearly separated
    // destructive actions (rollback / delete) — never icon-only.
    let html = '<div class="zss-action-row zss-risk-actions">';
    if (safe.length) html += `<span class="zss-action-tier" role="group" aria-label="${escapeHtml(t('snapshots.actions.safe', 'Safe actions'))}">${safe.join(' ')}</span>`;
    if (destructive.length) html += `<span class="zss-action-tier zss-tier-destructive" role="group" aria-label="${escapeHtml(t('snapshots.actions.destructive', 'Destructive actions'))}">${destructive.join(' ')}</span>`;
    html += '</div>';
    return html;
}

function renderDatasetListHead() {
    const head = document.getElementById('snapshots-table-head');
    if (head) {
        head.innerHTML = `
            <th>${escapeHtml(t('table.dataset', 'Dataset'))}</th>
            <th>${escapeHtml(t('table.snapshot_count', 'Snapshots'))}</th>
            <th>${escapeHtml(t('table.status', 'Status'))}</th>
            <th>${escapeHtml(t('table.actions', 'Actions'))}</th>
        `;
    }
}

function renderSnapshotListHead() {
    const head = document.getElementById('snapshots-table-head');
    if (head) {
        head.innerHTML = `
            <th>${escapeHtml(t('table.snapshot_name', 'Snapshot Name'))}</th>
            <th>${escapeHtml(t('snapshots.source', 'Source'))}</th>
            <th>${escapeHtml(t('table.created_at', 'Created At'))}</th>
            <th>${escapeHtml(t('common.protected', 'Protected'))}</th>
            <th>${escapeHtml(t('table.actions', 'Actions'))}</th>
        `;
    }
}

async function loadDatasetList() {
    renderDatasetListHead();
    const data = await fetchData('../api/datasets.php');

    if (!data || !data.ok) {
        renderTableMessage('snapshots-table', `${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.unknown_error', 'Unknown error')}`, 4);
        return;
    }

    const tbody = document.getElementById('snapshots-table');
    tbody.innerHTML = '';

    if (!data.data || data.data.length === 0) {
        renderTableMessage('snapshots-table', t('snapshots.dataset_empty', 'No datasets'), 4);
        return;
    }

    data.data.forEach(ds => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(ds.name)}</td>
            <td>${escapeHtml(ds.snapshot_count ?? 0)}</td>
            <td><span class="zss-badge ${ds.enabled ? 'zss-badge-success' : 'zss-badge-muted'}">${escapeHtml(ds.enabled ? t('common.enabled', 'Enabled') : t('common.disabled', 'Disabled'))}</span></td>
            <td><a class="zss-btn zss-btn-secondary zss-btn-small" href="${withLang(`snapshots.php?dataset=${encodeURIComponent(ds.name)}`)}">${escapeHtml(t('snapshots.view', 'View snapshots'))}</a></td>
        `;
        tbody.appendChild(row);
    });
}

function setTimelineMessage(message) {
    const timeline = document.getElementById('snapshots-timeline');
    if (timeline) {
        timeline.innerHTML = `<p class="zss-timeline-message">${escapeHtml(message)}</p>`;
    }
}

function groupSnapshotsByDay(snaps) {
    const groups = new Map();
    const formatter = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit' });
    snaps.forEach(snap => {
        if (!snap.created_at) return;
        // en-CA yields the unambiguous YYYY-MM-DD key regardless of host locale.
        const dayKey = formatter.format(new Date(Number(snap.created_at) * 1000));
        if (!groups.has(dayKey)) {
            groups.set(dayKey, []);
        }
        groups.get(dayKey).push(snap);
    });
    return [...groups.entries()].sort((a, b) => b[0].localeCompare(a[0]));
}

function renderDayLabel(dayKey) {
    const todayKey = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    const yesterdayDate = new Date();
    yesterdayDate.setDate(yesterdayDate.getDate() - 1);
    const yesterdayKey = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(yesterdayDate);

    let name;
    if (dayKey === todayKey) {
        name = t('snapshots.timeline.today', 'Today');
    } else if (dayKey === yesterdayKey) {
        name = t('snapshots.timeline.yesterday', 'Yesterday');
    } else {
        name = dayKey.slice(5).replace('-', '/');
    }

    const daysAgo = Math.max(0, Math.round((new Date(`${todayKey}T00:00:00`) - new Date(`${dayKey}T00:00:00`)) / 86400000));
    const sub = daysAgo > 0
        ? `${dayKey} · ${t('snapshots.timeline.days_ago', '{days} days ago', { days: daysAgo })}`
        : dayKey;

    return `<b class="zss-day-name">${escapeHtml(String(name))}</b><span class="zss-day-sub">${escapeHtml(sub)}</span>`;
}

function renderEventTime(created_at) {
    if (!created_at) return '-';
    const date = new Date(Number(created_at) * 1000);
    if (Number.isNaN(date.getTime())) return '-';
    const pad = value => String(value).padStart(2, '0');
    return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function renderAgeBar(snap, oldestCreatedAt) {
    const created = Number(snap.created_at) || 0;
    const nowSeconds = Math.floor(Date.now() / 1000);
    const span = Math.max(1, nowSeconds - oldestCreatedAt);
    // Older snapshots fill further; the newest snapshot reads as nearly empty.
    const percent = Math.min(100, Math.max(2, Math.round(((nowSeconds - created) / span) * 100)));
    const tier = percent > 80 ? 'high' : percent >= 40 ? 'mid' : 'ok';
    return `<span class="zss-agebar"><span class="zss-bar"><span class="zss-bar-fill ${tier}" style="width:${percent}%"></span></span></span>`;
}

function renderSnapshotEvent(snap, oldestCreatedAt) {
    return `
        <div class="zss-event">
            <span class="zss-event-time">${escapeHtml(renderEventTime(snap.created_at))}</span>
            <span class="zss-event-name" title="${escapeHtml(snap.name)}">${escapeHtml(snap.short_name || snap.name)}</span>
            ${renderOriginTag(snap.origin || 'external')}
            ${renderHoldTagChips(snap)}
            ${renderAgeBar(snap, oldestCreatedAt)}
            ${renderSnapshotActions(snap)}
        </div>
    `;
}

function renderSnapshotTimeline(snaps) {
    const timeline = document.getElementById('snapshots-timeline');
    if (!timeline) return;

    const withTime = snaps.filter(snap => snap.created_at);
    const oldestCreatedAt = withTime.length
        ? Math.min(...withTime.map(snap => Number(snap.created_at)))
        : 0;

    const groups = groupSnapshotsByDay(snaps);
    if (groups.length === 0) {
        timeline.innerHTML = `<p class="zss-timeline-message">${escapeHtml(t('snapshots.empty', 'No snapshots'))}</p>`;
        return;
    }

    timeline.innerHTML = groups.map(([dayKey, daySnaps]) => `
        <div class="zss-day">
            <div class="zss-day-label">${renderDayLabel(dayKey)}</div>
            <div class="zss-events">${daySnaps.map(snap => renderSnapshotEvent(snap, oldestCreatedAt)).join('')}</div>
        </div>
    `).join('');
}

async function loadSnapshots(datasetName) {
    renderSnapshotListHead();
    const data = await fetchData(`../api/snapshots.php?name=${encodeURIComponent(datasetName)}`);

    if (!data || !data.ok) {
        setTimelineMessage(`${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.unknown_error', 'Unknown error')}`);
        hideActivityStrip();
        return;
    }

    const snapshots = data.data || [];
    if (snapshots.length === 0) {
        setTimelineMessage(t('snapshots.empty', 'No snapshots'));
        hideActivityStrip();
        return;
    }

    renderSnapshotTimeline(snapshots);
    renderActivityStrip(snapshots);
}

function hideActivityStrip() {
    const panel = document.getElementById('activity-panel');
    if (panel) panel.hidden = true;
}

function aggregateActivityByDay(snaps) {
    const counts = new Map();
    const heldDays = new Set();
    const formatter = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit' });
    snaps.forEach(snap => {
        if (!snap.created_at) return;
        const dayKey = formatter.format(new Date(Number(snap.created_at) * 1000));
        counts.set(dayKey, (counts.get(dayKey) || 0) + 1);
        if (snap.held) heldDays.add(dayKey);
    });
    return { counts, heldDays };
}

function renderActivityStrip(snaps) {
    const panel = document.getElementById('activity-panel');
    const strip = document.getElementById('activity-strip');
    const axisStart = document.getElementById('activity-axis-start');
    const axisEnd = document.getElementById('activity-axis-end');
    const legend = document.getElementById('activity-legend');
    if (!panel || !strip) return;

    const { counts, heldDays } = aggregateActivityByDay(snaps);
    const maxCount = Math.max(1, ...counts.values());
    const today = new Date();
    today.setHours(12, 0, 0, 0);

    const columns = [];
    for (let offset = 29; offset >= 0; offset--) {
        const date = new Date(today);
        date.setDate(date.getDate() - offset);
        const dayKey = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
        const count = counts.get(dayKey) || 0;
        const heightPercent = count === 0 ? 6 : 18 + Math.round((count / maxCount) * 82);
        columns.push(`<span class="zss-activity-col${count >= maxCount && count > 0 ? ' hot' : ''}${heldDays.has(dayKey) ? ' held' : ''}" style="height:${heightPercent}%"></span>`);
    }

    strip.innerHTML = columns.join('');
    panel.hidden = false;

    if (axisStart) axisStart.textContent = t('snapshots.activity.range_start', '30 days ago');
    if (axisEnd) axisEnd.textContent = t('snapshots.activity.range_end', 'Today');
    if (legend) legend.textContent = t('snapshots.activity.legend', 'Bar height = snapshots created that day; ◆ marks days with held snapshots.');
}

async function createSnapshot() {
    const confirmed = await zssConfirmAction({
        title: t('snapshots.create', 'Create snapshot manually'),
        message: t('snapshots.confirm_create', 'Create a snapshot manually now?'),
        detail: dataset,
        confirmText: t('snapshots.create', 'Create snapshot manually'),
    });
    if (!confirmed) return;

    try {
        const result = await postJson('../api/snapshot-create.php', { name: dataset });

        if (result.ok) {
            zssToast({ type: 'success', title: t('snapshots.create_success', 'Snapshot created') });
            window.setTimeout(() => loadSnapshots(dataset), 450);
        } else {
            zssToast({
                type: 'error',
                title: t('snapshots.create_failed', 'Create failed'),
                message: result.error?.message || t('common.unknown_error', 'Unknown error'),
            });
        }
    } catch (error) {
        zssToast({ type: 'error', title: t('common.request_failed', 'Request failed'), message: error.message });
    }
}

async function deleteSnapshot(name, origin = '', button = null) {
    const confirmKey = origin === 'external' ? 'snapshots.confirm_delete_external' : 'snapshots.confirm_delete';
    const confirmFallback = origin === 'external'
        ? 'This snapshot was not created by the plugin. Delete external snapshot {name}?'
        : 'Delete snapshot {name}?';

    const confirmed = await zssConfirmAction({
        title: t('common.delete', 'Delete'),
        message: t(confirmKey, confirmFallback, { name }),
        detail: name,
        confirmText: t('common.delete', 'Delete'),
        danger: true,
    });
    if (!confirmed) return;

    const restoreButton = zssSetButtonBusy(button, t('snapshots.action_working', 'Working...'));

    try {
        const result = await postJson('../api/snapshot-delete.php', { name, confirm: name });

        if (result.ok) {
            zssFlashRow(button);
            zssToast({ type: 'success', title: t('snapshots.delete_success', 'Snapshot deleted') });
            window.setTimeout(() => loadSnapshots(dataset), 450);
        } else {
            zssToast({
                type: 'error',
                title: t('snapshots.delete_failed', 'Delete failed'),
                message: result.error?.message || t('common.unknown_error', 'Unknown error'),
            });
        }
    } catch (error) {
        zssToast({ type: 'error', title: t('common.request_failed', 'Request failed'), message: error.message });
    } finally {
        restoreButton();
    }
}

async function addHold(name, button = null) {
    const confirmed = await zssConfirmAction({
        title: t('snapshots.hold_dialog_title', 'Set read-only protection'),
        message: t('snapshots.hold_dialog_message', 'This will add the autosnap hold tag. The snapshot cannot be deleted until protection is released.'),
        detail: name,
        confirmText: t('snapshots.hold_dialog_confirm', 'Set read-only'),
    });
    if (!confirmed) return;

    const restoreButton = zssSetButtonBusy(button, t('snapshots.action_working', 'Working...'));

    try {
        const result = await postJson('../api/snapshot-hold.php', { name });

        if (result.ok) {
            zssFlashRow(button);
            zssToast({
                type: 'success',
                title: t('snapshots.hold_success', 'Protection enabled'),
                message: t('snapshots.hold_success_detail', 'Permanent manual hold tag zss_manual was added.'),
            });
            window.setTimeout(() => loadSnapshots(dataset), 450);
        } else {
            zssToast({
                type: 'error',
                title: t('snapshots.hold_failed', 'Failed to enable protection'),
                message: result.error?.message || t('common.unknown_error', 'Unknown error'),
            });
        }
    } catch (error) {
        zssToast({
            type: 'error',
            title: t('common.request_failed', 'Request failed'),
            message: error.message,
        });
    } finally {
        restoreButton();
    }
}

async function releaseHold(name, holdTags = [], button = null) {
    let tag = holdTags.length === 1 ? holdTags[0] : '';

    if (holdTags.length !== 1) {
        tag = await zssConfirmAction({
            title: t('snapshots.release', 'Release hold'),
            message: t('snapshots.release_hold_tag_prompt', 'Hold tags: {tags}\nEnter the hold tag to release:', { tags: holdTags.join(', ') }),
            detail: name,
            inputLabel: t('snapshots.release', 'Release hold'),
            inputValue: holdTags[0] || '',
            confirmText: t('snapshots.release', 'Release hold'),
        });
        if (tag === false) return;
    }

    if (!tag) {
        zssToast({
            type: 'error',
            title: t('snapshots.release_failed', 'Failed to release protection'),
            message: t('snapshots.release_hold_tag_required', 'Hold tag is required.'),
        });
        return;
    }

    const confirmed = await zssConfirmAction({
        title: t('snapshots.release', 'Release hold'),
        message: t('snapshots.confirm_release_tag', 'Release hold tag {tag} for snapshot {name}?', { name, tag }),
        detail: `${name}:${tag}`,
        confirmText: t('snapshots.release', 'Release hold'),
    });
    if (!confirmed) return;

    const restoreButton = zssSetButtonBusy(button, t('snapshots.action_working', 'Working...'));

    try {
        const result = await postJson('../api/snapshot-release.php', { name, tag, confirm: `${name}:${tag}` });

        if (result.ok) {
            zssFlashRow(button);
            zssToast({ type: 'success', title: t('snapshots.release_success', 'Protection released') });
            window.setTimeout(() => loadSnapshots(dataset), 450);
        } else {
            zssToast({
                type: 'error',
                title: t('snapshots.release_failed', 'Failed to release protection'),
                message: result.error?.message || t('common.unknown_error', 'Unknown error'),
            });
        }
    } catch (error) {
        zssToast({ type: 'error', title: t('common.request_failed', 'Request failed'), message: error.message });
    } finally {
        restoreButton();
    }
}

async function rollbackSnapshot(name, button = null) {
    const typedName = await zssConfirmAction({
        title: t('snapshots.rollback', 'Rollback'),
        message: t('snapshots.confirm_rollback', 'Rollback dataset to snapshot {name}? Changes after this snapshot may be lost.', { name }),
        detail: name,
        inputLabel: t('snapshots.confirm_rollback_input', 'Type the full snapshot name to confirm rollback:'),
        inputValue: '',
        confirmText: t('snapshots.rollback', 'Rollback'),
        danger: true,
    });
    if (typedName === false) return;

    if (typedName !== name) {
        zssToast({
            type: 'error',
            title: t('snapshots.rollback_failed', 'Rollback failed'),
            message: t('snapshots.rollback_confirm_mismatch', 'Snapshot name does not match. Rollback cancelled.'),
        });
        return;
    }

    const restoreButton = zssSetButtonBusy(button, t('snapshots.action_working', 'Working...'));

    try {
        const result = await postJson('../api/snapshot-rollback.php', { name, confirm: typedName });

        if (result.ok) {
            zssFlashRow(button);
            zssToast({ type: 'success', title: t('snapshots.rollback_success', 'Rollback completed') });
            window.setTimeout(() => loadSnapshots(dataset), 450);
        } else {
            zssToast({
                type: 'error',
                title: t('snapshots.rollback_failed', 'Rollback failed'),
                message: result.error?.message || t('common.unknown_error', 'Unknown error'),
            });
        }
    } catch (error) {
        zssToast({ type: 'error', title: t('common.request_failed', 'Request failed'), message: error.message });
    } finally {
        restoreButton();
    }
}

function updateSnapshotCrumb(name) {
    const crumb = document.getElementById('snapshots-dataset-crumb');
    if (!crumb) return;
    if (!name) {
        crumb.textContent = '';
        return;
    }
    const parts = name.split('/');
    const last = parts.pop() || '';
    const parent = parts.join(' / ');
    crumb.innerHTML = `${parent ? `${escapeHtml(parent)} / ` : ''}<strong>${escapeHtml(last)}</strong> / ${escapeHtml(t('snapshots.context.breadcrumb', 'snapshots'))}`;
}

function applyContextStripFallback(message) {
    const statusEl = document.getElementById('snapshots-dataset-status');
    const retentionEl = document.getElementById('snapshots-retention');
    const protectedEl = document.getElementById('snapshots-protected-count');
    if (statusEl) {
        statusEl.className = 'zss-badge zss-badge-muted';
        statusEl.textContent = message;
    }
    if (retentionEl) retentionEl.textContent = '-';
    if (protectedEl) protectedEl.textContent = '-';
}

function updateSnapshotContext(datasets) {
    const current = datasets.find(ds => ds.name === dataset);
    const statusEl = document.getElementById('snapshots-dataset-status');
    const retentionEl = document.getElementById('snapshots-retention');
    const protectedEl = document.getElementById('snapshots-protected-count');
    if (!current) {
        applyContextStripFallback(t('snapshots.context.not_found', 'Dataset not found'));
        return;
    }
    if (statusEl) {
        statusEl.className = `zss-badge ${current.enabled ? 'zss-badge-success' : 'zss-badge-muted'}`;
        statusEl.textContent = current.enabled ? t('common.enabled', 'Enabled') : t('common.disabled', 'Disabled');
    }
    if (retentionEl) retentionEl.textContent = `${current.keep ?? '-'} · ${frequencyLabel(current.frequency)}`;
    if (protectedEl) protectedEl.textContent = current.held_snapshot_count ?? 0;
}

async function loadSnapshotDatasetSelector() {
    const select = document.getElementById('snapshot-dataset-select');
    if (!select) return;

    const data = await fetchData('../api/datasets.php');
    if (!data || !data.ok) {
        applyContextStripFallback(t('common.load_failed', 'Load failed'));
        return;
    }

    const datasets = data.data || [];
    datasets.forEach(ds => {
        const option = document.createElement('option');
        option.value = ds.name;
        option.textContent = ds.name;
        select.appendChild(option);
    });

    if (dataset && datasets.some(ds => ds.name === dataset)) {
        select.value = dataset;
    }

    updateSnapshotContext(datasets);
    updateSnapshotCrumb(dataset);

    select.addEventListener('change', () => {
        const target = select.value ? `snapshots.php?dataset=${encodeURIComponent(select.value)}` : 'snapshots.php';
        window.location.href = withLang(target);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    loadSnapshotDatasetSelector();
    if (dataset) {
        loadSnapshots(dataset);
    } else {
        loadDatasetList();
    }
});

// Delegated handler for timeline action buttons. Buttons carry JSON-encoded
// snapshot names in data attributes; see renderSnapshotActions.
document.getElementById('snapshots-timeline').addEventListener('click', function(event) {
    const button = event.target.closest('button[data-action][data-name]');
    if (!button) {
        return;
    }

    const action = button.dataset.action;
    let name = '';
    try {
        name = JSON.parse(button.dataset.name || '""');
    } catch (error) {
        name = '';
    }

    if (!name) {
        zssToast({
            type: 'error',
            title: t('common.request_failed', 'Request failed'),
            message: t('snapshots.invalid_action_name', 'Invalid snapshot name'),
        });
        return;
    }

    if (action === 'release') {
        let holdTags = [];
        try {
            holdTags = JSON.parse(button.dataset.holdTags || '[]');
        } catch (error) {
            holdTags = [];
        }
        releaseHold(name, holdTags, button);
        return;
    }

    if (action === 'hold') {
        addHold(name, button);
        return;
    }

    if (action === 'delete') {
        deleteSnapshot(name, button.dataset.origin || '', button);
        return;
    }

    if (action === 'rollback') {
        rollbackSnapshot(name, button);
    }
});
