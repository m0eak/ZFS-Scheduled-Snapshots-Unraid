<?php
$currentPage = 'snapshots';
$dataset = $_GET['dataset'] ?? '';
require_once __DIR__ . '/layout/header.php';
?>

<h2>快照管理</h2>

<?php if ($dataset): ?>
    <p>当前数据集: <strong><?php echo htmlspecialchars($dataset); ?></strong></p>
    <p><a href="snapshots.php">← 返回全部快照</a></p>
    <p style="margin-top: 10px;">
        <button class="btn btn-small" onclick="createSnapshot()">手动创建快照</button>
    </p>
<?php endif; ?>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>快照名称</th>
                <?php if (!$dataset): ?><th>数据集</th><?php endif; ?>
                <th>创建时间</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody id="snapshots-table">
            <tr>
                <td colspan="<?php echo $dataset ? '4' : '5'; ?>">
                    <?php echo $dataset ? '快照列表加载中...' : '请先选择一个数据集'; ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<script>
let currentSnapshots = [];

// 加载快照列表
async function loadSnapshots(datasetName) {
    const tbody = document.getElementById('snapshots-table');
    const data = await fetchData(`../api/snapshots.php?name=${encodeURIComponent(datasetName)}`);
    
    if (data && data.ok) {
        currentSnapshots = data.data;
        tbody.innerHTML = '';
        
        if (data.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${dataset ? '4' : '5'}">暂无快照</td></tr>`;
        } else {
            data.data.forEach(snap => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${snap.short_name}</td>
                    <?php if (!$dataset): ?><td>${snap.dataset}</td><?php endif; ?>
                    <td>${snap.created_at ? formatTimestamp(snap.created_at) : '-'}</td>
                    <td><span class="status ${snap.held ? 'hold' : 'enabled'}">${snap.held ? '受保护' : '普通'}</span></td>
                    <td>
                        ${snap.held ? 
                            `<button class="btn btn-small" onclick="releaseHold('${snap.name}')">释放保护</button>` :
                            `<button class="btn btn-small" onclick="addHold('${snap.name}')">设为只读</button>
                             <button class="btn btn-small btn-secondary" onclick="deleteSnapshot('${snap.name}')">删除</button>`
                        }
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
    } else {
        tbody.innerHTML = `<tr><td colspan="${datasetName ? '4' : '5'}">快照加载失败：${data?.error?.message || '未知错误'}</td></tr>`;
    }
}

// 加载数据集选择列表
async function loadDatasetList() {
    const tbody = document.getElementById('snapshots-table');
    const data = await fetchData('../api/datasets.php');
    
    if (data && data.ok) {
        tbody.innerHTML = '';

        if (!data.data || data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5">暂无数据集</td></tr>';
            return;
        }
        
        data.data.forEach(ds => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="2">${ds.name}</td>
                <td>${ds.snapshot_count} 个快照</td>
                <td><span class="status ${ds.enabled ? 'enabled' : 'disabled'}">${ds.enabled ? '启用' : '禁用'}</span></td>
                <td><a href="snapshots.php?dataset=${encodeURIComponent(ds.name)}" class="btn btn-small">查看快照</a></td>
            `;
            tbody.appendChild(row);
        });
    } else {
        tbody.innerHTML = `<tr><td colspan="5">数据集加载失败：${data?.error?.message || '未知错误'}</td></tr>`;
    }
}

// 手动创建快照
async function createSnapshot() {
    if (!confirm('确定要手动创建快照吗？')) return;
    
    try {
        const response = await fetch('../api/snapshot-create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: '<?php echo addslashes($dataset); ?>' }),
        });
        
        const result = await response.json();
        
        if (result.ok) {
            alert('创建成功');
            loadSnapshots('<?php echo addslashes($dataset); ?>');
        } else {
            alert('创建失败: ' + (result.error?.message || '未知错误'));
        }
    } catch (error) {
        alert('请求失败: ' + error.message);
    }
}

// 删除快照
async function deleteSnapshot(name) {
    if (!confirm('确定要删除快照 ' + name + ' 吗？')) return;
    
    try {
        const response = await fetch('../api/snapshot-delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name }),
        });
        
        const result = await response.json();
        
        if (result.ok) {
            alert('删除成功');
            loadSnapshots('<?php echo addslashes($dataset); ?>');
        } else {
            alert('删除失败: ' + (result.error?.message || '未知错误'));
        }
    } catch (error) {
        alert('请求失败: ' + error.message);
    }
}

// 添加 hold
async function addHold(name) {
    if (!confirm('确定要为快照 ' + name + ' 添加只读保护吗？')) return;
    
    try {
        const response = await fetch('../api/snapshot-hold.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name }),
        });
        
        const result = await response.json();
        
        if (result.ok) {
            alert('设置成功');
            loadSnapshots('<?php echo addslashes($dataset); ?>');
        } else {
            alert('设置失败: ' + (result.error?.message || '未知错误'));
        }
    } catch (error) {
        alert('请求失败: ' + error.message);
    }
}

// 释放 hold
async function releaseHold(name) {
    if (!confirm('确定要释放快照 ' + name + ' 的只读保护吗？')) return;
    
    try {
        const response = await fetch('../api/snapshot-release.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name }),
        });
        
        const result = await response.json();
        
        if (result.ok) {
            alert('释放成功');
            loadSnapshots('<?php echo addslashes($dataset); ?>');
        } else {
            alert('释放失败: ' + (result.error?.message || '未知错误'));
        }
    } catch (error) {
        alert('请求失败: ' + error.message);
    }
}

// 页面加载
document.addEventListener('DOMContentLoaded', function() {
    const datasetName = '<?php echo addslashes($dataset); ?>';
    if (datasetName) {
        loadSnapshots(datasetName);
    } else {
        loadDatasetList();
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
