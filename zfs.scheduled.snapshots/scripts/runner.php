<?php

// Include plugin bootstrap
require_once dirname(__DIR__) . '/include/bootstrap.php';

ZfsScheduledSnapshots::log('Runner started');

// 1. Get all datasets that have snapshots enabled
$datasets = ZfsScheduledSnapshots::getDatasets();
$datasetCount = count($datasets);
ZfsScheduledSnapshots::log("Runner loaded {$datasetCount} enabled dataset(s)");

foreach ($datasets as $name => $config) {
    $freq = $config['frequency'];
    $keep = $config['keep'];
    $readonly = $config['readonly'] ?? false; // Readonly flag
    $retainDays = $config['retain_days'] ?? 0; // Retain days

    ZfsScheduledSnapshots::log("Dataset '{$name}': evaluating schedule (frequency={$freq}, keep={$keep}, readonly=" . ($readonly ? 'true' : 'false') . ", retain_days={$retainDays})");

    // 2. Determine if we should snapshot based on the pure schedule policy.
    $latest = ZfsScheduledSnapshots::getLatestSnapshot($name);
    $schedule = SchedulePolicy::evaluate(array_merge($config, [
        'latest_timestamp' => $latest ? $latest['timestamp'] : 0,
    ]));
    $shouldSnapshot = $schedule['due'];
    $reason = $schedule['reason'];

    // 3. Create snapshot if needed
    if ($shouldSnapshot) {
        ZfsScheduledSnapshots::log("Dataset '$name': {$reason}. Taking snapshot.");
        $success = ZfsScheduledSnapshots::createSnapshot($name, 'autosnap', $readonly);
        if ($success) {
            ZfsScheduledSnapshots::log("Dataset '{$name}': snapshot created successfully");
        } else {
            ZfsScheduledSnapshots::log("Dataset '{$name}': snapshot creation failed", 'ERROR');
        }
    } else {
        ZfsScheduledSnapshots::log("Dataset '{$name}': {$reason}");
    }

    // 4. Run retention maintenance independently from snapshot creation.
    ZfsScheduledSnapshots::log("Dataset '{$name}': starting maintenance phase");
    ZfsScheduledSnapshots::releaseExpiredAutosnapHolds($name, 'autosnap', $retainDays);
    ZfsScheduledSnapshots::pruneSnapshots($name, $keep, 'autosnap', $retainDays);
    ZfsScheduledSnapshots::log("Dataset '{$name}': maintenance phase finished");
}

ZfsScheduledSnapshots::log('Runner finished');

?>
