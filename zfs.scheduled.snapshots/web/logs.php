<?php
$currentPage = 'logs';
require __DIR__ . '/layout/header.php';
?>

<h2>日志</h2>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>时间</th>
                <th>级别</th>
                <th>内容</th>
            </tr>
        </thead>
        <tbody id="logs-table">
            <tr>
                <td colspan="3">日志功能待完善，当前为占位状态</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const logs = await fetchData('../api/logs.php');
    if (logs && logs.ok && logs.data.entries) {
        const tbody = document.getElementById('logs-table');
        tbody.innerHTML = '';
        
        if (logs.data.entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3">暂无日志</td></tr>';
        } else {
            logs.data.entries.forEach(log => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${log.timestamp}</td>
                    <td>${log.level}</td>
                    <td>${log.message}</td>
                `;
                tbody.appendChild(row);
            });
        }
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
