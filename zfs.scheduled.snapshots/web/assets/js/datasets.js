let currentDatasets = [];

const monthlySelect = () => document.getElementById('config-day-monthly');
for (let i = 1; i <= 31; i++) {
    const option = document.createElement('option');
    option.value = i;
    option.textContent = ZSS_LOCALE === 'zh-CN' ? `${i} 号` : `${i}`;
    monthlySelect().appendChild(option);
}

document.getElementById('config-frequency').addEventListener('change', e => updateFieldVisibility(e.target.value));
document.getElementById('new-dataset-mount').addEventListener('change', e => {
    const mountpoint = document.getElementById('new-dataset-mountpoint');
    mountpoint.disabled = e.target.value === 'no';
    if (mountpoint.disabled) mountpoint.value = '';
    updateDefaultMountpointHint();
});
document.getElementById('new-dataset-parent').addEventListener('change', updateDefaultMountpointHint);
document.getElementById('new-dataset-child').addEventListener('input', updateDefaultMountpointHint);

function updateFieldVisibility(frequency) {
    const timeRow = document.getElementById('row-time');
    const dayRow = document.getElementById('row-day');
    const weeklySelect = document.getElementById('config-day-weekly');
    const monthlySelectEl = document.getElementById('config-day-monthly');
    const dayLabel = document.getElementById('label-day');
    timeRow.style.display = ['daily', 'weekly', 'monthly'].includes(frequency) ? 'grid' : 'none';
    dayRow.style.display = ['weekly', 'monthly'].includes(frequency) ? 'grid' : 'none';
    if (frequency === 'weekly') {
        dayLabel.textContent = t('datasets.fields.weekday', 'Weekday');
        weeklySelect.style.display = 'block';
        monthlySelectEl.style.display = 'none';
    } else if (frequency === 'monthly') {
        dayLabel.textContent = t('datasets.fields.day', 'Day');
        weeklySelect.style.display = 'none';
        monthlySelectEl.style.display = 'block';
    }
}

async function createDataset() {
    const parent = document.getElementById('new-dataset-parent').value;
    const childInput = document.getElementById('new-dataset-child');
    const child = childInput.value.trim().replace(/^\/+|\/+$/g, '');
    if (!parent || !child) {
        alert(t('datasets.create.name_required', 'Dataset name is required'));
        return;
    }
    const name = `${parent}/${child}`;
    if (!confirm(t('datasets.create.confirm', 'Create dataset {name}?', { name }))) return;
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
            await loadDatasets();
            alert(t('datasets.create.success', 'Dataset created'));
        } else {
            alert(`${t('datasets.create.failed', 'Dataset creation failed')}: ${result.error?.message || t('common.unknown_error', 'Unknown error')}`);
        }
    } catch (error) {
        alert(`${t('common.request_failed', 'Request failed')}: ${error.message}`);
    }
}

function updateDatasetContext(datasets) {
    const datasetCount = document.getElementById('ctx-dataset-count');
    const enabledCount = document.getElementById('ctx-enabled-count');
    const snapshotCount = document.getElementById('ctx-snapshot-count');
    if (!datasetCount || !enabledCount || !snapshotCount) return;
    datasetCount.textContent = datasets.length;
    enabledCount.textContent = datasets.filter(ds => ds.enabled).length;
    snapshotCount.textContent = datasets.reduce((sum, ds) => sum + (ds.snapshot_count || 0), 0);
}

async function loadDatasets() {
    const data = await fetchData('../api/datasets.php');
    if (!data || !data.ok) {
        renderTableMessage('datasets-table', `${t('common.load_failed', 'Load failed')}: ${data?.error?.message || t('common.api_error', 'API error')}`, 10);
        return;
    }
    currentDatasets = data.data || [];
    updateDatasetContext(currentDatasets);
    if (typeof window.refreshResourceTree === 'function') window.refreshResourceTree(data);
    updateCreateParentOptions(currentDatasets);
    const tbody = document.getElementById('datasets-table');
    tbody.innerHTML = '';
    if (currentDatasets.length === 0) {
        renderTableMessage('datasets-table', t('datasets.empty', 'No datasets'), 10);
        return;
    }
    currentDatasets.forEach(ds => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><a class="zss-dataset-link" href="${withLang(`snapshots.php?dataset=${encodeURIComponent(ds.name)}`)}">${escapeHtml(ds.name)}</a></td>
            <td><span class="zss-badge ${ds.enabled ? 'zss-badge-success' : 'zss-badge-muted'}">${escapeHtml(ds.enabled ? t('common.enabled', 'Enabled') : t('common.disabled', 'Disabled'))}</span></td>
            <td>${escapeHtml(frequencyLabel(ds.frequency))}</td>
            <td>${renderKeepGauge(ds)}</td>
            <td>${renderReadonlyWindow(ds)}</td>
            <td><span class="zss-badge ${ds.readonly ? 'zss-badge-info' : 'zss-badge-muted'}">${escapeHtml(ds.readonly ? t('common.yes', 'Yes') : t('common.no', 'No'))}</span></td>
            <td>${renderHeldBadge(Number(ds.held_snapshot_count) || 0)}</td>
            <td>${escapeHtml(ds.snapshot_count ?? 0)}</td>
            <td>${ds.latest_snapshot_at ? escapeHtml(formatTimestamp(ds.latest_snapshot_at)) : '-'}</td>
            <td><div class="zss-action-row"><button class="zss-btn zss-btn-secondary zss-btn-small" onclick="openEdit(${JSON.stringify(ds.name).replaceAll('"', '&quot;')})">${escapeHtml(t('datasets.actions.edit_schedule', 'Edit scheduled snapshots'))}</button><a class="zss-btn zss-btn-secondary zss-btn-small" href="${withLang(`snapshots.php?dataset=${encodeURIComponent(ds.name)}`)}">${escapeHtml(t('datasets.actions.snapshots', 'Snapshots'))}</a></div></td>
        `;
        tbody.appendChild(row);
    });
}

function renderKeepGauge(ds) {
    const keep = Number(ds.keep);
    const count = Number(ds.snapshot_count) || 0;

    // No retention quota configured (or invalid): fall back to a plain count.
    if (!Number.isFinite(keep) || keep <= 0) {
        return `<span class="zss-keep-count">${escapeHtml(String(count))}</span>`;
    }

    const percent = Math.min(100, Math.round((count / keep) * 100));
    const tier = count > keep ? 'high' : percent >= 80 ? 'mid' : 'ok';
    return `
        <span class="zss-keep-gauge">
            <span class="zss-bar"><span class="zss-bar-fill ${tier}" style="width:${percent}%"></span></span>
            <span class="zss-keep-count" title="${escapeHtml(`${percent}%`)}">${escapeHtml(t('datasets.keep_gauge.of', '{count} / {keep}', { count, keep }))}</span>
        </span>
    `;
}

function renderReadonlyWindow(ds) {
    const days = Number(ds.retain_days);
    return escapeHtml(
        Number.isFinite(days) && days > 0
            ? t('datasets.readonly_window.days', '{days} days', { days })
            : t('datasets.readonly_window.off', 'Off')
    );
}

function renderHeldBadge(heldCount) {
    const label = t('snapshots.context.protected', 'Protected');
    return heldCount > 0
        ? `<span class="zss-held-badge">${escapeHtml(label)} ${escapeHtml(String(heldCount))}</span>`
        : `<span class="zss-held-badge is-zero">${escapeHtml(label)} 0</span>`;
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
    if (previous && rootDatasets.some(ds => ds.name === previous)) select.value = previous;
    updateDefaultMountpointHint();
}

function updateDefaultMountpointHint() {
    const hint = document.getElementById('new-dataset-default-mountpoint');
    const mount = document.getElementById('new-dataset-mount').value;
    const parent = document.getElementById('new-dataset-parent').value;
    const child = document.getElementById('new-dataset-child').value.trim().replace(/^\/+|\/+$/g, '');
    if (mount === 'no') { hint.textContent = t('datasets.create.mountpoint_none', 'Mountpoint will be disabled.'); return; }
    if (!parent) { hint.textContent = t('datasets.create.mountpoint_default_empty', 'Leave empty to use the ZFS default mountpoint.'); return; }
    hint.textContent = t('datasets.create.mountpoint_default', 'Leave empty for default: /mnt/{path}', { path: `${parent}${child ? `/${child}` : '/<child>'}` });
}

function openEdit(name) {
    const ds = currentDatasets.find(d => d.name === name);
    if (!ds) return;
    document.getElementById('dataset-name').value = ds.name;
    document.getElementById('config-enabled').checked = !!ds.enabled;
    document.getElementById('config-frequency').value = ds.frequency;
    document.getElementById('config-keep').value = ds.keep;
    document.getElementById('config-time').value = ds.time || '00:00';
    document.getElementById('config-day-weekly').value = ds.day || 1;
    document.getElementById('config-day-monthly').value = ds.day || 1;
    document.getElementById('config-readonly').checked = !!ds.readonly;
    document.getElementById('config-retain-days').value = ds.retain_days;
    updateFieldVisibility(ds.frequency);
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeModal() { document.getElementById('edit-modal').style.display = 'none'; }
window.addEventListener('click', event => { if (event.target === document.getElementById('edit-modal')) closeModal(); });

async function saveConfig() {
    const name = document.getElementById('dataset-name').value;
    const frequency = document.getElementById('config-frequency').value;
    const day = frequency === 'weekly' ? parseInt(document.getElementById('config-day-weekly').value) : frequency === 'monthly' ? parseInt(document.getElementById('config-day-monthly').value) : 1;
    const payload = {
        enabled: document.getElementById('config-enabled').checked,
        frequency,
        keep: parseInt(document.getElementById('config-keep').value),
        time: document.getElementById('config-time').value,
        day,
        readonly: document.getElementById('config-readonly').checked,
        retain_days: parseInt(document.getElementById('config-retain-days').value),
    };
    try {
        const result = await postJson('../api/dataset-update.php', { name, ...payload });
        if (result.ok) {
            closeModal();
            await loadDatasets();
            alert(t('datasets.save_success', 'Saved successfully'));
        } else {
            alert(`${t('datasets.save_failed', 'Save failed')}: ${result.error?.message || t('common.unknown_error', 'Unknown error')}`);
        }
    } catch (error) {
        alert(`${t('common.request_failed', 'Request failed')}: ${error.message}`);
    }
}

document.addEventListener('DOMContentLoaded', loadDatasets);
