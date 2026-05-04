<?php

class DatasetService {
    public static function listManagedDatasets() {
        $datasets = ZfsScheduledSnapshots::getDatasets();
        $result = [];

        foreach ($datasets as $dataset) {
            $result[] = self::enrichDataset($dataset);
        }

        usort($result, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $result;
    }

    public static function getManagedDatasetNames() {
        $names = [];
        foreach (ZfsScheduledSnapshots::getDatasets() as $dataset) {
            $names[] = $dataset['name'];
        }
        sort($names);
        return $names;
    }

    public static function getManagedDataset($name) {
        foreach (ZfsScheduledSnapshots::getDatasets() as $dataset) {
            if (($dataset['name'] ?? null) === $name) {
                return self::enrichDataset($dataset);
            }
        }

        return null;
    }

    public static function updateManagedDataset($payload) {
        $name = $payload['name'];
        $enabled = zss_normalize_bool($payload['enabled'] ?? false) ? 'true' : 'false';
        $readonly = zss_normalize_bool($payload['readonly'] ?? false) ? 'true' : 'false';
        $frequency = $payload['frequency'] ?? 'daily';
        $keep = intval($payload['keep'] ?? 31);
        $time = $payload['time'] ?? '00:00';
        $day = intval($payload['day'] ?? 1);
        $retainDays = intval($payload['retain_days'] ?? 0);

        $commands = [
            "zfs set com.sun:auto-snapshot={$enabled} " . escapeshellarg($name),
            "zfs set com.sun:auto-snapshot:frequency=" . escapeshellarg($frequency) . ' ' . escapeshellarg($name),
            "zfs set com.sun:auto-snapshot:keep=" . escapeshellarg((string) $keep) . ' ' . escapeshellarg($name),
            "zfs set com.sun:auto-snapshot:time=" . escapeshellarg($time) . ' ' . escapeshellarg($name),
            "zfs set com.sun:auto-snapshot:day=" . escapeshellarg((string) $day) . ' ' . escapeshellarg($name),
            "zfs set com.sun:auto-snapshot:readonly={$readonly} " . escapeshellarg($name),
            "zfs set com.sun:auto-snapshot:retain-days=" . escapeshellarg((string) $retainDays) . ' ' . escapeshellarg($name),
        ];

        $results = [];
        foreach ($commands as $command) {
            $result = ZfsScheduledSnapshots::exec($command);
            $results[] = [
                'command' => $command,
                'return_var' => $result['return_var'],
                'output' => $result['output'],
            ];

            if ($result['return_var'] !== 0) {
                return [
                    'ok' => false,
                    'results' => $results,
                ];
            }
        }

        return [
            'ok' => true,
            'dataset' => self::getManagedDataset($name),
            'results' => $results,
        ];
    }

    public static function countEnabledDatasets($datasets) {
        $count = 0;
        foreach ($datasets as $dataset) {
            if (!empty($dataset['enabled'])) {
                $count++;
            }
        }
        return $count;
    }

    private static function enrichDataset($dataset) {
        $name = $dataset['name'];
        $snapshotCount = SnapshotService::countManagedSnapshots($name);
        $readonlySnapshotCount = SnapshotService::countReadonlySnapshots($name);
        $latest = ZfsScheduledSnapshots::getLatestSnapshot($name);

        return [
            'name' => $name,
            'enabled' => (bool) ($dataset['enabled'] ?? false),
            'frequency' => $dataset['frequency'] ?? 'daily',
            'keep' => intval($dataset['keep'] ?? 31),
            'time' => $dataset['time'] ?? '00:00',
            'day' => intval($dataset['day'] ?? 1),
            'readonly' => (bool) ($dataset['readonly'] ?? false),
            'retain_days' => intval($dataset['retain_days'] ?? 0),
            'snapshot_count' => $snapshotCount,
            'readonly_snapshot_count' => $readonlySnapshotCount,
            'latest_snapshot_name' => $latest['name'] ?? null,
            'latest_snapshot_at' => $latest['timestamp'] ?? null,
            'status' => self::buildDatasetStatus($dataset, $latest),
            'status_message' => null,
        ];
    }

    private static function buildDatasetStatus($dataset, $latest) {
        if (empty($dataset['enabled'])) {
            return 'disabled';
        }

        if ($latest === null) {
            return 'warn';
        }

        return 'ok';
    }
}
