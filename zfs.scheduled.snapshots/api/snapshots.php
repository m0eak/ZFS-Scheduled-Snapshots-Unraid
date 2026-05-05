<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

$name = $_GET['name'] ?? '';
$allowedNames = DatasetService::getManagedDatasetNames();
$nameError = zss_validate_dataset_name($name, $allowedNames);

if ($nameError !== null) {
    zss_json_error('INVALID_DATASET', $nameError, 400);
}

$snapshots = SnapshotService::getDatasetSnapshots($name);

zss_json_success($snapshots);
