<?php

require_once __DIR__ . '/../common.php';

class DatasetService {

    /**
     * 获取所有受管数据集的名称列表
     */
    public static function getManagedDatasetNames() {
        $names = [];
        $result = ZfsScheduledSnapshots::exec("zfs list -H -o name -t filesystem,volume");
        if (!empty($result['output'])) {
            foreach ($result['output'] as $line) {
                $names[] = trim($line);
            }
        }
        return $names;
    }

    /**
     * 获取单个数据集的完整配置
     */
    public static function getManagedDataset($name) {
        $allowedNames = self::getManagedDatasetNames();
        if (!in_array($name, $allowedNames)) {
            return null;
        }

        $config = [
            'name' => $name,
            'enabled' => false,
            'frequency' => 'daily',
            'keep' => 31,
            'time' => '00:00',
            'day' => 1,
            'readonly' => false,
            'retain_days' => 0,
            'snapshot_count' => 0,
            'readonly_snapshot_count' => 0,
            'latest_snapshot_at' => null,
        ];

        // 获取 ZFS 属性
        $props = ZfsScheduledSnapshots::exec("zfs get -H -o property,value com.sun:auto-snapshot,com.sun:auto-snapshot:frequency,com.sun:auto-snapshot:keep,com.sun:auto-snapshot:time,com.sun:auto-snapshot:day,com.sun:auto-snapshot:readonly,com.sun:auto-snapshot:retain-days $name");
        
        if (!empty($props['output'])) {
            foreach ($props['output'] as $line) {
                $parts = explode("\t", $line);
                if (count($parts) < 2) continue;
                
                $prop = $parts[0];
                $val = $parts[1];
                
                if ($prop === 'com.sun:auto-snapshot') $config['enabled'] = ($val === 'true');
                if ($prop === 'com.sun:auto-snapshot:frequency' && $val !== '-') $config['frequency'] = $val;
                if ($prop === 'com.sun:auto-snapshot:keep' && $val !== '-') $config['keep'] = intval($val);
                if ($prop === 'com.sun:auto-snapshot:time' && $val !== '-') $config['time'] = $val;
                if ($prop === 'com.sun:auto-snapshot:day' && $val !== '-') $config['day'] = intval($val);
                if ($prop === 'com.sun:auto-snapshot:readonly' && $val !== '-') $config['readonly'] = ($val === 'true');
                if ($prop === 'com.sun:auto-snapshot:retain-days' && $val !== '-') $config['retain_days'] = intval($val);
            }
        }

        // 获取快照统计
        $stats = self::getDatasetSnapshotStats($name);
        $config['snapshot_count'] = $stats['total'];
        $config['readonly_snapshot_count'] = $stats['readonly'];

        // 获取最新快照时间
        $latest = ZfsScheduledSnapshots::getLatestSnapshot($name);
        if ($latest) {
            $config['latest_snapshot_at'] = $latest['timestamp'];
        }

        return $config;
    }

    /**
     * 获取数据集快照统计
     */
    private static function getDatasetSnapshotStats($name) {
        $stats = [
            'total' => 0,
            'readonly' => 0,
        ];

        // 列出所有 autosnap 快照
        $result = ZfsScheduledSnapshots::exec("zfs list -t snapshot -H -o name -d 1 $name | grep \"@autosnap_\"");
        if (!empty($result['output'])) {
            $stats['total'] = count($result['output']);

            // 统计带 hold 的快照
            foreach ($result['output'] as $snap) {
                $holdCheck = ZfsScheduledSnapshots::exec("zfs holds -H $snap 2>/dev/null | grep -c autosnap");
                if (!empty($holdCheck['output']) && intval($holdCheck['output'][0]) > 0) {
                    $stats['readonly']++;
                }
            }
        }

        return $stats;
    }

    /**
     * 获取所有数据集的列表（精简信息）
     */
    public static function getManagedDatasets() {
        $names = self::getManagedDatasetNames();
        $datasets = [];

        foreach ($names as $name) {
            $ds = self::getManagedDataset($name);
            if ($ds) {
                // 只返回列表页需要的字段
                $datasets[] = [
                    'name' => $ds['name'],
                    'enabled' => $ds['enabled'],
                    'frequency' => $ds['frequency'],
                    'keep' => $ds['keep'],
                    'retain_days' => $ds['retain_days'],
                    'readonly' => $ds['readonly'],
                    'snapshot_count' => $ds['snapshot_count'],
                    'latest_snapshot_at' => $ds['latest_snapshot_at'],
                ];
            }
        }

        return $datasets;
    }

    /**
     * 更新数据集配置
     */
    public static function updateDatasetConfig($name, $config) {
        $allowedNames = self::getManagedDatasetNames();
        if (!in_array($name, $allowedNames)) {
            return [
                'success' => false,
                'error' => 'Dataset not found',
            ];
        }

        $errors = [];

        // 逐个设置属性
        if (isset($config['enabled'])) {
            $val = $config['enabled'] ? 'true' : 'false';
            $res = ZfsScheduledSnapshots::exec("zfs set com.sun:auto-snapshot=$val $name");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set enabled';
            }
        }

        if (isset($config['frequency'])) {
            $res = ZfsScheduledSnapshots::exec("zfs set com.sun:auto-snapshot:frequency={$config['frequency']} $name");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set frequency';
            }
        }

        if (isset($config['keep'])) {
            $res = ZfsScheduledSnapshots::exec("zfs set com.sun:auto-snapshot:keep={$config['keep']} $name");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set keep';
            }
        }

        if (isset($config['time'])) {
            $res = ZfsScheduledSnapshots::exec("zfs set com.sun:auto-snapshot:time={$config['time']} $name");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set time';
            }
        }

        if (isset($config['day'])) {
            $res = ZfsScheduledSnapshots::exec("zfs set com.sun:auto-snapshot:day={$config['day']} $name");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set day';
            }
        }

        if (isset($config['readonly'])) {
            $val = $config['readonly'] ? 'true' : 'false';
            $res = ZfsScheduledSnapshots::exec("zfs set com.sun:auto-snapshot:readonly=$val $name");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set readonly';
            }
        }

        if (isset($config['retain_days'])) {
            $res = ZfsScheduledSnapshots::exec("zfs set com.sun:auto-snapshot:retain-days={$config['retain_days']} $name");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set retain_days';
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'error' => implode(', ', $errors),
            ];
        }

        ZfsScheduledSnapshots::log("Updated configuration for dataset: $name");

        return [
            'success' => true,
            'dataset' => self::getManagedDataset($name),
        ];
    }

    /**
     * 获取概览统计
     */
    public static function getOverviewStats() {
        $datasets = self::getManagedDatasets();
        
        $enabledCount = 0;
        $totalSnapshots = 0;
        $totalReadonly = 0;
        $lastSnapshotTime = null;
        $lastSnapshotDataset = null;

        foreach ($datasets as $ds) {
            if ($ds['enabled']) {
                $enabledCount++;
            }
            $totalSnapshots += $ds['snapshot_count'];

            if ($ds['latest_snapshot_at']) {
                if ($lastSnapshotTime === null || $ds['latest_snapshot_at'] > $lastSnapshotTime) {
                    $lastSnapshotTime = $ds['latest_snapshot_at'];
                    $lastSnapshotDataset = $ds['name'];
                }
            }
        }

        // 统计全局 readonly 快照数量
        foreach ($datasets as $ds) {
            $full = self::getManagedDataset($ds['name']);
            if ($full) {
                $totalReadonly += $full['readonly_snapshot_count'];
            }
        }

        return [
            'dataset_count' => count($datasets),
            'enabled_count' => $enabledCount,
            'snapshot_count' => $totalSnapshots,
            'readonly_snapshot_count' => $totalReadonly,
            'last_snapshot_at' => $lastSnapshotTime,
            'last_snapshot_dataset' => $lastSnapshotDataset,
        ];
    }
}
