<?php
$currentPage = 'snapshots';
$dataset = $_GET['dataset'] ?? '';
require_once __DIR__ . '/layout/header.php';
?>

<h2><?php echo htmlspecialchars(zss_t('snapshots.title')); ?></h2>

<?php if ($dataset): ?>
    <p><?php echo htmlspecialchars(zss_t('snapshots.current_dataset')); ?>: <strong><?php echo htmlspecialchars($dataset); ?></strong></p>
    <p><?php echo htmlspecialchars(zss_t('snapshots.all_snapshots_notice')); ?></p>
    <p><a class="page-link" href="<?php echo htmlspecialchars(withLang('snapshots-list.php')); ?>">← <?php echo htmlspecialchars(zss_t('snapshots.back_all')); ?></a></p>
    <p style="margin-top: 10px;">
        <button class="btn btn-small" onclick="createSnapshot()"><?php echo htmlspecialchars(zss_t('snapshots.create')); ?></button>
    </p>
<?php endif; ?>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th><?php echo htmlspecialchars(zss_t('table.snapshot_name')); ?></th>
                <?php if (!$dataset): ?><th><?php echo htmlspecialchars(zss_t('table.dataset')); ?></th><?php endif; ?>
                <th><?php echo htmlspecialchars(zss_t('snapshots.source')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.created_at')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.status')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.actions')); ?></th>
            </tr>
        </thead>
        <tbody id="snapshots-table">
            <tr>
                <td colspan="<?php echo $dataset ? '5' : '6'; ?>">
                    <?php echo htmlspecialchars($dataset ? zss_t('common.loading') : zss_t('snapshots.select_dataset')); ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<script>
let currentSnapshots = [];
const dataset = <?php echo json_encode($dataset); ?>;

function snapshotOriginLabel(origin) {
    if (origin === 'autosnap') {
        return t('snapshots.origin_autosnap', 'Auto');
    }

    if (origin === 'plugin_manual') {
        return t('snapshots.origin_plugin_manual', 'Plugin manual');
    }

    return t('snapshots.origin_external', 'External');
}

function renderSnapshotActions(snap) {
    const actions = snap.actions || {};
    const escapedName = escapeHtml(snap.name);
    const escapedOrigin = escapeHtml(snap.origin || 'external');
    const escapedHoldTags = escapeHtml(JSON.stringify(snap.hold_tags || []));
    const buttons = [];

    if (actions.release) {
        buttons.push(`<button class="btn btn-small" data-action="release" data-name="${escapedName}" data-hold-tags="${escapedHoldTags}">${t('snapshots.release', 'Release hold')}</button>`);
    }

    if (actions.hold) {
        buttons.push(`<button class="btn btn-small" data-action="hold" data-name="${escapedName}">${t('snapshots.hold', 'Set read-only')}</button>`);
    }

    if (actions.rollback) {
        buttons.push(`<button class="btn btn-small btn-secondary" data-action="rollback" data-name="${escapedName}">${t('snapshots.rollback', 'Rollback')}</button>`);
    }

    if (actions.delete) {
        buttons.push(`<button class="btn btn-small btn-secondary" data-action="delete" data-name="${escapedName}" data-origin="${escapedOrigin}">${t('common.delete', 'Delete')}</button>`);
    }

    if (buttons.length === 0) {
        return `<span class="status disabled">${t('common.none', 'None')}</span>`;
    }

    return buttons.join(' ');
}

async function loadSnapshots(datasetName) {
    const tbody = document.getElementById('snapshots-table');
    const data = await fetchData(`../api/snapshots.php?name=${encodeURIComponent(datasetName)}`);
    
    if (data && data.ok) {
        currentSnapshots = data.data;
        tbody.innerHTML = '';
        
        if (data.data.length === 0) {
            renderTableMessage('snapshots-table', t('snapshots.empty', 'No snapshots'), dataset ? 5 : 6);
        } else {
            data.data.forEach(snap => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHtml(snap.short_name)}</td>
                    ${dataset ? '' : `<td>${escapeHtml(snap.dataset)}</td>`}
                    <td>${snapshotOriginLabel(snap.origin)}</td>
                    <td>${snap.created_at ? formatTimestamp(snap.created_at) : '-'}</td>
                    <td><span class="status ${snap.held ? 'hold' : 'enabled'}">${snap.held ? t('common.protected', 'Protected') : t('common.normal', 'Normal')}</span></td>
                    <td>${renderSnapshotActions(snap)}</td>
                `;
                tbody.appendChild(row);
            });
        }
    } else {
        renderTableMessage('snapshots-table', `${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.unknown_error', 'Unknown error')}`, datasetName ? 5 : 6, 'table-message error');
    }
}

async function loadDatasetList() {
    const tbody = document.getElementById('snapshots-table');
    const data = await fetchData('../api/datasets.php');
    
    if (data && data.ok) {
        tbody.innerHTML = '';

        if (!data.data || data.data.length === 0) {
            renderTableMessage('snapshots-table', t('snapshots.dataset_empty', 'No datasets'), 6);
            return;
        }
        
        data.data.forEach(ds => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="3">${escapeHtml(ds.name)}</td>
                <td>${ds.snapshot_count}</td>
                <td><span class="status ${ds.enabled ? 'enabled' : 'disabled'}">${ds.enabled ? t('common.enabled', 'Enabled') : t('common.disabled', 'Disabled')}</span></td>
                <td><a href="${withLang(`snapshots-list.php?dataset=${encodeURIComponent(ds.name)}`)}" class="btn btn-small">${t('snapshots.view', 'View snapshots')}</a></td>
            `;
            tbody.appendChild(row);
        });
    } else {
        renderTableMessage('snapshots-table', `${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.unknown_error', 'Unknown error')}`, 6, 'table-message error');
    }
}

async function createSnapshot() {
    if (!confirm(t('snapshots.confirm_create', 'Create a snapshot manually now?'))) return;
    
    try {
        const result = await postJson('../api/snapshot-create.php', { name: dataset });
        
        if (result.ok) {
            alert(t('snapshots.create_success', 'Snapshot created'));
            loadSnapshots(dataset);
        } else {
            alert(`${t('snapshots.create_failed', 'Create failed')}: ${result.error?.message || t('common.unknown_error', 'Unknown error')}`);
        }
    } catch (error) {
        alert(`${t('common.request_failed', 'Request failed')}: ${error.message}`);
    }
}

async function deleteSnapshot(name, origin = '') {
    const confirmKey = origin === 'external' ? 'snapshots.confirm_delete_external' : 'snapshots.confirm_delete';
    const confirmFallback = origin === 'external'
        ? 'This snapshot was not created by the plugin. Delete external snapshot {name}?'
        : 'Delete snapshot {name}?';

    if (!confirm(t(confirmKey, confirmFallback, { name }))) return;
    
    try {
        const result = await postJson('../api/snapshot-delete.php', { name });
        
        if (result.ok) {
            alert(t('snapshots.delete_success', 'Snapshot deleted'));
            loadSnapshots(dataset);
        } else {
            alert(`${t('snapshots.delete_failed', 'Delete failed')}: ${result.error?.message || t('common.unknown_error', 'Unknown error')}`);
        }
    } catch (error) {
        alert(`${t('common.request_failed', 'Request failed')}: ${error.message}`);
    }
}

async function addHold(name) {
    if (!confirm(t('snapshots.confirm_hold', 'Add read-only protection to snapshot {name}?', { name }))) return;
    
    try {
        const result = await postJson('../api/snapshot-hold.php', { name });
        
        if (result.ok) {
            alert(t('snapshots.hold_success', 'Protection enabled'));
            loadSnapshots(dataset);
        } else {
            alert(`${t('snapshots.hold_failed', 'Failed to enable protection')}: ${result.error?.message || t('common.unknown_error', 'Unknown error')}`);
        }
    } catch (error) {
        alert(`${t('common.request_failed', 'Request failed')}: ${error.message}`);
    }
}

async function releaseHold(name, holdTags = []) {
    let tag = holdTags.length === 1 ? holdTags[0] : '';

    if (holdTags.length !== 1) {
        tag = prompt(t('snapshots.release_hold_tag_prompt', 'Hold tags: {tags}\nEnter the hold tag to release:', { tags: holdTags.join(', ') }), holdTags[0] || '');
    }

    if (!tag) {
        alert(t('snapshots.release_hold_tag_required', 'Hold tag is required.'));
        return;
    }

    if (!confirm(t('snapshots.confirm_release_tag', 'Release hold tag {tag} for snapshot {name}?', { name, tag }))) return;
    
    try {
        const result = await postJson('../api/snapshot-release.php', { name, tag });
        
        if (result.ok) {
            alert(t('snapshots.release_success', 'Protection released'));
            loadSnapshots(dataset);
        } else {
            alert(`${t('snapshots.release_failed', 'Failed to release protection')}: ${result.error?.message || t('common.unknown_error', 'Unknown error')}`);
        }
    } catch (error) {
        alert(`${t('common.request_failed', 'Request failed')}: ${error.message}`);
    }
}

async function rollbackSnapshot(name) {
    if (!confirm(t('snapshots.confirm_rollback', 'Rollback dataset to snapshot {name}? Changes after this snapshot may be lost.', { name }))) return;

    const typedName = prompt(t('snapshots.confirm_rollback_input', 'Type the full snapshot name to confirm rollback:'), '');
    if (typedName !== name) {
        alert(t('snapshots.rollback_confirm_mismatch', 'Snapshot name does not match. Rollback cancelled.'));
        return;
    }

    try {
        const result = await postJson('../api/snapshot-rollback.php', { name });

        if (result.ok) {
            alert(t('snapshots.rollback_success', 'Rollback completed'));
            loadSnapshots(dataset);
        } else {
            alert(`${t('snapshots.rollback_failed', 'Rollback failed')}: ${result.error?.message || t('common.unknown_error', 'Unknown error')}`);
        }
    } catch (error) {
        alert(`${t('common.request_failed', 'Request failed')}: ${error.message}`);
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
    const name = button.dataset.name;

    if (action === 'release') {
        let holdTags = [];
        try {
            holdTags = JSON.parse(button.dataset.holdTags || '[]');
        } catch (error) {
            holdTags = [];
        }
        releaseHold(name, holdTags);
        return;
    }

    if (action === 'hold') {
        addHold(name);
        return;
    }

    if (action === 'delete') {
        deleteSnapshot(name, button.dataset.origin || '');
        return;
    }

    if (action === 'rollback') {
        rollbackSnapshot(name);
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
