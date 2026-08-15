<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_api_run(function() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        zss_require_action_request();
        $payload = zss_get_action_payload();
        $action = $payload['action'] ?? '';

        if ($action !== 'clear') {
            zss_json_error('INVALID_ACTION', 'Unsupported log action', 400);
        }

        $result = LogService::clearLogs();
        if ($result) {
            zss_json_success(['message' => 'Log cleared']);
        }

        zss_json_error('CLEAR_FAILED', 'Failed to clear log', 500);
    }

    $level = $_GET['level'] ?? 'all';
    $limit = intval($_GET['limit'] ?? 200);

    $status = LogService::getLogStatus();
    $logResult = LogService::getLogs($limit, $level);
    $logs = $logResult['logs'] ?? [];
    $error = $logResult['error'] ?? null;

    zss_json_success([
        'logs' => $logs,
        'total' => count($logs),
        'status' => $status,
        'read_error' => $error,
    ]);
});
