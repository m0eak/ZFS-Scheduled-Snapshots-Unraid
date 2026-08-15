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

    $readonly = zss_normalize_bool($payload['readonly'] ?? false);
    $result = SnapshotService::createSnapshot($name, $readonly);

    if ($result['success']) {
        zss_json_success(['message' => 'Snapshot created']);
    }

    $status = !empty($result['snapshot_created']) ? 409 : 500;
    zss_json_error($result['code'] ?? 'CREATE_FAILED', $result['error'], $status, [
        'snapshot_created' => !empty($result['snapshot_created']),
        'snapshot_name' => $result['snapshot_name'] ?? null,
        'protected' => !empty($result['protected']),
    ]);
});
