<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

$name = $_GET['name'] ?? '';
$allowedNames = DatasetService::getManagedDatasetNames();
$nameError = zss_validate_dataset_name($name, $allowedNames);

if ($nameError !== null) {
    zss_json_error('INVALID_DATASET', $nameError, 400);
}

$dataset = DatasetService::getManagedDataset($name);
if ($dataset === null) {
    zss_json_error('DATASET_NOT_FOUND', 'Dataset not found', 404);
}

zss_json_success($dataset);
