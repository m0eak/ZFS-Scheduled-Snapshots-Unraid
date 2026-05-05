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
