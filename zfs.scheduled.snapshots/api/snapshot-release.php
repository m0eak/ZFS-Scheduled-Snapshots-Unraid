<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_api_run(function() {
    zss_require_action_request();
    $payload = zss_get_action_payload();

    $name = $payload['name'] ?? '';
    $tag = $payload['tag'] ?? ZfsScheduledSnapshots::HOLD_TAG;

    if (SnapshotService::validateOperableSnapshotName($name, DatasetService::getManagedDatasetNames()) !== null) {
        zss_json_error('INVALID_SNAPSHOT', 'Invalid snapshot name', 400);
    }

    zss_require_action_confirmation($payload, $name . ':' . $tag);

    $result = SnapshotService::releaseSnapshot($name, $tag);

    if ($result['success']) {
        zss_json_success(['message' => 'Hold released']);
    } else {
        if (in_array(($result['code'] ?? ''), ['INVALID_HOLD_TAG', 'HOLD_TAG_NOT_FOUND'], true)) {
            zss_json_error($result['code'], $result['error'], 400);
        }

        zss_json_error('RELEASE_FAILED', $result['error'], 500);
    }
});
