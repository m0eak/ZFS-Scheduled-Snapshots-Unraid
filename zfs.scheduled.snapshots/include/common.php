<?php

require_once __DIR__ . '/ZfsCommand.php';
require_once __DIR__ . '/RetentionPolicy.php';

class ZfsScheduledSnapshots {
    
    const LOG_FILE = '/var/log/zfs-scheduled-snapshots.log';
    const LOG_MAX_LINES = 1000;
    const AUTO_SNAPSHOT_PREFIX = 'autosnap';
    const MANUAL_SNAPSHOT_PREFIX = 'manual';
    const AUTO_HOLD_TAG = 'zss_auto';
    const LEGACY_HOLD_TAG = 'autosnap';
    const MANUAL_HOLD_TAG = 'zss_manual';
    const PENDING_AUTO_HOLD_PROPERTY = 'com.sun:auto-snapshot:pending-hold';
    
    // Logging function
    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        // Log to file for WebUI viewing first
        $logDir = dirname(self::LOG_FILE);
        if (!is_dir($logDir)) {
            error_log('ZfsScheduledSnapshots: log directory does not exist: ' . $logDir);
        } elseif (!is_writable($logDir)) {
            error_log('ZfsScheduledSnapshots: log directory is not writable: ' . $logDir);
        } else {
            $writeResult = @file_put_contents(self::LOG_FILE, $logEntry, FILE_APPEND);
            if ($writeResult === false) {
                error_log('ZfsScheduledSnapshots: failed to write log file: ' . self::LOG_FILE);
            } else {
                // Rotate log if too big (keep last N lines)
                $lines = @file(self::LOG_FILE);
                if ($lines && count($lines) > self::LOG_MAX_LINES) {
                    $trimmed = array_slice($lines, -self::LOG_MAX_LINES);
                    @file_put_contents(self::LOG_FILE, implode('', $trimmed));
                }
            }
        }

        // Log to syslog for Unraid when available
        if (function_exists('openlog') && function_exists('syslog') && function_exists('closelog')) {
            @openlog("ZfsScheduledSnapshots", LOG_PID | LOG_PERROR, LOG_LOCAL0);
            @syslog(LOG_INFO, "[$level] $message");
            @closelog();
        }
    }

    // Execute a shell command
    public static function exec($command) {
        return ZfsCommand::runShell($command);
    }

    // Get all datasets with their relevant properties
    public static function getDatasets() {
        // We need: name, auto-snapshot, frequency, keep
        // Properties: com.sun:auto-snapshot, com.sun:auto-snapshot:frequency, com.sun:auto-snapshot:keep
        $datasets = [];
        
        // 1. Find datasets with auto-snapshot=true
        $result = self::exec("zfs get -H -o name,value -t filesystem,volume com.sun:auto-snapshot");
        
        if ($result['return_var'] !== 0) {
            self::log("Error getting ZFS datasets: " . implode("\n", $result['output']), 'ERROR');
            return [];
        }

        foreach ($result['output'] as $line) {
            $parts = explode("\t", $line);
            if (count($parts) >= 2) {
                $name = $parts[0];
                $value = $parts[1];
                
                if ($value === 'true') {
                    $datasets[$name] = [
                        'name' => $name,
                        'enabled' => true,
                        'frequency' => 'daily', // Default
                        'keep' => 31 // Default
                    ];
                }
            }
        }

        // 2. Fetch frequency and keep for enabled datasets
        foreach ($datasets as $name => &$data) {
            $datasetArg = escapeshellarg($name);

            // Get frequency
            $freqResult = self::exec("zfs get -H -o value com.sun:auto-snapshot:frequency $datasetArg");
            if (!empty($freqResult['output']) && $freqResult['output'][0] !== '-') {
                 $data['frequency'] = $freqResult['output'][0];
            }

            // Get keep count
            $keepResult = self::exec("zfs get -H -o value com.sun:auto-snapshot:keep $datasetArg");
            if (!empty($keepResult['output']) && $keepResult['output'][0] !== '-') {
                 $data['keep'] = intval($keepResult['output'][0]);
            }
            
            // Get time (HH:MM)
            $timeResult = self::exec("zfs get -H -o value com.sun:auto-snapshot:time $datasetArg");
            if (!empty($timeResult['output']) && $timeResult['output'][0] !== '-') {
                 $data['time'] = $timeResult['output'][0];
            } else {
                 $data['time'] = '00:00'; // Default
            }

            // Get day (1-31 or 1-7)
            $dayResult = self::exec("zfs get -H -o value com.sun:auto-snapshot:day $datasetArg");
            if (!empty($dayResult['output']) && $dayResult['output'][0] !== '-') {
                 $data['day'] = intval($dayResult['output'][0]);
            } else {
                 $data['day'] = 1; // Default
            }

            // Get readonly flag
            $readonlyResult = self::exec("zfs get -H -o value com.sun:auto-snapshot:readonly $datasetArg");
            if (!empty($readonlyResult['output']) && $readonlyResult['output'][0] !== '-') {
                 $data['readonly'] = ($readonlyResult['output'][0] === 'true');
            } else {
                 $data['readonly'] = false; // Default
            }

            // Get retain days
            $retainResult = self::exec("zfs get -H -o value com.sun:auto-snapshot:retain-days $datasetArg");
            if (!empty($retainResult['output']) && $retainResult['output'][0] !== '-') {
                 $data['retain_days'] = intval($retainResult['output'][0]);
            } else {
                 $data['retain_days'] = 0; // Default (disabled)
            }
        }

        return $datasets;
    }

    // Get latest auto-snapshot for a dataset
    public static function getLatestSnapshot($datasetName) {
        // List snapshots, filter by name pattern 'autosnap-*', sort by creation time desc, take head 1
        // Name pattern assumed: autosnap-YYYY-MM-DD-HH-MM-SS or similar. 
        // We will use a standard format: autosnap_YYYY-MM-DD_HH:MM:SS
        
        // Use -p for unix timestamp to be safer.
        $prefix = self::AUTO_SNAPSHOT_PREFIX;
        $datasetArg = escapeshellarg($datasetName);
        $cmd = "zfs list -t snapshot -H -p -o name,creation -S creation -d 1 $datasetArg | grep \"@{$prefix}_\" | head -n 1";
        $result = self::exec($cmd);
        
        if (empty($result['output'])) {
            return null;
        }
        
        $parts = preg_split('/\s+/', $result['output'][0]);
        if (count($parts) >= 2) {
            return [
                'name' => $parts[0],
                'timestamp' => intval($parts[1])
            ];
        }
        
        return null;
    }

    public static function setPendingAutoHoldSnapshot($datasetName, $snapshotName) {
        $datasetArg = escapeshellarg($datasetName);
        $assignmentArg = escapeshellarg(self::PENDING_AUTO_HOLD_PROPERTY . '=' . $snapshotName);
        $result = static::exec("zfs set $assignmentArg $datasetArg");

        if ($result['return_var'] === 0) {
            static::log("Recorded pending automatic hold for snapshot: $snapshotName", 'ERROR');
            return true;
        }

        $error = !empty($result['output']) ? implode("\n", $result['output']) : 'failed to set pending hold property';
        static::log("Failed to record pending automatic hold for snapshot $snapshotName: $error", 'ERROR');
        return false;
    }

    public static function getPendingAutoHoldSnapshot($datasetName) {
        $datasetArg = escapeshellarg($datasetName);
        $propertyArg = escapeshellarg(self::PENDING_AUTO_HOLD_PROPERTY);
        $result = static::exec("zfs get -H -o value $propertyArg $datasetArg");
        $value = trim((string) ($result['output'][0] ?? ''));

        return $value === '' || $value === '-' ? null : $value;
    }

    public static function clearPendingAutoHoldSnapshot($datasetName) {
        $datasetArg = escapeshellarg($datasetName);
        $propertyArg = escapeshellarg(self::PENDING_AUTO_HOLD_PROPERTY);
        $result = static::exec("zfs inherit $propertyArg $datasetArg");

        if ($result['return_var'] === 0) {
            static::log("Cleared pending automatic hold marker for dataset: $datasetName");
            return true;
        }

        $error = !empty($result['output']) ? implode("\n", $result['output']) : 'failed to clear pending hold property';
        static::log("Failed to clear pending automatic hold marker for dataset $datasetName: $error", 'WARN');
        return false;
    }

    public static function recoverPendingAutoHold($datasetName) {
        $snapshotName = static::getPendingAutoHoldSnapshot($datasetName);
        if ($snapshotName === null) {
            return [
                'pending' => false,
                'recovered' => false,
            ];
        }

        if (!static::snapshotExists($snapshotName)) {
            if (!static::clearPendingAutoHoldSnapshot($datasetName)) {
                return [
                    'pending' => true,
                    'recovered' => false,
                    'snapshot_name' => $snapshotName,
                    'error' => 'pending snapshot no longer exists and its marker could not be cleared',
                ];
            }

            static::log("Cleared stale pending automatic hold marker for missing snapshot: $snapshotName", 'WARN');
            return [
                'pending' => false,
                'recovered' => false,
                'snapshot_name' => $snapshotName,
                'stale' => true,
            ];
        }

        $snapshotArg = escapeshellarg($snapshotName);
        $tagArg = escapeshellarg(self::AUTO_HOLD_TAG);
        $result = static::exec("zfs hold $tagArg $snapshotArg");
        if ($result['return_var'] !== 0) {
            $error = !empty($result['output']) ? implode("\n", $result['output']) : 'failed to add automatic hold';
            static::log("Pending automatic hold recovery failed for $snapshotName: $error", 'ERROR');
            return [
                'pending' => true,
                'recovered' => false,
                'snapshot_name' => $snapshotName,
                'error' => $error,
            ];
        }

        if (!static::clearPendingAutoHoldSnapshot($datasetName)) {
            return [
                'pending' => true,
                'recovered' => false,
                'snapshot_name' => $snapshotName,
                'error' => 'automatic hold was restored but the pending marker could not be cleared',
            ];
        }

        static::log("Recovered automatic hold for snapshot: $snapshotName");
        return [
            'pending' => false,
            'recovered' => true,
            'snapshot_name' => $snapshotName,
        ];
    }

    // Create a snapshot and report whether readonly protection was applied.
    public static function createSnapshot($datasetName, $prefix = self::AUTO_SNAPSHOT_PREFIX, $readonly = false, $holdTag = self::AUTO_HOLD_TAG, $trackPendingAutoHold = false) {
        $timestamp = date('Y-m-d_H:i:s');
        $snapName = "{$datasetName}@{$prefix}_{$timestamp}";
        $snapArg = escapeshellarg($snapName);

        if ($readonly && $trackPendingAutoHold && !static::setPendingAutoHoldSnapshot($datasetName, $snapName)) {
            return [
                'success' => false,
                'snapshot_created' => false,
                'protected' => false,
                'snapshot_name' => $snapName,
                'error' => 'Failed to record automatic hold recovery marker',
                'code' => 'PENDING_AUTO_HOLD_MARK_FAILED',
            ];
        }

        $result = static::exec("zfs snapshot $snapArg");
        if ($result['return_var'] !== 0) {
            $error = !empty($result['output']) ? implode("\n", $result['output']) : 'snapshot creation failed';
            if ($readonly && $trackPendingAutoHold) {
                static::clearPendingAutoHoldSnapshot($datasetName);
            }
            static::log("Failed to create snapshot $snapName: $error", 'ERROR');
            return [
                'success' => false,
                'snapshot_created' => false,
                'protected' => false,
                'snapshot_name' => $snapName,
                'error' => $error,
            ];
        }

        static::log("Created snapshot: $snapName");

        if (!$readonly) {
            return [
                'success' => true,
                'snapshot_created' => true,
                'protected' => false,
                'snapshot_name' => $snapName,
            ];
        }

        $tag = trim((string) $holdTag);
        if ($tag === '') {
            $error = 'hold tag is required';
            static::log("Snapshot created but no hold tag was configured for $snapName", 'ERROR');
            return [
                'success' => false,
                'snapshot_created' => true,
                'protected' => false,
                'snapshot_name' => $snapName,
                'error' => $error,
                'code' => 'INVALID_HOLD_TAG',
            ];
        }

        $tagArg = escapeshellarg($tag);
        $holdResult = static::exec("zfs hold $tagArg $snapArg");
        if ($holdResult['return_var'] === 0) {
            $pendingMarker = false;
            if ($trackPendingAutoHold && !static::clearPendingAutoHoldSnapshot($datasetName)) {
                $pendingMarker = true;
                static::log("Automatic hold was added but the recovery marker remains for $snapName", 'WARN');
            }

            static::log("Added hold '$tag' to snapshot: $snapName");
            return [
                'success' => true,
                'snapshot_created' => true,
                'protected' => true,
                'snapshot_name' => $snapName,
                'hold_tag' => $tag,
                'pending_marker' => $pendingMarker,
            ];
        }

        $error = !empty($holdResult['output']) ? implode("\n", $holdResult['output']) : 'hold creation failed';
        static::log("Snapshot created but failed to add hold '$tag' to $snapName: $error", 'ERROR');

        return [
            'success' => false,
            'snapshot_created' => true,
            'protected' => false,
            'snapshot_name' => $snapName,
            'error' => $error,
            'code' => 'SNAPSHOT_HOLD_FAILED',
        ];
    }

    private static function getSnapshotHoldTags($snapshotName) {
        $holdTags = [];
        $snapshotArg = escapeshellarg($snapshotName);
        $holdCheck = static::exec("zfs holds -H $snapshotArg 2>/dev/null");

        if (!empty($holdCheck['output'])) {
            foreach ($holdCheck['output'] as $holdLine) {
                $holdParts = preg_split('/\s+/', trim($holdLine));
                if (count($holdParts) >= 2 && !in_array($holdParts[1], $holdTags, true)) {
                    $holdTags[] = $holdParts[1];
                }
            }
        }

        return $holdTags;
    }

    private static function hasAutomaticHold($snapshotName) {
        return in_array(self::AUTO_HOLD_TAG, static::getSnapshotHoldTags($snapshotName), true);
    }

    private static function snapshotExists($snapshotName) {
        $snapshotArg = escapeshellarg($snapshotName);
        $result = static::exec("zfs list -H -o name -t snapshot $snapshotArg");
        return $result['return_var'] === 0 && !empty($result['output']);
    }

    private static function releaseAutomaticHold($snapshotName, $reason) {
        if (!static::hasAutomaticHold($snapshotName)) {
            return;
        }

        $snapshotArg = escapeshellarg($snapshotName);
        $tagArg = escapeshellarg(self::AUTO_HOLD_TAG);
        $result = static::exec("zfs release $tagArg $snapshotArg");

        if ($result['return_var'] === 0) {
            static::log("Released automatic hold from snapshot ($reason): $snapshotName");
            return;
        }

        $error = !empty($result['output']) ? implode("\n", $result['output']) : 'release failed';
        static::log("Failed to release automatic hold from snapshot ($reason): $snapshotName: $error", 'WARN');
    }

    public static function releaseExpiredAutosnapHolds($datasetName, $prefix = self::AUTO_SNAPSHOT_PREFIX, $retainDays = 0) {
        if ($retainDays <= 0) return;

        $datasetArg = escapeshellarg($datasetName);
        $cmd = "zfs list -t snapshot -H -p -o name,creation -S creation -d 1 $datasetArg | grep \"@{$prefix}_\"";
        $result = static::exec($cmd);

        if (empty($result['output'])) {
            return;
        }

        $expireTs = time() - ($retainDays * 86400);
        foreach ($result['output'] as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 2) continue;

            $snap = $parts[0];
            $ctime = intval($parts[1]);
            if ($ctime > 0 && $ctime < $expireTs) {
                static::releaseAutomaticHold($snap, 'expired');
            }
        }
    }

    public static function hasUnprotectedAutosnapSnapshots($datasetName) {
        $datasetArg = escapeshellarg($datasetName);
        $prefix = self::AUTO_SNAPSHOT_PREFIX;
        $result = static::exec("zfs list -t snapshot -H -o name -d 1 $datasetArg | grep \"@{$prefix}_\"");

        foreach ($result['output'] ?? [] as $snapshotName) {
            $holdTags = static::getSnapshotHoldTags(trim($snapshotName));
            if (!in_array(self::AUTO_HOLD_TAG, $holdTags, true)) {
                return true;
            }
        }

        return false;
    }

    private static function destroyPrunedSnapshot($snap, $reason) {
        $snapArg = escapeshellarg($snap);
        $result = self::exec("zfs destroy $snapArg");

        if ($result['return_var'] === 0) {
            self::log("Pruned snapshot ($reason): $snap");
            return;
        }

        $error = !empty($result['output']) ? implode("\n", $result['output']) : 'destroy failed';
        self::log("Skipped pruning snapshot ($reason): $snap: $error", 'WARN');
    }

    // Prune snapshots
    public static function pruneSnapshots($datasetName, $keep, $prefix = self::AUTO_SNAPSHOT_PREFIX, $retainDays = 0) {
        if ($keep <= 0) return;

        // Get all auto snapshots sorted by creation (newest first because of -S creation)
        $datasetArg = escapeshellarg($datasetName);
        $cmd = "zfs list -t snapshot -H -p -o name,creation -S creation -d 1 $datasetArg | grep \"@{$prefix}_\"";
        
        $result = self::exec($cmd);
        $snapshots = [];
        foreach ($result['output'] as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 2) continue;

            $snapshots[] = [
                'name' => $parts[0],
                'creation' => intval($parts[1]),
            ];
        }
        
        $toDelete = RetentionPolicy::selectPruneCandidates($snapshots, $keep);
        if (empty($toDelete)) {
            return;
        }

        // Delete by count
        foreach ($toDelete as $snap) {
            self::destroyPrunedSnapshot($snap, 'count');
        }

    }
}
?>
