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

<div id="log-status" class="log-status" style="margin-bottom: 15px;"></div>

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
function renderLogStatus(data) {
    const box = document.getElementById('log-status');
    const status = data?.data?.status;
    const readError = data?.data?.read_error;

    if (!status) {
        box.innerHTML = '<div class="status-box warn">无法获取日志状态信息</div>';
        return;
    }

    if (readError) {
        box.innerHTML = `<div class="status-box error">日志读取失败：${readError}<br>路径：<code>${status.path}</code></div>`;
        return;
    }

    if (!status.exists) {
        box.innerHTML = `<div class="status-box warn">日志文件尚不存在：<code>${status.path}</code><br>目录存在：${status.dir_exists ? '是' : '否'}；目录可写：${status.dir_writable ? '是' : '否'}</div>`;
        return;
    }

    if (!status.readable) {
        box.innerHTML = `<div class="status-box error">日志文件存在但不可读：<code>${status.path}</code></div>`;
        return;
    }

    box.innerHTML = `<div class="status-box ok">日志文件正常：<code>${status.path}</code></div>`;
}

async function loadLogs() {
    const level = document.getElementById('log-level').value;
    const tbody = document.getElementById('logs-table');
    
    const data = await fetchData(`../api/logs.php?level=${encodeURIComponent(level)}&limit=200`);
    renderLogStatus(data);
    
    if (data && data.ok) {
        tbody.innerHTML = '';
        
        if (data.data.logs.length === 0) {
            const status = data.data.status;
            if (status && !status.exists) {
                tbody.innerHTML = '<tr><td colspan="3">日志文件尚未生成</td></tr>';
            } else {
                tbody.innerHTML = '<tr><td colspan="3">暂无日志</td></tr>';
            }
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

<style>
.status-box {
    padding: 12px 14px;
    border-radius: 6px;
    border: 1px solid #ddd;
    background: #fafafa;
    color: #444;
    font-size: 13px;
    line-height: 1.6;
}
.status-box.ok {
    background: #f1f8e9;
    border-color: #c5e1a5;
    color: #33691e;
}
.status-box.warn {
    background: #fff8e1;
    border-color: #ffe082;
    color: #8d6e63;
}
.status-box.error {
    background: #ffebee;
    border-color: #ef9a9a;
    color: #b71c1c;
}
.status-box code {
    background: rgba(0,0,0,0.05);
    padding: 2px 5px;
    border-radius: 4px;
}
</style>

<?php require __DIR__ . '/layout/footer.php'; ?>
