<?php
$currentPage = 'snapshots';
$dataset = $_GET['dataset'] ?? '';
require_once __DIR__ . '/layout/header.php';
?>

<h2><?php echo htmlspecialchars(zss_t('snapshots.title')); ?></h2>

<?php if ($dataset): ?>
    <p><?php echo htmlspecialchars(zss_t('snapshots.current_dataset')); ?>: <strong><?php echo htmlspecialchars($dataset); ?></strong></p>
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
                <th><?php echo htmlspecialchars(zss_t('table.created_at')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.status')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.actions')); ?></th>
            </tr>
        </thead>
        <tbody id="snapshots-table">
            <tr>
                <td colspan="<?php echo $dataset ? '4' : '5'; ?>">
                    <?php echo htmlspecialchars($dataset ? zss_t('common.loading') : zss_t('snapshots.select_dataset')); ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<script>
let currentSnapshots = [];
const dataset = <?php echo json_encode($dataset); ?>;

async function loadSnapshots(datasetName) {
    const tbody = document.getElementById('snapshots-table');
    const data = await fetchData(`../api/snapshots.php?name=${encodeURIComponent(datasetName)}`);
    
    if (data && data.ok) {
        currentSnapshots = data.data;
        tbody.innerHTML = '';
        
        if (data.data.length === 0) {
            renderTableMessage('snapshots-table', t('snapshots.empty', 'No snapshots'), dataset ? 4 : 5);
        } else {
            data.data.forEach(snap => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${snap.short_name}</td>
                    ${dataset ? '' : `<td>${snap.dataset}</td>`}
                    <td>${snap.created_at ? formatTimestamp(snap.created_at) : '-'}</td>
                    <td><span class="status ${snap.held ? 'hold' : 'enabled'}">${snap.held ? t('common.protected', 'Protected') : t('common.normal', 'Normal')}</span></td>
                    <td>
                        ${snap.held ?
                            `<button class="btn btn-small" data-action="release" data-name="${escapeHtml(snap.name)}">${t('snapshots.release', 'Release hold')}</button>` :
                            `<button class="btn btn-small" data-action="hold" data-name="${escapeHtml(snap.name)}">${t('snapshots.hold', 'Set read-only')}</button>
                             <button class="btn btn-small btn-secondary" data-action="delete" data-name="${escapeHtml(snap.name)}">${t('common.delete', 'Delete')}</button>`
                        }
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
    } else {
        renderTableMessage('snapshots-table', `${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.unknown_error', 'Unknown error')}`, datasetName ? 4 : 5, 'table-message error');
    }
}

async function loadDatasetList() {
    const tbody = document.getElementById('snapshots-table');
    const data = await fetchData('../api/datasets.php');
    
    if (data && data.ok) {
        tbody.innerHTML = '';

        if (!data.data || data.data.length === 0) {
            renderTableMessage('snapshots-table', t('snapshots.dataset_empty', 'No datasets'), 5);
            return;
        }
        
        data.data.forEach(ds => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="2">${ds.name}</td>
                <td>${ds.snapshot_count}</td>
                <td><span class="status ${ds.enabled ? 'enabled' : 'disabled'}">${ds.enabled ? t('common.enabled', 'Enabled') : t('common.disabled', 'Disabled')}</span></td>
                <td><a href="${withLang(`snapshots-list.php?dataset=${encodeURIComponent(ds.name)}`)}" class="btn btn-small">${t('snapshots.view', 'View snapshots')}</a></td>
            `;
            tbody.appendChild(row);
        });
    } else {
        renderTableMessage('snapshots-table', `${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.unknown_error', 'Unknown error')}`, 5, 'table-message error');
    }
}

async function createSnapshot() {
    if (!confirm(t('snapshots.confirm_create', 'Create a snapshot manually now?'))) return;
    
    try {
        const response = await fetch(withLang('../api/snapshot-create.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: dataset }),
        });
        
        const result = await response.json();
        
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

async function deleteSnapshot(name) {
    if (!confirm(t('snapshots.confirm_delete', 'Delete snapshot {name}?', { name }))) return;
    
    try {
        const response = await fetch(withLang('../api/snapshot-delete.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name }),
        });
        
        const result = await response.json();
        
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
        const response = await fetch(withLang('../api/snapshot-hold.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name }),
        });
        
        const result = await response.json();
        
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

async function releaseHold(name) {
    if (!confirm(t('snapshots.confirm_release', 'Release read-only protection for snapshot {name}?', { name }))) return;
    
    try {
        const response = await fetch(withLang('../api/snapshot-release.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name }),
        });
        
        const result = await response.json();
        
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
        releaseHold(name);
        return;
    }

    if (action === 'hold') {
        addHold(name);
        return;
    }

    if (action === 'delete') {
        deleteSnapshot(name);
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
