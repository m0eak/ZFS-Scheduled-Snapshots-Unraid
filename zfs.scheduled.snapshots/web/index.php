<?php
$currentPage = 'index';
require __DIR__ . '/layout/header.php';
?>

<h2>概览</h2>

<div class="stats-grid">
    <div class="stat-card">
        <h3>数据集总数</h3>
        <div class="stat-value" id="dataset-count">-</div>
    </div>
    <div class="stat-card">
        <h3>已启用</h3>
        <div class="stat-value" id="enabled-count">-</div>
    </div>
    <div class="stat-card">
        <h3>快照总数</h3>
        <div class="stat-value" id="snapshot-count">-</div>
    </div>
    <div class="stat-card">
        <h3>受保护快照</h3>
        <div class="stat-value" id="readonly-count">-</div>
    </div>
    <div class="stat-card">
        <h3>最后快照时间</h3>
        <div class="stat-value small" id="last-snapshot">-</div>
    </div>
    <div class="stat-card">
        <h3>最后快照数据集</h3>
        <div class="stat-value small" id="last-dataset">-</div>
    </div>
</div>

<h3>数据集状态</h3>
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>数据集</th>
                <th>状态</th>
                <th>频率</th>
                <th>保留数量</th>
                <th>只读</th>
                <th>快照数</th>
            </tr>
        </thead>
        <tbody id="datasets-table">
            <tr>
                <td colspan="6">加载中...</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    // 加载概览
    const overview = await fetchData('../api/overview.php');
    if (overview && overview.ok) {
        const data = overview.data;
        document.getElementById('dataset-count').textContent = data.dataset_count || 0;
        document.getElementById('enabled-count').textContent = data.enabled_count || 0;
        document.getElementById('snapshot-count').textContent = data.snapshot_count || 0;
        document.getElementById('readonly-count').textContent = data.readonly_snapshot_count || 0;
        document.getElementById('last-snapshot').textContent = data.last_snapshot_at ? formatTimestamp(data.last_snapshot_at) : '-';
        document.getElementById('last-dataset').textContent = data.last_snapshot_dataset || '-';
    } else {
        document.getElementById('last-snapshot').textContent = '加载失败';
        document.getElementById('last-dataset').textContent = overview?.error?.message || '概览接口异常';
    }

    // 加载数据集
    const datasets = await fetchData('../api/datasets.php');
    if (datasets && datasets.ok) {
        const tbody = document.getElementById('datasets-table');
        tbody.innerHTML = '';

        if (!datasets.data || datasets.data.length === 0) {
            renderTableMessage('datasets-table', '暂无数据集', 6);
            return;
        }
        
        datasets.data.forEach(ds => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${ds.name}</td>
                <td><span class="status ${ds.enabled ? 'enabled' : 'disabled'}">${ds.enabled ? '启用' : '禁用'}</span></td>
                <td>${ds.frequency}</td>
                <td>${ds.keep}</td>
                <td><span class="status ${ds.readonly ? 'hold' : 'disabled'}">${ds.readonly ? '是' : '否'}</span></td>
                <td>${ds.snapshot_count}</td>
            `;
            tbody.appendChild(row);
        });
    } else {
        renderTableMessage('datasets-table', `加载失败：${datasets?.error?.message || '数据集接口异常'}`, 6, 'table-message error');
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
