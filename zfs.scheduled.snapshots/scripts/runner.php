<?php

// Include common library
require_once dirname(__DIR__) . '/include/common.php';

// Configuration: Intervals in seconds (for interval-based frequencies)
$intervals = [
    '5min' => 300,
    '15min' => 900,
    'hourly' => 3600
];

ZfsScheduledSnapshots::log('Runner started');

// 1. Get all datasets that have snapshots enabled
$datasets = ZfsScheduledSnapshots::getDatasets();
$datasetCount = count($datasets);
ZfsScheduledSnapshots::log("Runner loaded {$datasetCount} enabled dataset(s)");

foreach ($datasets as $name => $config) {
    $freq = $config['frequency'];
    $keep = $config['keep'];
    $targetTime = $config['time']; // HH:MM
    $targetDay = $config['day'];   // 1-7 (Weekly) or 1-31 (Monthly)
    $readonly = $config['readonly'] ?? false; // Readonly flag
    $retainDays = $config['retain_days'] ?? 0; // Retain days

    $shouldSnapshot = false;
    $reason = 'No snapshot needed';

    ZfsScheduledSnapshots::log("Dataset '{$name}': evaluating schedule (frequency={$freq}, keep={$keep}, readonly=" . ($readonly ? 'true' : 'false') . ", retain_days={$retainDays})");

    // 2. Determine if we should snapshot based on frequency type
    if (array_key_exists($freq, $intervals)) {
        // --- Interval Based (5min, 15min, hourly) ---
        $targetInterval = $intervals[$freq];
        $latest = ZfsScheduledSnapshots::getLatestSnapshot($name);

        if ($latest) {
            $lastTime = $latest['timestamp'];
            $now = time();
            $diff = $now - $lastTime;

            if ($diff >= $targetInterval) {
                $shouldSnapshot = true;
                $reason = "Last snapshot was {$diff} seconds ago (target: {$targetInterval})";
                ZfsScheduledSnapshots::log("Dataset '$name': {$reason}. Taking snapshot.");
            } else {
                $reason = "Last snapshot was {$diff} seconds ago (target: {$targetInterval}); skipping";
            }
        } else {
            // No previous snapshot, create immediately
            $shouldSnapshot = true;
            $reason = 'No previous auto-snapshot found';
            ZfsScheduledSnapshots::log("Dataset '$name': {$reason}. Taking initial snapshot.");
        }

    } else {
        // --- Schedule Based (daily, weekly, monthly) ---
        
        $latest = ZfsScheduledSnapshots::getLatestSnapshot($name);
        $lastSnapshotTime = $latest ? $latest['timestamp'] : 0;
        
        // Parse target time
        $parts = explode(':', $targetTime);
        $targetHour = intval($parts[0] ?? 0);
        $targetMinute = intval($parts[1] ?? 0);
        
        $now = time();
        $currentYear = intval(date('Y', $now));
        $currentMonth = intval(date('n', $now));
        $currentDayNum = intval(date('j', $now)); // 1-31

        if ($freq === 'daily') {
            $todayTarget = mktime($targetHour, $targetMinute, 0, $currentMonth, $currentDayNum, $currentYear);

            if ($now >= $todayTarget && $lastSnapshotTime < $todayTarget) {
                $shouldSnapshot = true;
                $reason = "Daily schedule {$targetTime} reached and not yet executed today";
                ZfsScheduledSnapshots::log("Dataset '$name': {$reason}. Taking snapshot.");
            } else {
                $reason = "Daily schedule {$targetTime} not due or already executed";
            }

        } elseif ($freq === 'weekly') {
            $currentDow = intval(date('N', $now));
            $todayAtTargetTime = mktime($targetHour, $targetMinute, 0, $currentMonth, $currentDayNum, $currentYear);
            $diffDays = $targetDay - $currentDow;
            $thisWeekTarget = strtotime("$diffDays days", $todayAtTargetTime);
            
            if ($now >= $thisWeekTarget && $lastSnapshotTime < $thisWeekTarget) {
                 $shouldSnapshot = true;
                 $reason = "Weekly schedule day {$targetDay} @ {$targetTime} reached";
                 ZfsScheduledSnapshots::log("Dataset '$name': {$reason}. Taking snapshot.");
            } else {
                 $reason = "Weekly schedule day {$targetDay} @ {$targetTime} not due or already executed";
            }

        } elseif ($freq === 'monthly') {
            $daysInMonth = intval(date('t', $now));
            $actualTargetDay = min($targetDay, $daysInMonth);
            $thisMonthTarget = mktime($targetHour, $targetMinute, 0, $currentMonth, $actualTargetDay, $currentYear);
            
            if ($now >= $thisMonthTarget && $lastSnapshotTime < $thisMonthTarget) {
                $shouldSnapshot = true;
                $reason = "Monthly schedule day {$actualTargetDay} @ {$targetTime} reached";
                ZfsScheduledSnapshots::log("Dataset '$name': {$reason}. Taking snapshot.");
            } else {
                $reason = "Monthly schedule day {$actualTargetDay} @ {$targetTime} not due or already executed";
            }
        }
    }

    // 3. Create snapshot if needed
    if ($shouldSnapshot) {
        $success = ZfsScheduledSnapshots::createSnapshot($name, 'autosnap', $readonly);
        
        // 4. Prune old snapshots if creation was successful
        if ($success) {
            ZfsScheduledSnapshots::log("Dataset '{$name}': snapshot created successfully, starting prune phase");
            ZfsScheduledSnapshots::pruneSnapshots($name, $keep, 'autosnap', $retainDays);
            ZfsScheduledSnapshots::log("Dataset '{$name}': prune phase finished");
        } else {
            ZfsScheduledSnapshots::log("Dataset '{$name}': snapshot creation failed", 'ERROR');
        }
    } else {
        ZfsScheduledSnapshots::log("Dataset '{$name}': {$reason}");
    }
}

ZfsScheduledSnapshots::log('Runner finished');

?>
