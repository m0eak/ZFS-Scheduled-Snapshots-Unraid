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

    // 2. Recover an incomplete automatic hold before creating or pruning snapshots.
    $recovery = ZfsScheduledSnapshots::recoverPendingAutoHold($name);
    if (!empty($recovery['pending'])) {
        ZfsScheduledSnapshots::log("Dataset '{$name}': pending automatic hold remains unresolved; skipping snapshot creation and maintenance", 'ERROR');
        continue;
    }

    // 3. Determine if we should snapshot based on the pure schedule policy.
    $latest = ZfsScheduledSnapshots::getLatestSnapshot($name);
    $schedule = SchedulePolicy::evaluate(array_merge($config, [
        'latest_timestamp' => $latest ? $latest['timestamp'] : 0,
    ]));
    $shouldSnapshot = $schedule['due'];
    $reason = $schedule['reason'];

    // 4. Create snapshot if needed
    $maintenanceAllowed = true;
    if ($shouldSnapshot) {
        ZfsScheduledSnapshots::log("Dataset '$name': {$reason}. Taking snapshot.");
        $snapshotResult = ZfsScheduledSnapshots::createSnapshot(
            $name,
            ZfsScheduledSnapshots::AUTO_SNAPSHOT_PREFIX,
            $readonly,
            ZfsScheduledSnapshots::AUTO_HOLD_TAG,
            $readonly
        );
        if ($snapshotResult['success']) {
            ZfsScheduledSnapshots::log("Dataset '{$name}': snapshot created successfully");
        } elseif (!empty($snapshotResult['snapshot_created'])) {
            $maintenanceAllowed = false;
            ZfsScheduledSnapshots::log("Dataset '{$name}': snapshot was created but readonly protection failed; skipping maintenance for this pass", 'ERROR');
        } else {
            $maintenanceAllowed = false;
            ZfsScheduledSnapshots::log("Dataset '{$name}': snapshot creation failed; skipping maintenance for this pass", 'ERROR');
        }
    } else {
        ZfsScheduledSnapshots::log("Dataset '{$name}': {$reason}");
    }

    // 5. Run retention maintenance independently from normal snapshot creation.
    if ($maintenanceAllowed) {
        ZfsScheduledSnapshots::log("Dataset '{$name}': starting maintenance phase");
        ZfsScheduledSnapshots::releaseExpiredAutosnapHolds($name, ZfsScheduledSnapshots::AUTO_SNAPSHOT_PREFIX, $retainDays);
        ZfsScheduledSnapshots::pruneSnapshots($name, $keep, ZfsScheduledSnapshots::AUTO_SNAPSHOT_PREFIX, $retainDays);
        ZfsScheduledSnapshots::log("Dataset '{$name}': maintenance phase finished");
    }
}

ZfsScheduledSnapshots::log('Runner finished');

?>
