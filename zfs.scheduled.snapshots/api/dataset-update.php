<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    zss_json_error('METHOD_NOT_ALLOWED', 'Only POST is allowed', 405);
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$allowedNames = DatasetService::getManagedDatasetNames();
$errors = zss_validate_dataset_payload($payload, $allowedNames);
if (!empty($errors)) {
    zss_json_error('VALIDATION_FAILED', 'Payload validation failed', 422, [
        'fields' => $errors,
    ]);
}

$result = DatasetService::updateManagedDataset($payload);
if (empty($result['ok'])) {
    zss_json_error('DATASET_UPDATE_FAILED', 'Failed to update dataset properties', 500, [
        'results' => $result['results'] ?? [],
    ]);
}

zss_json_success($result['dataset'], [
    'results' => $result['results'] ?? [],
]);
