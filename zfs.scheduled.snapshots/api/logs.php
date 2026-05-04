<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

$action = $_GET['action'] ?? 'list';
$level = $_GET['level'] ?? 'all';
$limit = intval($_GET['limit'] ?? 200);

if ($action === 'clear') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        zss_json_error('METHOD_NOT_ALLOWED', 'Only POST is allowed', 405);
    }
    
    $result = LogService::clearLogs();
    
    if ($result) {
        zss_json_success(['message' => 'Log cleared']);
    } else {
        zss_json_error('CLEAR_FAILED', 'Failed to clear log', 500);
    }
}

// 默认：获取日志列表
$logs = LogService::getLogs($limit, $level);

zss_json_success([
    'logs' => $logs,
    'total' => count($logs),
]);
