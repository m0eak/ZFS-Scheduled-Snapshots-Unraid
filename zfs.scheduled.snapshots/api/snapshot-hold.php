<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_api_run(function() {
    zss_require_action_request();
    $payload = zss_get_action_payload();

    $name = $payload['name'] ?? '';

    if (!SnapshotService::isManagedSnapshotName($name)) {
        zss_json_error('INVALID_SNAPSHOT', 'Invalid snapshot name', 400);
    }

    $result = SnapshotService::holdSnapshot($name);

    if ($result['success']) {
        zss_json_success(['message' => 'Hold added']);
    } else {
        zss_json_error('HOLD_FAILED', $result['error'], 500);
    }
});
