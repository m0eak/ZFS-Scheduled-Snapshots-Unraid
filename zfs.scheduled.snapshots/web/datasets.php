<?php
$currentPage = 'datasets';
require __DIR__ . '/layout/header.php';
?>

<h2>数据集管理</h2>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>数据集</th>
                <th>状态</th>
                <th>频率</th>
                <th>保留数量</th>
                <th>保留天数</th>
                <th>只读</th>
                <th>快照数</th>
                <th>最后快照</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody id="datasets-table">
            <tr>
                <td colspan="9">加载中...</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const datasets = await fetchData('../api/datasets.php');
    if (datasets && datasets.ok) {
        const tbody = document.getElementById('datasets-table');
        tbody.innerHTML = '';
        
        datasets.data.forEach(ds => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${ds.name}</td>
                <td><span class="status ${ds.enabled ? 'enabled' : 'disabled'}">${ds.enabled ? '启用' : '禁用'}</span></td>
                <td>${ds.frequency}</td>
                <td>${ds.keep}</td>
                <td>${ds.retain_days}</td>
                <td><span class="status ${ds.readonly ? 'hold' : 'disabled'}">${ds.readonly ? '是' : '否'}</span></td>
                <td>${ds.snapshot_count}</td>
                <td>${ds.latest_snapshot_at ? formatTimestamp(ds.latest_snapshot_at) : '-'}</td>
                <td>
                    <a href="snapshots.php?dataset=${encodeURIComponent(ds.name)}" class="btn btn-small">快照</a>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
