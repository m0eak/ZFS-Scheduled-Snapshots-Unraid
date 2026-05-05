<?php

class ZfsScheduledSnapshots {
    
    const LOG_FILE = '/var/log/zfs-scheduled-snapshots.log';
    const LOG_MAX_LINES = 1000;
    const AUTO_SNAPSHOT_PREFIX = 'autosnap';
    const MANUAL_SNAPSHOT_PREFIX = 'manual';
    const HOLD_TAG = 'autosnap';
    
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
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        return [
            'output' => $output,
            'return_var' => $return_var
        ];
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

    // Create a snapshot
    public static function createSnapshot($datasetName, $prefix = self::AUTO_SNAPSHOT_PREFIX, $readonly = false) {
        $timestamp = date('Y-m-d_H:i:s');
        $snapName = "{$datasetName}@{$prefix}_{$timestamp}";
        $snapArg = escapeshellarg($snapName);
        
        $result = self::exec("zfs snapshot $snapArg");
        
        if ($result['return_var'] === 0) {
            self::log("Created snapshot: $snapName");
            
            // 如果是只读快照，添加 hold
            if ($readonly) {
                $tag = self::HOLD_TAG;
                $tagArg = escapeshellarg($tag);
                $holdResult = self::exec("zfs hold $tagArg $snapArg");
                if ($holdResult['return_var'] === 0) {
                    self::log("Added hold '$tag' to snapshot: $snapName");
                } else {
                    self::log("Failed to add hold to snapshot $snapName: " . implode("\n", $holdResult['output']), 'ERROR');
                }
            }
            
            return true;
        } else {
            self::log("Failed to create snapshot $snapName: " . implode("\n", $result['output']), 'ERROR');
            return false;
        }
    }

    // Prune snapshots
    public static function pruneSnapshots($datasetName, $keep, $prefix = self::AUTO_SNAPSHOT_PREFIX, $retainDays = 0) {
        if ($keep <= 0) return;

        // Get all auto snapshots sorted by creation (newest first because of -S creation)
        $datasetArg = escapeshellarg($datasetName);
        $cmd = "zfs list -t snapshot -H -o name,creation -S creation -d 1 $datasetArg | grep \"@{$prefix}_\"";
        
        $result = self::exec($cmd);
        $snapshots = $result['output'];
        
        $count = count($snapshots);
        if ($count <= $keep && $retainDays <= 0) {
            return;
        }

        // We want to keep the first $keep (newest), so delete from index $keep onwards
        $toDelete = array_slice($snapshots, $keep); 

        // Delete by count
        foreach ($toDelete as $line) {
            $snap = preg_split('/\s+/', $line)[0];
            $tag = self::HOLD_TAG;
            $tagArg = escapeshellarg($tag);
            $snapArg = escapeshellarg($snap);
            self::exec("zfs release $tagArg $snapArg 2>/dev/null");
            self::exec("zfs destroy $snapArg");
            self::log("Pruned snapshot (count): $snap");
        }

        // Delete by retain days
        if ($retainDays > 0) {
            $expireTs = time() - ($retainDays * 86400);
            foreach ($snapshots as $line) {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) < 2) continue;
                $snap = $parts[0];
                $ctime = strtotime($parts[1]);
                if ($ctime !== false && $ctime < $expireTs) {
                    $tag = self::HOLD_TAG;
                    $tagArg = escapeshellarg($tag);
                    $snapArg = escapeshellarg($snap);
                    self::exec("zfs release $tagArg $snapArg 2>/dev/null");
                    self::exec("zfs destroy $snapArg");
                    self::log("Pruned snapshot (expired): $snap");
                }
            }
        }
    }
}
?>
