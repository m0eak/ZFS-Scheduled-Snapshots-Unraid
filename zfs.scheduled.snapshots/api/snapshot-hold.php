<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_api_run(function() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        zss_json_error('METHOD_NOT_ALLOWED', 'Only POST is allowed', 405);
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $name = $payload['name'] ?? '';

    if (empty($name) || strpos($name, '@autosnap_') === false) {
        zss_json_error('INVALID_SNAPSHOT', 'Invalid snapshot name', 400);
    }

    $result = SnapshotService::holdSnapshot($name, 'autosnap');

    if ($result['success']) {
        zss_json_success(['message' => 'Hold added']);
    } else {
        zss_json_error('HOLD_FAILED', $result['error'], 500);
    }
});
