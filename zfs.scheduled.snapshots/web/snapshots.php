<?php
$currentPage = 'snapshots';
$dataset = $_GET['dataset'] ?? '';
require __DIR__ . '/layout/header.php';
?>

<h2>快照管理</h2>

<?php if ($dataset): ?>
    <p>当前数据集: <strong><?php echo htmlspecialchars($dataset); ?></strong></p>
    <p><a href="snapshots.php">← 返回全部快照</a></p>
<?php endif; ?>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>快照名称</th>
                <th>数据集</th>
                <th>创建时间</th>
                <th>状态</th>
            </tr>
        </thead>
        <tbody id="snapshots-table">
            <tr>
                <td colspan="4">
                    <?php echo $dataset ? '快照列表加载中...' : '请先选择一个数据集'; ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const dataset = '<?php echo addslashes($dataset); ?>';
    
    if (dataset) {
        const snapshots = await fetchData(`../api/snapshots.php?name=${encodeURIComponent(dataset)}`);
        if (snapshots && snapshots.ok) {
            const tbody = document.getElementById('snapshots-table');
            tbody.innerHTML = '';
            
            if (snapshots.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">暂无快照</td></tr>';
            } else {
                snapshots.data.forEach(snap => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${snap.short_name}</td>
                        <td>${snap.dataset}</td>
                        <td>${snap.created_at ? formatTimestamp(snap.created_at) : '-'}</td>
                        <td><span class="status ${snap.held ? 'hold' : 'enabled'}">${snap.held ? '受保护' : '普通'}</span></td>
                    `;
                    tbody.appendChild(row);
                });
            }
        }
    } else {
        // 如果没有指定 dataset，先显示所有数据集供选择
        const datasets = await fetchData('../api/datasets.php');
        if (datasets && datasets.ok) {
            const tbody = document.getElementById('snapshots-table');
            tbody.innerHTML = '';
            
            datasets.data.forEach(ds => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td colspan="3">${ds.name}</td>
                    <td><a href="snapshots.php?dataset=${encodeURIComponent(ds.name)}" class="btn btn-small">查看快照</a></td>
                `;
                tbody.appendChild(row);
            });
        }
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
