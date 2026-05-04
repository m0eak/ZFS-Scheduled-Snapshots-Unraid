<?php

require_once __DIR__ . '/../common.php';

class SnapshotService {

    /**
     * 获取数据集的快照列表
     */
    public static function getDatasetSnapshots($datasetName) {
        $snapshots = [];

        // 列出所有 autosnap 快照，包含创建时间和用户属性
        $result = ZfsScheduledSnapshots::exec("zfs list -t snapshot -H -p -o name,creation,userrefs -S creation -d 1 $datasetName | grep \"@autosnap_\"");
        
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
            $holdCheck = ZfsScheduledSnapshots::exec("zfs holds -H $fullName 2>/dev/null");
            if (!empty($holdCheck['output'])) {
                foreach ($holdCheck['output'] as $holdLine) {
                    $holdParts = preg_split('/\s+/', $holdLine);
                    if (count($holdParts) >= 2) {
                        $tag = $holdParts[1];
                        $holdTags[] = $tag;
                        if ($tag === 'autosnap') {
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
        $result = ZfsScheduledSnapshots::createSnapshot($datasetName, 'autosnap', $readonly);
        
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
        // 先释放 hold
        ZfsScheduledSnapshots::exec("zfs release autosnap $snapshotName 2>/dev/null");
        
        $result = ZfsScheduledSnapshots::exec("zfs destroy $snapshotName");
        
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
    public static function holdSnapshot($snapshotName, $tag = 'autosnap') {
        $result = ZfsScheduledSnapshots::exec("zfs hold $tag $snapshotName");
        
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
    public static function releaseSnapshot($snapshotName, $tag = 'autosnap') {
        $result = ZfsScheduledSnapshots::exec("zfs release $tag $snapshotName");
        
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
