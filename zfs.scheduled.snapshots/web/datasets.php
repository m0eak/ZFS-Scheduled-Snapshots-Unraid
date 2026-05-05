<?php
$currentPage = 'datasets';
require_once __DIR__ . '/layout/header.php';
?>

<h2><?php echo htmlspecialchars(zss_t('datasets.title')); ?></h2>

<div class="card" style="margin-bottom: 16px;">
    <h3><?php echo htmlspecialchars(zss_t('datasets.create.title')); ?></h3>
    <p class="muted"><?php echo htmlspecialchars(zss_t('datasets.create.description')); ?></p>
    <div class="form-row">
        <label class="form-label" for="new-dataset-parent"><?php echo htmlspecialchars(zss_t('datasets.create.parent')); ?></label>
        <select id="new-dataset-parent" class="form-select"></select>
    </div>
    <div class="form-row">
        <label class="form-label" for="new-dataset-child"><?php echo htmlspecialchars(zss_t('datasets.create.child')); ?></label>
        <input type="text" id="new-dataset-child" class="form-input" placeholder="appdata/media">
    </div>
    <div class="form-grid">
        <div class="form-row">
            <label class="form-label" for="new-dataset-mount"><?php echo htmlspecialchars(zss_t('datasets.create.mount')); ?></label>
            <select id="new-dataset-mount" class="form-select">
                <option value="yes"><?php echo htmlspecialchars(zss_t('common.yes')); ?></option>
                <option value="no"><?php echo htmlspecialchars(zss_t('common.no')); ?></option>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="new-dataset-mountpoint"><?php echo htmlspecialchars(zss_t('datasets.create.mountpoint')); ?></label>
            <input type="text" id="new-dataset-mountpoint" class="form-input" placeholder="<?php echo htmlspecialchars(zss_t('datasets.create.mountpoint_placeholder')); ?>">
            <div class="form-help" id="new-dataset-default-mountpoint"></div>
        </div>
        <div class="form-row">
            <label class="form-label" for="new-dataset-atime"><?php echo htmlspecialchars(zss_t('datasets.create.atime')); ?></label>
            <select id="new-dataset-atime" class="form-select">
                <option value="inherit"><?php echo htmlspecialchars(zss_t('common.inherit')); ?></option>
                <option value="off">Off</option>
                <option value="on">On</option>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="new-dataset-casesensitivity"><?php echo htmlspecialchars(zss_t('datasets.create.casesensitivity')); ?></label>
            <select id="new-dataset-casesensitivity" class="form-select">
                <option value="inherit"><?php echo htmlspecialchars(zss_t('datasets.create.zfs_default')); ?></option>
                <option value="sensitive">Sensitive</option>
                <option value="insensitive">Insensitive</option>
                <option value="mixed">Mixed</option>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="new-dataset-compression"><?php echo htmlspecialchars(zss_t('datasets.create.compression')); ?></label>
            <select id="new-dataset-compression" class="form-select">
                <option value="inherit"><?php echo htmlspecialchars(zss_t('common.inherit')); ?></option>
                <option value="off">Off</option>
                <option value="lz4">lz4</option>
                <option value="gzip">gzip</option>
                <option value="zstd">zstd</option>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="new-dataset-quota"><?php echo htmlspecialchars(zss_t('datasets.create.quota')); ?></label>
            <div style="display: flex; gap: 8px;">
                <input type="number" id="new-dataset-quota" class="form-input" min="0" placeholder="0">
                <select id="new-dataset-quota-unit" class="form-select" style="max-width: 120px;">
                    <option value="M">MiB</option>
                    <option value="G">GiB</option>
                    <option value="T">TiB</option>
                </select>
            </div>
            <div class="form-help"><?php echo htmlspecialchars(zss_t('datasets.create.quota_help')); ?></div>
        </div>
    </div>
    <button type="button" class="btn btn-primary" onclick="createDataset()">
        <?php echo htmlspecialchars(zss_t('datasets.create.action')); ?>
    </button>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th><?php echo htmlspecialchars(zss_t('table.dataset')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.status')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.frequency')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.keep')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.keep_days')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.readonly')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.snapshot_count')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.last_snapshot')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.actions')); ?></th>
            </tr>
        </thead>
        <tbody id="datasets-table">
            <tr>
                <td colspan="9"><?php echo htmlspecialchars(zss_t('common.loading')); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div id="edit-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo htmlspecialchars(zss_t('datasets.modal.title')); ?></h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="edit-form">
                <input type="hidden" id="dataset-name">
                
                <div class="form-row">
                    <label class="form-label">
                        <input type="checkbox" id="config-enabled">
                        <?php echo htmlspecialchars(zss_t('datasets.fields.enable')); ?>
                    </label>
                </div>

                <div class="form-row">
                    <label class="form-label" for="config-frequency"><?php echo htmlspecialchars(zss_t('datasets.fields.frequency')); ?></label>
                    <select id="config-frequency" class="form-select">
                        <option value="5min"><?php echo htmlspecialchars(zss_t('frequency.5min')); ?></option>
                        <option value="15min"><?php echo htmlspecialchars(zss_t('frequency.15min')); ?></option>
                        <option value="hourly"><?php echo htmlspecialchars(zss_t('frequency.hourly')); ?></option>
                        <option value="daily"><?php echo htmlspecialchars(zss_t('frequency.daily')); ?></option>
                        <option value="weekly"><?php echo htmlspecialchars(zss_t('frequency.weekly')); ?></option>
                        <option value="monthly"><?php echo htmlspecialchars(zss_t('frequency.monthly')); ?></option>
                    </select>
                </div>

                <div class="form-row">
                    <label class="form-label" for="config-keep"><?php echo htmlspecialchars(zss_t('datasets.fields.keep')); ?></label>
                    <input type="number" id="config-keep" class="form-input" min="1" value="31">
                </div>

                <div class="form-row" id="row-time" style="display: none;">
                    <label class="form-label" for="config-time"><?php echo htmlspecialchars(zss_t('datasets.fields.time')); ?></label>
                    <input type="time" id="config-time" class="form-input" value="00:00">
                </div>

                <div class="form-row" id="row-day" style="display: none;">
                    <label class="form-label" id="label-day"><?php echo htmlspecialchars(zss_t('datasets.fields.day')); ?></label>
                    <select id="config-day-weekly" class="form-select" style="display: none;">
                        <option value="1"><?php echo htmlspecialchars(zss_t('weekday.1')); ?></option>
                        <option value="2"><?php echo htmlspecialchars(zss_t('weekday.2')); ?></option>
                        <option value="3"><?php echo htmlspecialchars(zss_t('weekday.3')); ?></option>
                        <option value="4"><?php echo htmlspecialchars(zss_t('weekday.4')); ?></option>
                        <option value="5"><?php echo htmlspecialchars(zss_t('weekday.5')); ?></option>
                        <option value="6"><?php echo htmlspecialchars(zss_t('weekday.6')); ?></option>
                        <option value="7"><?php echo htmlspecialchars(zss_t('weekday.7')); ?></option>
                    </select>
                    <select id="config-day-monthly" class="form-select" style="display: none;"></select>
                </div>

                <div class="form-row">
                    <label class="form-label">
                        <input type="checkbox" id="config-readonly">
                        <?php echo htmlspecialchars(zss_t('datasets.fields.readonly')); ?>
                    </label>
                </div>

                <div class="form-row">
                    <label class="form-label" for="config-retain-days"><?php echo htmlspecialchars(zss_t('datasets.fields.retain_days')); ?></label>
                    <input type="number" id="config-retain-days" class="form-input" min="0" value="0">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()"><?php echo htmlspecialchars(zss_t('common.cancel')); ?></button>
            <button type="button" class="btn btn-primary" onclick="saveConfig()"><?php echo htmlspecialchars(zss_t('common.save')); ?></button>
        </div>
    </div>
</div>

<script>
const monthlySelect = document.getElementById('config-day-monthly');
for (let i = 1; i <= 31; i++) {
    const option = document.createElement('option');
    option.value = i;
    option.textContent = ZSS_LOCALE === 'zh-CN' ? `${i} 号` : `${i}`;
    monthlySelect.appendChild(option);
}

document.getElementById('config-frequency').addEventListener('change', function(e) {
    updateFieldVisibility(e.target.value);
});

document.getElementById('new-dataset-mount').addEventListener('change', function(e) {
    const mountpoint = document.getElementById('new-dataset-mountpoint');
    mountpoint.disabled = e.target.value === 'no';
    if (mountpoint.disabled) {
        mountpoint.value = '';
    }
    updateDefaultMountpointHint();
});

document.getElementById('new-dataset-parent').addEventListener('change', updateDefaultMountpointHint);
document.getElementById('new-dataset-child').addEventListener('input', updateDefaultMountpointHint);

function updateFieldVisibility(frequency) {
    const timeRow = document.getElementById('row-time');
    const dayRow = document.getElementById('row-day');
    const weeklySelect = document.getElementById('config-day-weekly');
    const monthlySelect = document.getElementById('config-day-monthly');
    const dayLabel = document.getElementById('label-day');

    if (frequency === 'daily' || frequency === 'weekly' || frequency === 'monthly') {
        timeRow.style.display = 'block';
    } else {
        timeRow.style.display = 'none';
    }

    if (frequency === 'weekly' || frequency === 'monthly') {
        dayRow.style.display = 'block';
        if (frequency === 'weekly') {
            dayLabel.textContent = t('datasets.fields.weekday', 'Weekday');
            weeklySelect.style.display = 'block';
            monthlySelect.style.display = 'none';
        } else {
            dayLabel.textContent = t('datasets.fields.day', 'Day');
            weeklySelect.style.display = 'none';
            monthlySelect.style.display = 'block';
        }
    } else {
        dayRow.style.display = 'none';
    }
}

let currentDatasets = [];

async function createDataset() {
    const parentSelect = document.getElementById('new-dataset-parent');
    const childInput = document.getElementById('new-dataset-child');
    const parent = parentSelect.value;
    const child = childInput.value.trim().replace(/^\/+|\/+$/g, '');

    if (!parent || !child) {
        alert(t('datasets.create.name_required', 'Dataset name is required'));
        return;
    }

    const name = `${parent}/${child}`;

    if (!confirm(t('datasets.create.confirm', 'Create dataset {name}?', { name }))) {
        return;
    }

    const payload = {
        parent,
        child,
        mount: document.getElementById('new-dataset-mount').value,
        mountpoint: document.getElementById('new-dataset-mountpoint').value.trim(),
        atime: document.getElementById('new-dataset-atime').value,
        casesensitivity: document.getElementById('new-dataset-casesensitivity').value,
        compression: document.getElementById('new-dataset-compression').value,
        quota: document.getElementById('new-dataset-quota').value.trim(),
        quota_unit: document.getElementById('new-dataset-quota-unit').value,
    };

    try {
        const result = await postJson('../api/dataset-create.php', payload);

        if (result.ok) {
            childInput.value = '';
            document.getElementById('new-dataset-mountpoint').value = '';
            document.getElementById('new-dataset-quota').value = '';
            updateDefaultMountpointHint();
            loadDatasets();
            alert(t('datasets.create.success', 'Dataset created'));
        } else {
            alert(`${t('datasets.create.failed', 'Create failed')}: ${result.error?.message || t('common.unknown_error', 'Unknown error')}`);
        }
    } catch (error) {
        alert(`${t('common.request_failed', 'Request failed')}: ${error.message}`);
    }
}

async function loadDatasets() {
    const tbody = document.getElementById('datasets-table');
    const data = await fetchData('../api/datasets.php');
    
    if (data && data.ok) {
        currentDatasets = data.data;
        updateCreateParentOptions(currentDatasets);
        tbody.innerHTML = '';

        if (!data.data || data.data.length === 0) {
            renderTableMessage('datasets-table', t('datasets.empty', 'No datasets'), 9);
            return;
        }
        
        data.data.forEach(ds => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${ds.name}</td>
                <td><span class="status ${ds.enabled ? 'enabled' : 'disabled'}">${ds.enabled ? t('common.enabled', 'Enabled') : t('common.disabled', 'Disabled')}</span></td>
                <td>${frequencyLabel(ds.frequency)}</td>
                <td>${ds.keep}</td>
                <td>${ds.retain_days}</td>
                <td><span class="status ${ds.readonly ? 'hold' : 'disabled'}">${ds.readonly ? t('common.yes', 'Yes') : t('common.no', 'No')}</span></td>
                <td>${ds.snapshot_count}</td>
                <td>${ds.latest_snapshot_at ? formatTimestamp(ds.latest_snapshot_at) : '-'}</td>
                <td>
                    <button class="btn btn-small" onclick="openEdit('${ds.name}')">${t('common.edit', 'Edit')}</button>
                    <a href="${withLang(`snapshots-list.php?dataset=${encodeURIComponent(ds.name)}`)}" class="btn btn-small">${t('datasets.actions.snapshots', 'Snapshots')}</a>
                </td>
            `;
            tbody.appendChild(row);
        });
    } else {
        renderTableMessage('datasets-table', `${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.api_error', 'API error')}`, 9, 'table-message error');
    }
}

function updateCreateParentOptions(datasets) {
    const select = document.getElementById('new-dataset-parent');
    const previous = select.value;
    select.innerHTML = '';

    const rootDatasets = datasets.filter(ds => (!ds.type || ds.type === 'filesystem') && ds.is_root);

    rootDatasets.forEach(ds => {
        const option = document.createElement('option');
        option.value = ds.name;
        option.textContent = ds.name;
        select.appendChild(option);
    });

    if (previous && rootDatasets.some(ds => ds.name === previous)) {
        select.value = previous;
    }

    updateDefaultMountpointHint();
}

function updateDefaultMountpointHint() {
    const hint = document.getElementById('new-dataset-default-mountpoint');
    const mount = document.getElementById('new-dataset-mount').value;
    const parent = document.getElementById('new-dataset-parent').value;
    const child = document.getElementById('new-dataset-child').value.trim().replace(/^\/+|\/+$/g, '');

    if (mount === 'no') {
        hint.textContent = t('datasets.create.mountpoint_none', 'Mountpoint will be disabled.');
        return;
    }

    if (!parent) {
        hint.textContent = t('datasets.create.mountpoint_default_empty', 'Leave empty to use the ZFS default mountpoint.');
        return;
    }

    const suffix = child ? `/${child}` : '/<child>';
    hint.textContent = t('datasets.create.mountpoint_default', 'Leave empty for default: /mnt/{path}', { path: `${parent}${suffix}` });
}

function openEdit(name) {
    const ds = currentDatasets.find(d => d.name === name);
    if (!ds) return;

    document.getElementById('dataset-name').value = ds.name;
    document.getElementById('config-enabled').checked = ds.enabled;
    document.getElementById('config-frequency').value = ds.frequency;
    document.getElementById('config-keep').value = ds.keep;
    document.getElementById('config-time').value = ds.time || '00:00';
    document.getElementById('config-day-weekly').value = ds.day || 1;
    document.getElementById('config-day-monthly').value = ds.day || 1;
    document.getElementById('config-readonly').checked = ds.readonly;
    document.getElementById('config-retain-days').value = ds.retain_days;

    updateFieldVisibility(ds.frequency);
    document.getElementById('edit-modal').style.display = 'block';
}

function closeModal() {
    document.getElementById('edit-modal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('edit-modal');
    if (event.target === modal) {
        closeModal();
    }
}

async function saveConfig() {
    const name = document.getElementById('dataset-name').value;
    const frequency = document.getElementById('config-frequency').value;
    
    let dayValue;
    if (frequency === 'weekly') {
        dayValue = parseInt(document.getElementById('config-day-weekly').value);
    } else if (frequency === 'monthly') {
        dayValue = parseInt(document.getElementById('config-day-monthly').value);
    } else {
        dayValue = 1;
    }

    const payload = {
        enabled: document.getElementById('config-enabled').checked,
        frequency: frequency,
        keep: parseInt(document.getElementById('config-keep').value),
        time: document.getElementById('config-time').value,
        day: dayValue,
        readonly: document.getElementById('config-readonly').checked,
        retain_days: parseInt(document.getElementById('config-retain-days').value),
    };

    try {
        const result = await postJson('../api/dataset-update.php', { name, ...payload });
        
        if (result.ok) {
            closeModal();
            loadDatasets();
            alert(t('datasets.save_success', 'Saved successfully'));
        } else {
            alert(`${t('datasets.save_failed', 'Save failed')}: ${result.error?.message || t('common.unknown_error', 'Unknown error')}`);
        }
    } catch (error) {
        alert(`${t('common.request_failed', 'Request failed')}: ${error.message}`);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadDatasets();
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
