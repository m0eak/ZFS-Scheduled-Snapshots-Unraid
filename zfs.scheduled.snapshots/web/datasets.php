<?php
$currentPage = 'datasets';
require_once __DIR__ . '/layout/header.php';
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

<!-- 编辑模态框 -->
<div id="edit-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>编辑数据集配置</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="edit-form">
                <input type="hidden" id="dataset-name">
                
                <div class="form-row">
                    <label class="form-label">
                        <input type="checkbox" id="config-enabled">
                        启用自动快照
                    </label>
                </div>

                <div class="form-row">
                    <label class="form-label" for="config-frequency">快照频率</label>
                    <select id="config-frequency" class="form-select">
                        <option value="5min">每 5 分钟</option>
                        <option value="15min">每 15 分钟</option>
                        <option value="hourly">每小时</option>
                        <option value="daily">每天</option>
                        <option value="weekly">每周</option>
                        <option value="monthly">每月</option>
                    </select>
                </div>

                <div class="form-row">
                    <label class="form-label" for="config-keep">保留快照数量</label>
                    <input type="number" id="config-keep" class="form-input" min="1" value="31">
                </div>

                <div class="form-row" id="row-time" style="display: none;">
                    <label class="form-label" for="config-time">快照时间</label>
                    <input type="time" id="config-time" class="form-input" value="00:00">
                </div>

                <div class="form-row" id="row-day" style="display: none;">
                    <label class="form-label" id="label-day">日期</label>
                    <select id="config-day-weekly" class="form-select" style="display: none;">
                        <option value="1">周一</option>
                        <option value="2">周二</option>
                        <option value="3">周三</option>
                        <option value="4">周四</option>
                        <option value="5">周五</option>
                        <option value="6">周六</option>
                        <option value="7">周日</option>
                    </select>
                    <select id="config-day-monthly" class="form-select" style="display: none;">
                        <!-- JS 填充 1-31 -->
                    </select>
                </div>

                <div class="form-row">
                    <label class="form-label">
                        <input type="checkbox" id="config-readonly">
                        新快照设为只读（Hold 保护）
                    </label>
                </div>

                <div class="form-row">
                    <label class="form-label" for="config-retain-days">
                        只读快照保留天数（0=不限制）
                    </label>
                    <input type="number" id="config-retain-days" class="form-input" min="0" value="0">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
            <button type="button" class="btn btn-primary" onclick="saveConfig()">保存</button>
        </div>
    </div>
</div>

<script>
// 填充每月日期选项
const monthlySelect = document.getElementById('config-day-monthly');
for (let i = 1; i <= 31; i++) {
    const option = document.createElement('option');
    option.value = i;
    option.textContent = i + ' 号';
    monthlySelect.appendChild(option);
}

// 频率变化时显示/隐藏相关字段
document.getElementById('config-frequency').addEventListener('change', function(e) {
    updateFieldVisibility(e.target.value);
});

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
            dayLabel.textContent = '星期';
            weeklySelect.style.display = 'block';
            monthlySelect.style.display = 'none';
        } else {
            dayLabel.textContent = '日期';
            weeklySelect.style.display = 'none';
            monthlySelect.style.display = 'block';
        }
    } else {
        dayRow.style.display = 'none';
    }
}

let currentDatasets = [];

// 加载数据集列表
async function loadDatasets() {
    const tbody = document.getElementById('datasets-table');
    const data = await fetchData('../api/datasets.php');
    
    if (data && data.ok) {
        currentDatasets = data.data;
        tbody.innerHTML = '';
        
        data.data.forEach(ds => {
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
                    <button class="btn btn-small" onclick="openEdit('${ds.name}')">编辑</button>
                    <a href="snapshots.php?dataset=${encodeURIComponent(ds.name)}" class="btn btn-small">快照</a>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
}

// 打开编辑模态框
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

// 关闭模态框
function closeModal() {
    document.getElementById('edit-modal').style.display = 'none';
}

// 点击模态框外部关闭
window.onclick = function(event) {
    const modal = document.getElementById('edit-modal');
    if (event.target === modal) {
        closeModal();
    }
}

// 保存配置
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
        const response = await fetch('../api/dataset-update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ name, ...payload }),
        });

        const result = await response.json();
        
        if (result.ok) {
            closeModal();
            loadDatasets();
            alert('保存成功');
        } else {
            alert('保存失败: ' + (result.error?.message || '未知错误'));
        }
    } catch (error) {
        alert('请求失败: ' + error.message);
    }
}

// 页面加载
document.addEventListener('DOMContentLoaded', function() {
    loadDatasets();
});
</script>

<style>
/* 模态框样式 */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 4px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 500;
}

.close {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: #999;
    line-height: 1;
}

.close:hover {
    color: #333;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #eee;
    text-align: right;
}

/* 表单样式 */
.form-row {
    margin-bottom: 15px;
}

.form-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #555;
    font-size: 14px;
}

.form-input, .form-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: #3498db;
}

.btn-secondary {
    background-color: #95a5a6;
    margin-right: 10px;
}

.btn-secondary:hover {
    background-color: #7f8c8d;
}
</style>

<?php require __DIR__ . '/layout/footer.php'; ?>
