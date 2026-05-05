<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_api_run(function() {
    zss_require_action_request();
    $payload = zss_get_action_payload();

    $name = trim((string) ($payload['name'] ?? ''));
    $allowedNames = DatasetService::getManagedDatasetNames();
    $nameError = zss_validate_new_dataset_name($name, $allowedNames);

    if ($nameError !== null) {
        zss_json_error('INVALID_DATASET', $nameError, 400);
    }

    $result = DatasetService::createDataset($name);

    if ($result['success']) {
        zss_json_success([
            'message' => 'Dataset created',
            'dataset' => $result['dataset'],
        ]);
    } else {
        zss_json_error('CREATE_FAILED', $result['error'], 500);
    }
});
