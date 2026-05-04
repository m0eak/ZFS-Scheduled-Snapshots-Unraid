<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

$datasets = DatasetService::listManagedDatasets();
$enabledCount = DatasetService::countEnabledDatasets($datasets);
$snapshotCount = 0;
$readonlySnapshotCount = 0;
$lastSnapshotAt = null;
$lastSnapshotDataset = null;
$warnings = [];

foreach ($datasets as $dataset) {
    $snapshotCount += intval($dataset['snapshot_count'] ?? 0);
    $readonlySnapshotCount += intval($dataset['readonly_snapshot_count'] ?? 0);

    $datasetLatest = $dataset['latest_snapshot_at'] ?? null;
    if ($datasetLatest !== null && ($lastSnapshotAt === null || $datasetLatest > $lastSnapshotAt)) {
        $lastSnapshotAt = $datasetLatest;
        $lastSnapshotDataset = $dataset['name'];
    }

    if (($dataset['enabled'] ?? false) && ($dataset['latest_snapshot_at'] ?? null) === null) {
        $warnings[] = 'Enabled dataset without managed snapshot: ' . $dataset['name'];
    }
}

zss_json_success([
    'dataset_count' => count($datasets),
    'enabled_count' => $enabledCount,
    'snapshot_count' => $snapshotCount,
    'readonly_snapshot_count' => $readonlySnapshotCount,
    'last_snapshot_at' => $lastSnapshotAt,
    'last_snapshot_dataset' => $lastSnapshotDataset,
    'last_runner_at' => null,
    'last_runner_status' => 'unknown',
    'warning_count' => count($warnings),
    'warnings' => $warnings,
]);
