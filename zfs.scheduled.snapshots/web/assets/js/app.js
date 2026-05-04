// 简单的工具函数
document.addEventListener('DOMContentLoaded', function() {
    console.log('ZFS Scheduled Snapshots WebUI loaded');
});

// 格式化时间戳
function formatTimestamp(timestamp) {
    if (!timestamp) return '-';
    const date = new Date(timestamp * 1000);
    return date.toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

// 加载数据
async function fetchData(url) {
    try {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json'
            }
        });

        const contentType = response.headers.get('content-type') || '';
        let data = null;

        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            throw new Error(`Expected JSON but received: ${text.slice(0, 200)}`);
        }

        if (!response.ok) {
            throw new Error(data?.error?.message || `HTTP ${response.status}`);
        }

        if (data && data.ok === false) {
            throw new Error(data?.error?.message || 'API returned error');
        }

        return data;
    } catch (error) {
        console.error('Fetch error:', error);
        return {
            ok: false,
            error: {
                message: error.message || '请求失败'
            }
        };
    }
}

function renderTableMessage(tbodyId, message, colspan = 1, className = 'table-message') {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="${className}">${message}</td></tr>`;
}
