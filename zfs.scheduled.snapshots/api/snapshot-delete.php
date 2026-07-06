<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_api_run(function() {
    zss_require_action_request();
    $payload = zss_get_action_payload();

    $name = $payload['name'] ?? '';

    if (SnapshotService::validateSnapshotName($name, DatasetService::getManagedDatasetNames()) !== null) {
        zss_json_error('INVALID_SNAPSHOT', 'Invalid snapshot name', 400);
    }

    zss_require_action_confirmation($payload, $name);

    $result = SnapshotService::destroySnapshot($name);

    if ($result['success']) {
        zss_json_success(['message' => 'Snapshot deleted']);
    } else {
        if (($result['code'] ?? '') === 'SNAPSHOT_HELD') {
            zss_json_error('SNAPSHOT_HELD', $result['error'], 409);
        }

        zss_json_error('DELETE_FAILED', $result['error'], 500);
    }
});
