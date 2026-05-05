<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_api_run(function() {
    zss_require_action_request();
    $payload = zss_get_action_payload();

    $name = $payload['name'] ?? '';

    if (empty($name) || strpos($name, '@autosnap_') === false) {
        zss_json_error('INVALID_SNAPSHOT', 'Invalid snapshot name', 400);
    }

    $result = SnapshotService::destroySnapshot($name);

    if ($result['success']) {
        zss_json_success(['message' => 'Snapshot deleted']);
    } else {
        zss_json_error('DELETE_FAILED', $result['error'], 500);
    }
});
