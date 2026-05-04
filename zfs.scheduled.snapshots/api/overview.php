<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

$overview = DatasetService::getOverviewStats();
$datasets = DatasetService::getManagedDatasets();
$warnings = [];

foreach ($datasets as $dataset) {
    if (($dataset['enabled'] ?? false) && ($dataset['latest_snapshot_at'] ?? null) === null) {
        $warnings[] = 'Enabled dataset without managed snapshot: ' . $dataset['name'];
    }
}

zss_json_success(array_merge($overview, [
    'last_runner_at' => null,
    'last_runner_status' => 'unknown',
    'warning_count' => count($warnings),
    'warnings' => $warnings,
]));
