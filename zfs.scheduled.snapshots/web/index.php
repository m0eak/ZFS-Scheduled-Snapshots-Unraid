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
            row.innerHTML = `
                <td>${ds.name}</td>
                <td><span class="status ${ds.enabled ? 'enabled' : 'disabled'}">${ds.enabled ? t('common.enabled', 'Enabled') : t('common.disabled', 'Disabled')}</span></td>
                <td>${frequencyLabel(ds.frequency)}</td>
                <td>${ds.keep}</td>
                <td><span class="status ${ds.readonly ? 'hold' : 'disabled'}">${ds.readonly ? t('common.yes', 'Yes') : t('common.no', 'No')}</span></td>
                <td>${ds.snapshot_count}</td>
            `;
            tbody.appendChild(row);
        });
    } else {
        renderTableMessage('datasets-table', `${t('common.load_failed', 'Load failed')}: ${datasets?.error?.message || t('common.api_error', 'API error')}`, 6, 'table-message error');
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
