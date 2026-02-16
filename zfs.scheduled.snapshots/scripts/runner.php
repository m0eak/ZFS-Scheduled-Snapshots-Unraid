<?php

// Include common library
require_once dirname(__DIR__) . '/include/common.php';

// Configuration: Intervals in seconds (for interval-based frequencies)
$intervals = [
    '5min' => 300,
    '15min' => 900,
    'hourly' => 3600
];

// 1. Get all datasets that have snapshots enabled
$datasets = ZfsScheduledSnapshots::getDatasets();

foreach ($datasets as $name => $config) {
    $freq = $config['frequency'];
    $keep = $config['keep'];
    $targetTime = $config['time']; // HH:MM
    $targetDay = $config['day'];   // 1-7 (Weekly) or 1-31 (Monthly)

    $shouldSnapshot = false;

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
                ZfsScheduledSnapshots::log("Dataset '$name': Last snapshot was $diff seconds ago (Target: $targetInterval). Taking snapshot.");
            }
        } else {
            // No previous snapshot, create immediately
            $shouldSnapshot = true;
            ZfsScheduledSnapshots::log("Dataset '$name': No previous auto-snapshot found. Taking initial snapshot.");
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
            // Target timestamp for TODAY
            $todayTarget = mktime($targetHour, $targetMinute, 0, $currentMonth, $currentDayNum, $currentYear);
            
            // Logic:
            // 1. We have passed the target time today ($now >= $todayTarget).
            // 2. The last snapshot was taken BEFORE today's target time (or never).
            //    Wait, checking if last snapshot < todayTarget covers both "yesterday" and "never".
            //    But we must ensure we don't snapshot twice if the cron runs at 10:05 and 10:10.
            //    If we snap at 10:05, $lastSnapshotTime becomes roughly $todayTarget.
            //    So $lastSnapshotTime < $todayTarget will be false. Correct.

            if ($now >= $todayTarget && $lastSnapshotTime < $todayTarget) {
                $shouldSnapshot = true;
                ZfsScheduledSnapshots::log("Dataset '$name': Daily schedule ($targetTime). Time passed and not yet executed today. Taking snapshot.");
            }

        } elseif ($freq === 'weekly') {
            // Target Day: 1 (Mon) - 7 (Sun)
            // Current Day of Week: date('N')
            $currentDow = intval(date('N', $now));
            
            // Calculate timestamp for THIS WEEK's target day/time
            // Logic: Start with "today at target time"
            $todayAtTargetTime = mktime($targetHour, $targetMinute, 0, $currentMonth, $currentDayNum, $currentYear);
            
            // Calculate day difference
            // e.g., Today is Wed(3), Target is Mon(1). Diff = 1 - 3 = -2 days. Target was 2 days ago.
            // e.g., Today is Mon(1), Target is Wed(3). Diff = 3 - 1 = +2 days. Target is in 2 days.
            $diffDays = $targetDay - $currentDow;
            
            $thisWeekTarget = strtotime("$diffDays days", $todayAtTargetTime);
            
            // Logic:
            // 1. We are past the target time for this week ($now >= $thisWeekTarget).
            // 2. The last snapshot was taken BEFORE this week's target time.
            
            if ($now >= $thisWeekTarget && $lastSnapshotTime < $thisWeekTarget) {
                 $shouldSnapshot = true;
                 ZfsScheduledSnapshots::log("Dataset '$name': Weekly schedule (Day $targetDay @ $targetTime). Target passed. Taking snapshot.");
            }

        } elseif ($freq === 'monthly') {
            // Target Day: 1-31
            // Handle Feb (28/29) or 30-day months
            $daysInMonth = intval(date('t', $now));
            $actualTargetDay = min($targetDay, $daysInMonth);
            
            $thisMonthTarget = mktime($targetHour, $targetMinute, 0, $currentMonth, $actualTargetDay, $currentYear);
            
            if ($now >= $thisMonthTarget && $lastSnapshotTime < $thisMonthTarget) {
                $shouldSnapshot = true;
                ZfsScheduledSnapshots::log("Dataset '$name': Monthly schedule (Day $actualTargetDay @ $targetTime). Target passed. Taking snapshot.");
            }
        }
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
