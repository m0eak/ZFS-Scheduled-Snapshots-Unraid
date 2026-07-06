function snapshotOriginLabel(origin) {
    if (origin === 'autosnap') {
        return t('snapshots.origin_autosnap', 'Auto');
    }

    if (origin === 'plugin_manual') {
        return t('snapshots.origin_plugin_manual', 'Plugin manual');
    }

    return t('snapshots.origin_external', 'External');
}

function renderOriginBadge(origin) {
    const className = origin === 'external' ? 'zss-badge-muted' : origin === 'plugin_manual' ? 'zss-badge-info' : 'zss-badge-success';
    return `<span class="zss-badge ${className}">${escapeHtml(snapshotOriginLabel(origin))}</span>`;
}

function renderSnapshotStatus(snap) {
    if (!snap.held) {
        return `<span class="zss-badge zss-badge-success">${escapeHtml(t('common.normal', 'Normal'))}</span>`;
    }

    const holdTags = Array.isArray(snap.hold_tags) ? snap.hold_tags : [];
    const label = holdTags.length > 0
        ? `${t('common.protected', 'Protected')}: ${holdTags.join(', ')}`
        : t('common.protected', 'Protected');

    return `<span class="zss-badge zss-badge-warning">${escapeHtml(label)}</span>`;
}

function renderSnapshotActions(snap) {
    const actions = snap.actions || {};
    const encodedName = escapeHtml(JSON.stringify(snap.name));
    const escapedOrigin = escapeHtml(snap.origin || 'external');
    const escapedHoldTags = escapeHtml(JSON.stringify(snap.hold_tags || []));
    const buttons = [];

    if (snap.operable === false) {
        return `<span class="zss-badge zss-badge-muted">${escapeHtml(t('snapshots.read_only_external', 'Read only'))}</span>`;
    }

    if (actions.release) {
        buttons.push(`<button class="zss-btn zss-btn-secondary zss-btn-small" data-action="release" data-name="${encodedName}" data-hold-tags="${escapedHoldTags}">${escapeHtml(t('snapshots.release', 'Release hold'))}</button>`);
    }

    if (actions.hold) {
        buttons.push(`<button class="zss-btn zss-btn-secondary zss-btn-small" data-action="hold" data-name="${encodedName}">${escapeHtml(t('snapshots.hold', 'Set read-only'))}</button>`);
    }

    if (actions.rollback) {
        buttons.push(`<button class="zss-btn zss-btn-warning zss-btn-small" data-action="rollback" data-name="${encodedName}">${escapeHtml(t('snapshots.rollback', 'Rollback'))}</button>`);
    }

    if (actions.delete) {
        buttons.push(`<button class="zss-btn zss-btn-danger zss-btn-small" data-action="delete" data-name="${encodedName}" data-origin="${escapedOrigin}">${escapeHtml(t('common.delete', 'Delete'))}</button>`);
    }

    if (buttons.length === 0) {
        return `<span class="zss-badge zss-badge-muted">${escapeHtml(t('common.none', 'None'))}</span>`;
    }

    return `<div class="zss-action-row">${buttons.join(' ')}</div>`;
}

function renderDatasetListHead() {
    document.getElementById('snapshots-table-head').innerHTML = `
        <th>${escapeHtml(t('table.dataset', 'Dataset'))}</th>
        <th>${escapeHtml(t('table.snapshot_count', 'Snapshots'))}</th>
        <th>${escapeHtml(t('table.status', 'Status'))}</th>
        <th>${escapeHtml(t('table.actions', 'Actions'))}</th>
    `;
}

function renderSnapshotListHead() {
    document.getElementById('snapshots-table-head').innerHTML = `
        <th>${escapeHtml(t('table.snapshot_name', 'Snapshot Name'))}</th>
        <th>${escapeHtml(t('snapshots.source', 'Source'))}</th>
        <th>${escapeHtml(t('table.created_at', 'Created At'))}</th>
        <th>${escapeHtml(t('table.status', 'Status'))}</th>
        <th>${escapeHtml(t('table.actions', 'Actions'))}</th>
    `;
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

async function loadSnapshots(datasetName) {
    renderSnapshotListHead();
    const data = await fetchData(`../api/snapshots.php?name=${encodeURIComponent(datasetName)}`);

    if (!data || !data.ok) {
        renderTableMessage('snapshots-table', `${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.unknown_error', 'Unknown error')}`, 5);
        return;
    }

    const tbody = document.getElementById('snapshots-table');
    tbody.innerHTML = '';

    if (!data.data || data.data.length === 0) {
        renderTableMessage('snapshots-table', t('snapshots.empty', 'No snapshots'), 5);
        return;
    }

    data.data.forEach(snap => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><span class="zss-code-text">${escapeHtml(snap.short_name || snap.name)}</span></td>
            <td>${renderOriginBadge(snap.origin || 'external')}</td>
            <td>${snap.created_at ? escapeHtml(formatTimestamp(snap.created_at)) : '-'}</td>
            <td>${renderSnapshotStatus(snap)}</td>
            <td>${renderSnapshotActions(snap)}</td>
        `;
        tbody.appendChild(row);
    });
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
                message: t('snapshots.hold_success_detail', 'Snapshot hold tag autosnap was added.'),
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

document.addEventListener('DOMContentLoaded', function() {
    if (dataset) {
        loadSnapshots(dataset);
    } else {
        loadDatasetList();
    }
});

document.getElementById('snapshots-table').addEventListener('click', function(event) {
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
