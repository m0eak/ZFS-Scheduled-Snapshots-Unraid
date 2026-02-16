<?php

class ZfsScheduledSnapshots {
    
    // Logging function
    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        // Log to syslog for Unraid
        openlog("ZfsScheduledSnapshots", LOG_PID | LOG_PERROR, LOG_LOCAL0);
        syslog(LOG_INFO, $message);
        closelog();
        
        // Optionally log to a file for debugging
        // file_put_contents('/var/log/zfs-scheduled-snapshots.log', $logEntry, FILE_APPEND);
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
            // Get frequency
            $freqResult = self::exec("zfs get -H -o value com.sun:auto-snapshot:frequency $name");
            if (!empty($freqResult['output']) && $freqResult['output'][0] !== '-') {
                 $data['frequency'] = $freqResult['output'][0];
            }

            // Get keep count
            $keepResult = self::exec("zfs get -H -o value com.sun:auto-snapshot:keep $name");
            if (!empty($keepResult['output']) && $keepResult['output'][0] !== '-') {
                 $data['keep'] = intval($keepResult['output'][0]);
            }
            
            // Get time (HH:MM)
            $timeResult = self::exec("zfs get -H -o value com.sun:auto-snapshot:time $name");
            if (!empty($timeResult['output']) && $timeResult['output'][0] !== '-') {
                 $data['time'] = $timeResult['output'][0];
            } else {
                 $data['time'] = '00:00'; // Default
            }

            // Get day (1-31 or 1-7)
            $dayResult = self::exec("zfs get -H -o value com.sun:auto-snapshot:day $name");
            if (!empty($dayResult['output']) && $dayResult['output'][0] !== '-') {
                 $data['day'] = intval($dayResult['output'][0]);
            } else {
                 $data['day'] = 1; // Default
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
        $cmd = "zfs list -t snapshot -H -p -o name,creation -S creation -d 1 $datasetName | grep \"@autosnap_\" | head -n 1";
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
    public static function createSnapshot($datasetName, $prefix = 'autosnap') {
        $timestamp = date('Y-m-d_H:i:s');
        $snapName = "{$datasetName}@{$prefix}_{$timestamp}";
        
        $result = self::exec("zfs snapshot $snapName");
        
        if ($result['return_var'] === 0) {
            self::log("Created snapshot: $snapName");
            return true;
        } else {
            self::log("Failed to create snapshot $snapName: " . implode("\n", $result['output']), 'ERROR');
            return false;
        }
    }

    // Prune snapshots
    public static function pruneSnapshots($datasetName, $keep, $prefix = 'autosnap') {
        if ($keep <= 0) return;

        // Get all auto snapshots sorted by creation (newest first because of -S creation)
        $cmd = "zfs list -t snapshot -H -o name -S creation -d 1 $datasetName | grep \"@{$prefix}_\"";
        
        $result = self::exec($cmd);
        $snapshots = $result['output'];
        
        $count = count($snapshots);
        if ($count <= $keep) {
            return;
        }

        // We want to keep the first $keep (newest), so delete from index $keep onwards
        $toDelete = array_slice($snapshots, $keep); 
        
        foreach ($toDelete as $snap) {
            self::exec("zfs destroy $snap");
            self::log("Pruned snapshot: $snap");
        }
    }
}
?>
