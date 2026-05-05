<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_api_run(function() {
    zss_require_action_request();
    $payload = zss_get_action_payload();

    $allowedNames = DatasetService::getManagedDatasetNames();
    $errors = zss_validate_dataset_payload($payload, $allowedNames);
    if (!empty($errors)) {
        zss_json_error('VALIDATION_FAILED', 'Payload validation failed', 422, [
            'fields' => $errors,
        ]);
    }

    $name = $payload['name'];
    $config = [
        'enabled' => zss_normalize_bool($payload['enabled'] ?? false),
        'frequency' => $payload['frequency'] ?? 'daily',
        'keep' => intval($payload['keep'] ?? 31),
        'time' => $payload['time'] ?? '00:00',
        'day' => intval($payload['day'] ?? 1),
        'readonly' => zss_normalize_bool($payload['readonly'] ?? false),
        'retain_days' => intval($payload['retain_days'] ?? 0),
    ];

    $result = DatasetService::updateDatasetConfig($name, $config);
    if (empty($result['success'])) {
        zss_json_error('DATASET_UPDATE_FAILED', $result['error'] ?? 'Failed to update dataset properties', 500);
    }

    zss_json_success($result['dataset']);
});
