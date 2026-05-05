<?php

require_once __DIR__ . '/../common.php';

class SnapshotService {

    private static function quoteDatasetName($name) {
        return escapeshellarg($name);
    }

    private static function quoteSnapshotName($name) {
        return escapeshellarg($name);
    }

    public static function isManagedSnapshotName($name) {
        if (!is_string($name) || strpos($name, '@') === false) {
            return false;
        }

        $shortName = substr($name, strpos($name, '@') + 1);
        $prefixes = [
            ZfsScheduledSnapshots::AUTO_SNAPSHOT_PREFIX . '_',
            ZfsScheduledSnapshots::MANUAL_SNAPSHOT_PREFIX . '_',
        ];

        foreach ($prefixes as $prefix) {
            if (strpos($shortName, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取数据集的快照列表
     */
    public static function getDatasetSnapshots($datasetName) {
        $snapshots = [];
        $datasetArg = self::quoteDatasetName($datasetName);

        // 列出插件管理的自动和手动快照，包含创建时间和用户属性
        $autoPrefix = ZfsScheduledSnapshots::AUTO_SNAPSHOT_PREFIX;
        $manualPrefix = ZfsScheduledSnapshots::MANUAL_SNAPSHOT_PREFIX;
        $result = ZfsScheduledSnapshots::exec("zfs list -t snapshot -H -p -o name,creation,userrefs -S creation -d 1 $datasetArg | grep -E \"@({$autoPrefix}|{$manualPrefix})_\"");
        
        if (empty($result['output'])) {
            return [];
        }

        foreach ($result['output'] as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 2) continue;

            $fullName = $parts[0];
            $creation = intval($parts[1]);
            $userrefs = isset($parts[2]) ? intval($parts[2]) : 0;

            // 提取快照短名称（去掉数据集前缀）
            $shortName = substr($fullName, strpos($fullName, '@') + 1);

            // 检查是否有 hold
            $held = false;
            $holdTags = [];
            $snapshotArg = self::quoteSnapshotName($fullName);
            $holdCheck = ZfsScheduledSnapshots::exec("zfs holds -H $snapshotArg 2>/dev/null");
            if (!empty($holdCheck['output'])) {
                foreach ($holdCheck['output'] as $holdLine) {
                    $holdParts = preg_split('/\s+/', $holdLine);
                    if (count($holdParts) >= 2) {
                        $tag = $holdParts[1];
                        $holdTags[] = $tag;
                        if ($tag === ZfsScheduledSnapshots::HOLD_TAG) {
                            $held = true;
                        }
                    }
                }
            }

            // 检查是否可以销毁（没有 hold）
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
            ];
        }

        return $snapshots;
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

        // 先释放 hold
        $tagArg = escapeshellarg(ZfsScheduledSnapshots::HOLD_TAG);
        ZfsScheduledSnapshots::exec("zfs release $tagArg $snapshotArg 2>/dev/null");
        
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
