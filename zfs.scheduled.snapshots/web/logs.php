<?php
$currentPage = 'logs';
require_once __DIR__ . '/layout/header.php';
?>

<h2>执行日志</h2>

<div style="margin-bottom: 15px;">
    <select id="log-level" class="form-select" style="width: 150px; display: inline-block; margin-right: 10px;">
        <option value="all">全部级别</option>
        <option value="INFO">INFO</option>
        <option value="ERROR">ERROR</option>
    </select>
    <button class="btn btn-small" onclick="loadLogs()">刷新</button>
    <button class="btn btn-small btn-secondary" onclick="clearLogs()" style="margin-left: 10px;">清空日志</button>
</div>

<div class="table-wrapper" style="max-height: 600px; overflow-y: auto;">
    <table>
        <thead>
            <tr>
                <th style="width: 180px;">时间</th>
                <th style="width: 80px;">级别</th>
                <th>消息</th>
            </tr>
        </thead>
        <tbody id="logs-table">
            <tr>
                <td colspan="3">加载中...</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
async function loadLogs() {
    const level = document.getElementById('log-level').value;
    const tbody = document.getElementById('logs-table');
    
    const data = await fetchData(`../api/logs.php?level=${encodeURIComponent(level)}&limit=200`);
    
    if (data && data.ok) {
        tbody.innerHTML = '';
        
        if (data.data.logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3">暂无日志</td></tr>';
        } else {
            data.data.logs.forEach(log => {
                const row = document.createElement('tr');
                const levelClass = log.level === 'ERROR' ? 'disabled' : 'enabled';
                row.innerHTML = `
                    <td>${log.timestamp}</td>
                    <td><span class="status ${levelClass}">${log.level}</span></td>
                    <td>${log.message}</td>
                `;
                tbody.appendChild(row);
            });
        }
    } else {
        tbody.innerHTML = `<tr><td colspan="3">日志加载失败：${data?.error?.message || '未知错误'}</td></tr>`;
    }
}

async function clearLogs() {
    if (!confirm('确定要清空所有日志吗？')) return;
    
    try {
        const response = await fetch('../api/logs.php?action=clear', {
            method: 'POST',
        });
        
        const result = await response.json();
        
        if (result.ok) {
            loadLogs();
        } else {
            alert('清空失败: ' + (result.error?.message || '未知错误'));
        }
    } catch (error) {
        alert('请求失败: ' + error.message);
    }
}

document.getElementById('log-level').addEventListener('change', function() {
    loadLogs();
});

// 页面加载
document.addEventListener('DOMContentLoaded', function() {
    loadLogs();
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
