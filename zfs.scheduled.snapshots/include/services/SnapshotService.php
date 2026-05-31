<?php

require_once __DIR__ . '/../common.php';

class SnapshotService {

    private static function quoteDatasetName($name) {
        return escapeshellarg($name);
    }

    private static function quoteSnapshotName($name) {
        return escapeshellarg($name);
    }

    private static function parseSnapshotName($fullName) {
        if (!is_string($fullName) || strpos($fullName, '@') === false) {
            return null;
        }

        [$dataset, $shortName] = explode('@', $fullName, 2);

        if ($dataset === '' || $shortName === '') {
            return null;
        }

        return [
            'dataset' => $dataset,
            'short_name' => $shortName,
        ];
    }

    private static function classifySnapshotShortName($shortName) {
        $autoPrefix = ZfsScheduledSnapshots::AUTO_SNAPSHOT_PREFIX . '_';
        $manualPrefix = ZfsScheduledSnapshots::MANUAL_SNAPSHOT_PREFIX . '_';

        if (strpos($shortName, $autoPrefix) === 0) {
            return [
                'origin' => 'autosnap',
                'managed' => true,
            ];
        }

        if (strpos($shortName, $manualPrefix) === 0) {
            return [
                'origin' => 'plugin_manual',
                'managed' => true,
            ];
        }

        return [
            'origin' => 'external',
            'managed' => false,
        ];
    }

    private static function buildSnapshotActions($holdTags) {
        $held = !empty($holdTags);

        return [
            'hold' => !$held,
            'release' => $held,
            'delete' => !$held,
            'rollback' => true,
        ];
    }

    private static function getSnapshotHoldTags($snapshotName) {
        $holdTags = [];
        $snapshotArg = self::quoteSnapshotName($snapshotName);
        $holdCheck = ZfsScheduledSnapshots::exec("zfs holds -H $snapshotArg 2>/dev/null");

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

    private static function hasAnyHold($snapshotName) {
        return !empty(self::getSnapshotHoldTags($snapshotName));
    }

    public static function validateOperableSnapshotName($name, $allowedDatasets) {
        $parsed = self::parseSnapshotName($name);
        if ($parsed === null) {
            return 'Invalid snapshot name';
        }

        if (!in_array($parsed['dataset'], $allowedDatasets, true)) {
            return 'Snapshot dataset does not exist';
        }

        return null;
    }

    public static function isManagedSnapshotName($name) {
        $parsed = self::parseSnapshotName($name);
        if ($parsed === null) {
            return false;
        }

        $classification = self::classifySnapshotShortName($parsed['short_name']);
        return $classification['managed'];
    }

    /**
     * 获取数据集的快照列表
     */
    public static function getDatasetSnapshots($datasetName) {
        $snapshots = [];
        $datasetArg = self::quoteDatasetName($datasetName);

        // 列出当前数据集下的全部一层快照；在 PHP 内部区分插件管理快照和外部快照。
        $result = ZfsScheduledSnapshots::exec("zfs list -t snapshot -H -p -o name,creation,userrefs -S creation -d 1 $datasetArg");
        
        if (empty($result['output'])) {
            return [];
        }

        foreach ($result['output'] as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 2) continue;

            $fullName = $parts[0];
            $creation = intval($parts[1]);
            $userrefs = isset($parts[2]) ? intval($parts[2]) : 0;

            $parsed = self::parseSnapshotName($fullName);
            if ($parsed === null) continue;

            $shortName = $parsed['short_name'];
            $classification = self::classifySnapshotShortName($shortName);

            // 检查是否有 hold。全部可见快照都可手动管理；有任意 hold 时需先 release 对应 tag 才能删除。
            $holdTags = self::getSnapshotHoldTags($fullName);
            $held = !empty($holdTags);

            $managed = $classification['managed'];
            $actions = self::buildSnapshotActions($holdTags);
            $destroyable = !$held;

            $snapshots[] = [
                'name' => $fullName,
                'short_name' => $shortName,
                'dataset' => $datasetName,
                'created_at' => $creation,
                'userrefs' => $userrefs,
                'held' => $held,
                'hold_tags' => $holdTags,
                'destroyable' => $destroyable,
                'origin' => $classification['origin'],
                'managed' => $managed,
                'actions' => $actions,
            ];
        }

        return $snapshots;
    }

    public static function rollbackSnapshot($snapshotName) {
        $snapshotArg = self::quoteSnapshotName($snapshotName);
        $result = ZfsScheduledSnapshots::exec("zfs rollback $snapshotArg");

        if ($result['return_var'] === 0) {
            ZfsScheduledSnapshots::log("Rolled back snapshot: $snapshotName");
            return [
                'success' => true,
            ];
        }

        return [
            'success' => false,
            'error' => !empty($result['output']) ? implode("\n", $result['output']) : 'Failed to rollback snapshot',
        ];
    }

    /**
     * 手动创建快照
     */
    public static function createSnapshot($datasetName, $readonly = false) {
        $result = ZfsScheduledSnapshots::createSnapshot($datasetName, ZfsScheduledSnapshots::MANUAL_SNAPSHOT_PREFIX, $readonly);
        
        if ($result) {
            return [
                'success' => true,
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to create snapshot',
        ];
    }

    /**
     * 销毁快照
     */
    public static function destroySnapshot($snapshotName) {
        $snapshotArg = self::quoteSnapshotName($snapshotName);

        if (self::hasAnyHold($snapshotName)) {
            return [
                'success' => false,
                'error' => 'Snapshot has hold tags. Release holds before deleting.',
                'code' => 'SNAPSHOT_HELD',
            ];
        }

        $result = ZfsScheduledSnapshots::exec("zfs destroy $snapshotArg");
        
        if ($result['return_var'] === 0) {
            ZfsScheduledSnapshots::log("Destroyed snapshot: $snapshotName");
            return [
                'success' => true,
            ];
        }

        return [
            'success' => false,
            'error' => !empty($result['output']) ? implode("\n", $result['output']) : 'Failed to destroy snapshot',
        ];
    }

    /**
     * 为快照添加 hold
     */
    public static function holdSnapshot($snapshotName, $tag = ZfsScheduledSnapshots::HOLD_TAG) {
        $snapshotArg = self::quoteSnapshotName($snapshotName);
        $tagArg = escapeshellarg($tag);
        $result = ZfsScheduledSnapshots::exec("zfs hold $tagArg $snapshotArg");
        
        if ($result['return_var'] === 0) {
            ZfsScheduledSnapshots::log("Added hold '$tag' to snapshot: $snapshotName");
            return [
                'success' => true,
            ];
        }

        return [
            'success' => false,
            'error' => !empty($result['output']) ? implode("\n", $result['output']) : 'Failed to add hold',
        ];
    }

    /**
     * 释放快照的 hold
     */
    public static function releaseSnapshot($snapshotName, $tag = ZfsScheduledSnapshots::HOLD_TAG) {
        $tag = trim((string) $tag);
        if ($tag === '') {
            return [
                'success' => false,
                'error' => 'Hold tag is required',
                'code' => 'INVALID_HOLD_TAG',
            ];
        }

        $holdTags = self::getSnapshotHoldTags($snapshotName);
        if (!in_array($tag, $holdTags, true)) {
            return [
                'success' => false,
                'error' => 'Hold tag does not exist on snapshot',
                'code' => 'HOLD_TAG_NOT_FOUND',
            ];
        }

        $snapshotArg = self::quoteSnapshotName($snapshotName);
        $tagArg = escapeshellarg($tag);
        $result = ZfsScheduledSnapshots::exec("zfs release $tagArg $snapshotArg");
        
        if ($result['return_var'] === 0) {
            ZfsScheduledSnapshots::log("Released hold '$tag' from snapshot: $snapshotName");
            return [
                'success' => true,
            ];
        }

        return [
            'success' => false,
            'error' => !empty($result['output']) ? implode("\n", $result['output']) : 'Failed to release hold',
        ];
    }
}
