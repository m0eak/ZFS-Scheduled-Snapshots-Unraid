<?php

class SnapshotService {
    public static function listManagedSnapshots($datasetName) {
        $datasetName = escapeshellarg($datasetName);
        $cmd = "zfs list -t snapshot -H -p -o name,creation -S creation -d 1 {$datasetName} | grep \"@autosnap_\"";
        $result = ZfsScheduledSnapshots::exec($cmd);

        $snapshots = [];
        foreach ($result['output'] as $line) {
            $parts = preg_split('/\s+/', trim($line), 2);
            if (count($parts) < 2) {
                continue;
            }

            $name = $parts[0];
            $createdAt = intval($parts[1]);
            $holds = self::getHoldTags($name);

            $snapshots[] = [
                'name' => $name,
                'dataset' => strstr($name, '@', true),
                'short_name' => strstr($name, '@') ?: $name,
                'created_at' => $createdAt,
                'created_at_text' => date('Y-m-d H:i:s', $createdAt),
                'held' => in_array('autosnap', $holds, true),
                'hold_tags' => $holds,
                'origin' => 'autosnap',
                'destroyable' => !in_array('autosnap', $holds, true),
            ];
        }

        return $snapshots;
    }

    public static function countManagedSnapshots($datasetName) {
        return count(self::listManagedSnapshots($datasetName));
    }

    public static function countReadonlySnapshots($datasetName) {
        $count = 0;
        foreach (self::listManagedSnapshots($datasetName) as $snapshot) {
            if (!empty($snapshot['held'])) {
                $count++;
            }
        }
        return $count;
    }

    public static function getHoldTags($snapshotName) {
        $snapshotArg = escapeshellarg($snapshotName);
        $result = ZfsScheduledSnapshots::exec("zfs holds {$snapshotArg} 2>/dev/null");
        $tags = [];

        foreach ($result['output'] as $index => $line) {
            if ($index === 0) {
                continue;
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 2) {
                $tags[] = $parts[1];
            }
        }

        return array_values(array_unique($tags));
    }
}
