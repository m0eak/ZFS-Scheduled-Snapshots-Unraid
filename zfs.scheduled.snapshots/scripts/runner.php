<?php

// Include common library
require_once dirname(__DIR__) . '/include/common.php';

// Configuration: Intervals in seconds
$intervals = [
    '5min' => 300,
    '15min' => 900,
    'hourly' => 3600,
    'daily' => 86400,
    'weekly' => 604800,
    'monthly' => 2592000
];

// 1. Get all datasets that have snapshots enabled
$datasets = ZfsScheduledSnapshots::getDatasets();

foreach ($datasets as $name => $config) {
    $freq = $config['frequency'];
    $keep = $config['keep'];

    // Determine target interval
    if (!isset($intervals[$freq])) {
        // Fallback or skip invalid frequency
        // Assuming 'daily' as default if not matched, or skip
        $targetInterval = $intervals['daily'];
    } else {
        $targetInterval = $intervals[$freq];
    }

    // 2. Get latest snapshot time
    $latest = ZfsScheduledSnapshots::getLatestSnapshot($name);
    
    $shouldSnapshot = false;

    if ($latest) {
        $lastTime = $latest['timestamp'];
        $now = time();
        $diff = $now - $lastTime;

        if ($diff >= $targetInterval) {
            $shouldSnapshot = true;
            ZfsScheduledSnapshots::log("Dataset '$name': Last snapshot was $diff seconds ago (Target: $targetInterval). Taking snapshot.");
        } else {
            // Not time yet
            // ZfsScheduledSnapshots::log("Dataset '$name': Skipping. Last snapshot $diff seconds ago. Target: $targetInterval");
        }
    } else {
        // No previous auto-snapshot found, create one immediately
        $shouldSnapshot = true;
        ZfsScheduledSnapshots::log("Dataset '$name': No previous auto-snapshot found. Taking initial snapshot.");
    }

    // 3. Create snapshot if needed
    if ($shouldSnapshot) {
        $success = ZfsScheduledSnapshots::createSnapshot($name);
        
        // 4. Prune old snapshots if creation was successful
        if ($success) {
            ZfsScheduledSnapshots::pruneSnapshots($name, $keep);
        }
    }
}

?>
