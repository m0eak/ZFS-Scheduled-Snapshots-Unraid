<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_api_run(function() {
    zss_require_action_request();
    $payload = zss_get_action_payload();

    $name = $payload['name'] ?? '';
    $allowedNames = DatasetService::getManagedDatasetNames();
    $nameError = zss_validate_dataset_name($name, $allowedNames);

    if ($nameError !== null) {
        zss_json_error('INVALID_DATASET', $nameError, 400);
    }

    $result = SnapshotService::createSnapshot($name, $payload['readonly'] ?? false);

    if ($result['success']) {
        zss_json_success(['message' => 'Snapshot created']);
    } else {
        zss_json_error('CREATE_FAILED', $result['error'], 500);
    }
});
