<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

$datasets = DatasetService::getManagedDatasets();
if (isset($datasets['success']) && $datasets['success'] === false) {
    zss_json_error($datasets['code'] ?? 'DATASET_CATALOG_FAILED', $datasets['error'] ?? 'Failed to list managed datasets', 500);
}

$overview = DatasetService::getOverviewStats($datasets);
if (isset($overview['success']) && $overview['success'] === false) {
    zss_json_error($overview['code'] ?? 'DATASET_CATALOG_FAILED', $overview['error'] ?? 'Failed to summarize managed datasets', 500);
}

zss_json_success($overview);
