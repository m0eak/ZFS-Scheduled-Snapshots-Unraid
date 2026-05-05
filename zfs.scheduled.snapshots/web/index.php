<?php
$currentPage = 'index';
require __DIR__ . '/layout/header.php';
?>

<h2><?php echo htmlspecialchars(zss_t('overview.title')); ?></h2>

<div class="stats-grid">
    <div class="stat-card">
        <h3><?php echo htmlspecialchars(zss_t('overview.stats.dataset_count')); ?></h3>
        <div class="stat-value" id="dataset-count">-</div>
    </div>
    <div class="stat-card">
        <h3><?php echo htmlspecialchars(zss_t('overview.stats.enabled_count')); ?></h3>
        <div class="stat-value" id="enabled-count">-</div>
    </div>
    <div class="stat-card">
        <h3><?php echo htmlspecialchars(zss_t('overview.stats.snapshot_count')); ?></h3>
        <div class="stat-value" id="snapshot-count">-</div>
    </div>
    <div class="stat-card">
        <h3><?php echo htmlspecialchars(zss_t('overview.stats.readonly_count')); ?></h3>
        <div class="stat-value" id="readonly-count">-</div>
    </div>
    <div class="stat-card">
        <h3><?php echo htmlspecialchars(zss_t('overview.stats.snapshot_size')); ?></h3>
        <div class="stat-value small" id="snapshot-used-bytes">-</div>
    </div>
    <div class="stat-card">
        <h3><?php echo htmlspecialchars(zss_t('overview.stats.last_snapshot')); ?></h3>
        <div class="stat-value small" id="last-snapshot">-</div>
    </div>
    <div class="stat-card">
        <h3><?php echo htmlspecialchars(zss_t('overview.stats.last_dataset')); ?></h3>
        <div class="stat-value small" id="last-dataset">-</div>
    </div>
</div>

<h3><?php echo htmlspecialchars(zss_t('overview.dataset_status')); ?></h3>
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th><?php echo htmlspecialchars(zss_t('table.dataset')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.status')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.frequency')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.keep')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.readonly')); ?></th>
                <th><?php echo htmlspecialchars(zss_t('table.snapshot_count')); ?></th>
            </tr>
        </thead>
        <tbody id="datasets-table">
            <tr>
                <td colspan="6"><?php echo htmlspecialchars(zss_t('common.loading')); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const overview = await fetchData('../api/overview.php');
    if (overview && overview.ok) {
        const data = overview.data;
        document.getElementById('dataset-count').textContent = data.dataset_count || 0;
        document.getElementById('enabled-count').textContent = data.enabled_count || 0;
        document.getElementById('snapshot-count').textContent = data.snapshot_count || 0;
        document.getElementById('readonly-count').textContent = data.readonly_snapshot_count || 0;
        document.getElementById('snapshot-used-bytes').textContent = formatBytes(data.snapshot_used_bytes);
        document.getElementById('last-snapshot').textContent = data.last_snapshot_at ? formatTimestamp(data.last_snapshot_at) : '-';
        document.getElementById('last-dataset').textContent = data.last_snapshot_dataset || '-';
    } else {
        document.getElementById('snapshot-used-bytes').textContent = '-';
        document.getElementById('last-snapshot').textContent = t('common.load_failed', 'Load failed');
        document.getElementById('last-dataset').textContent = overview?.error?.message || t('common.api_error', 'API error');
    }

    const datasets = await fetchData('../api/datasets.php');
    if (datasets && datasets.ok) {
        const tbody = document.getElementById('datasets-table');
        tbody.innerHTML = '';

        if (!datasets.data || datasets.data.length === 0) {
            renderTableMessage('datasets-table', t('datasets.empty', 'No datasets'), 6);
            return;
        }
        
        datasets.data.forEach(ds => {
            const row = document.createElement('tr');
            row.className = 'dataset-summary-row';
            row.dataset.dataset = ds.name;
            row.innerHTML = `
                <td><span class="expand-indicator">▸</span>${escapeHtml(ds.name)}</td>
                <td><span class="status ${ds.enabled ? 'enabled' : 'disabled'}">${ds.enabled ? t('common.enabled', 'Enabled') : t('common.disabled', 'Disabled')}</span></td>
                <td>${frequencyLabel(ds.frequency)}</td>
                <td>${ds.keep}</td>
                <td><span class="status ${ds.readonly ? 'hold' : 'disabled'}">${ds.readonly ? t('common.yes', 'Yes') : t('common.no', 'No')}</span></td>
                <td>${ds.snapshot_count}</td>
            `;
            tbody.appendChild(row);

            const detailRow = document.createElement('tr');
            detailRow.className = 'dataset-detail-row';
            detailRow.dataset.dataset = ds.name;
            detailRow.style.display = 'none';
            detailRow.innerHTML = `
                <td colspan="6">
                    ${renderDatasetDetails(ds)}
                </td>
            `;
            tbody.appendChild(detailRow);
        });
    } else {
        renderTableMessage('datasets-table', `${t('common.load_failed', 'Load failed')}: ${datasets?.error?.message || t('common.api_error', 'API error')}`, 6, 'table-message error');
    }
});

document.getElementById('datasets-table').addEventListener('click', function(event) {
    const row = event.target.closest('tr.dataset-summary-row');
    if (!row) {
        return;
    }

    const detailRow = row.nextElementSibling;
    if (!detailRow || !detailRow.classList.contains('dataset-detail-row')) {
        return;
    }

    const expanded = detailRow.style.display !== 'none';
    detailRow.style.display = expanded ? 'none' : 'table-row';
    row.classList.toggle('is-expanded', !expanded);
});

function renderDatasetDetails(ds) {
    const details = ds.details || {};
    const type = details.type || ds.type || '-';
    const sections = [
        {
            title: t('overview.details.identity', 'Identity'),
            items: [
                ['overview.details.creation', formatDetailValue('creation', details.creation)],
                ['overview.details.type', type],
                ['overview.details.mountpoint', formatDetailValue('mountpoint', details.mountpoint)],
                ['overview.details.origin', formatDetailValue('origin', details.origin)],
            ],
        },
        {
            title: t('overview.details.storage', 'Storage'),
            items: [
                ['overview.details.used', formatDetailValue('bytes', details.used)],
                ['overview.details.available', formatDetailValue('bytes', details.available)],
                ['overview.details.referenced', formatDetailValue('bytes', details.referenced)],
                ['overview.details.usedbysnapshots', formatDetailValue('bytes', details.usedbysnapshots)],
                ['overview.details.quota', formatDetailValue('quota', details.quota)],
                type === 'volume'
                    ? ['overview.details.volblocksize', formatDetailValue('bytes', details.volblocksize)]
                    : ['overview.details.recordsize', formatDetailValue('bytes', details.recordsize)],
            ],
        },
        {
            title: t('overview.details.behavior', 'Behavior'),
            items: [
                ['overview.details.compression', formatDetailValue('text', details.compression)],
                ['overview.details.compressratio', formatDetailValue('ratio', details.compressratio)],
                ['overview.details.atime', formatDetailValue('text', details.atime)],
                ['overview.details.xattr', formatDetailValue('text', details.xattr)],
                ['overview.details.primarycache', formatDetailValue('text', details.primarycache)],
                ['overview.details.readonly', formatDetailValue('text', details.readonly)],
                ['overview.details.casesensitivity', formatDetailValue('text', details.casesensitivity)],
                ['overview.details.sync', formatDetailValue('text', details.sync)],
            ],
        },
        {
            title: t('overview.details.encryption', 'Encryption'),
            items: [
                ['overview.details.encryption_mode', formatDetailValue('text', details.encryption)],
                ['overview.details.keystatus', formatDetailValue('text', details.keystatus)],
            ],
        },
    ];

    return `<div class="dataset-detail-panel">
        ${sections.map(section => renderDetailSection(section)).join('')}
    </div>`;
}

function renderDetailSection(section) {
    const items = section.items
        .filter(item => item && item[1] !== null && item[1] !== undefined && item[1] !== '')
        .map(([key, value]) => `
            <div class="detail-item">
                <dt>${escapeHtml(t(key, key))}</dt>
                <dd>${escapeHtml(value)}</dd>
            </div>
        `)
        .join('');

    if (!items) {
        return '';
    }

    return `<section class="detail-section">
        <h4>${escapeHtml(section.title)}</h4>
        <dl>${items}</dl>
    </section>`;
}

function formatDetailValue(type, value) {
    if (value === null || value === undefined || value === '' || value === '-') {
        return '-';
    }

    if (type === 'bytes') {
        return formatBytes(value);
    }

    if (type === 'quota') {
        return Number(value) === 0 ? t('common.none', 'None') : formatBytes(value);
    }

    if (type === 'creation') {
        return formatTimestamp(Number(value));
    }

    if (type === 'ratio') {
        const ratio = Number(value);
        return Number.isFinite(ratio) ? `${(ratio / 100).toFixed(2)}x` : String(value);
    }

    return String(value);
}
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
