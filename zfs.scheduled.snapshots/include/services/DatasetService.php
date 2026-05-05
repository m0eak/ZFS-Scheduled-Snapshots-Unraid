<?php

require_once __DIR__ . '/../common.php';

class DatasetService {

    private static function quoteDatasetName($name) {
        return escapeshellarg($name);
    }

    private static function quotePropertyAssignment($property, $value) {
        return escapeshellarg($property . '=' . $value);
    }

    private static function quoteCreateOption($property, $value) {
        return '-o ' . escapeshellarg($property . '=' . $value);
    }

    public static function createDataset($name, $options = [], $allowIntermediateParents = false) {
        $allowedNames = self::getManagedDatasetNames();
        if (function_exists('zss_validate_new_dataset_name')) {
            $nameError = zss_validate_new_dataset_name($name, $allowedNames, !$allowIntermediateParents);
            if ($nameError !== null) {
                return [
                    'success' => false,
                    'error' => $nameError,
                ];
            }
        }

        $createOptions = self::buildCreateOptions($options);
        if (!$createOptions['success']) {
            return $createOptions;
        }

        $optionArgs = '';
        foreach ($createOptions['options'] as $property => $value) {
            $optionArgs .= ' ' . self::quoteCreateOption($property, $value);
        }

        $datasetArg = self::quoteDatasetName($name);
        $result = ZfsScheduledSnapshots::exec("zfs create -p$optionArgs $datasetArg");

        if ($result['return_var'] !== 0) {
            return [
                'success' => false,
                'error' => !empty($result['output']) ? implode("\n", $result['output']) : 'Failed to create dataset',
            ];
        }

        $mountpoint = self::getDatasetPropertyValue($name, 'mountpoint');
        if ($mountpoint !== null && $mountpoint !== '-' && is_dir($mountpoint)) {
            @chown($mountpoint, 'nobody');
            @chgrp($mountpoint, 'users');
        }

        ZfsScheduledSnapshots::log("Created dataset: $name");

        return [
            'success' => true,
            'dataset' => self::getManagedDataset($name),
        ];
    }

    private static function buildCreateOptions($payload) {
        $options = [];
        $allowed = [
            'atime' => ['on', 'off'],
            'casesensitivity' => ['sensitive', 'insensitive', 'mixed'],
            'compression' => ['off', 'lz4', 'gzip', 'zstd'],
        ];

        $mount = $payload['mount'] ?? 'yes';
        if (!in_array($mount, ['yes', 'no'], true)) {
            return ['success' => false, 'error' => 'Invalid mount option'];
        }

        if ($mount === 'no') {
            $options['mountpoint'] = 'none';
        } else {
            $mountpoint = trim((string) ($payload['mountpoint'] ?? ''));
            if ($mountpoint !== '') {
                if ($mountpoint[0] !== '/' || preg_match('/[\x00-\x1F]/', $mountpoint) === 1) {
                    return ['success' => false, 'error' => 'Invalid mountpoint'];
                }
                $options['mountpoint'] = $mountpoint;
            }
        }

        foreach ($allowed as $property => $values) {
            $value = $payload[$property] ?? 'inherit';
            if ($value === 'inherit' || $value === '') {
                continue;
            }
            if (!in_array($value, $values, true)) {
                return ['success' => false, 'error' => "Invalid $property option"];
            }
            $options[$property] = $value;
        }

        $quota = trim((string) ($payload['quota'] ?? ''));
        if ($quota !== '' && $quota !== '0') {
            if (preg_match('/^[1-9][0-9]{0,8}$/', $quota) !== 1) {
                return ['success' => false, 'error' => 'Invalid quota'];
            }

            $unit = $payload['quota_unit'] ?? 'M';
            if (!in_array($unit, ['M', 'G', 'T'], true)) {
                return ['success' => false, 'error' => 'Invalid quota unit'];
            }

            $options['quota'] = $quota . $unit;
        }

        return [
            'success' => true,
            'options' => $options,
        ];
    }

    private static function getDatasetPropertyValue($name, $property) {
        $datasetArg = self::quoteDatasetName($name);
        $propertyArg = escapeshellarg($property);
        $result = ZfsScheduledSnapshots::exec("zfs get -H -o value $propertyArg $datasetArg");

        if (!empty($result['output'][0])) {
            return trim($result['output'][0]);
        }

        return null;
    }

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

    public static function getFilesystemDatasetNames() {
        $names = [];
        $result = ZfsScheduledSnapshots::exec("zfs list -H -o name -t filesystem");
        if (!empty($result['output'])) {
            foreach ($result['output'] as $line) {
                $names[] = trim($line);
            }
        }
        return $names;
    }

    private static function getDatasetType($name) {
        $datasetArg = self::quoteDatasetName($name);
        $result = ZfsScheduledSnapshots::exec("zfs list -H -o type $datasetArg");

        if (!empty($result['output'][0])) {
            return trim($result['output'][0]);
        }

        return null;
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
            'type' => self::getDatasetType($name),
            'enabled' => false,
            'frequency' => 'daily',
            'keep' => 31,
            'time' => '00:00',
            'day' => 1,
            'readonly' => false,
            'retain_days' => 0,
            'snapshot_count' => 0,
            'readonly_snapshot_count' => 0,
            'snapshot_used_bytes' => null,
            'latest_snapshot_at' => null,
        ];

        // 获取 ZFS 属性
        $datasetArg = self::quoteDatasetName($name);
        $props = ZfsScheduledSnapshots::exec("zfs get -H -o property,value com.sun:auto-snapshot,com.sun:auto-snapshot:frequency,com.sun:auto-snapshot:keep,com.sun:auto-snapshot:time,com.sun:auto-snapshot:day,com.sun:auto-snapshot:readonly,com.sun:auto-snapshot:retain-days $datasetArg");
        
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
        $config['snapshot_used_bytes'] = self::getDatasetSnapshotUsedBytes($name);

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

        $datasetArg = self::quoteDatasetName($name);

        // 列出所有 autosnap 快照
        $result = ZfsScheduledSnapshots::exec("zfs list -t snapshot -H -o name -d 1 $datasetArg | grep \"@autosnap_\"");
        if (!empty($result['output'])) {
            $stats['total'] = count($result['output']);

            // 统计带 hold 的快照
            foreach ($result['output'] as $snap) {
                $snapArg = escapeshellarg($snap);
                $tag = ZfsScheduledSnapshots::HOLD_TAG;
                $holdCheck = ZfsScheduledSnapshots::exec("zfs holds -H $snapArg 2>/dev/null | grep -c $tag");
                if (!empty($holdCheck['output']) && intval($holdCheck['output'][0]) > 0) {
                    $stats['readonly']++;
                }
            }
        }

        return $stats;
    }

    /**
     * 获取数据集快照占用空间（字节）
     */
    private static function getDatasetSnapshotUsedBytes($name) {
        $datasetArg = self::quoteDatasetName($name);

        $result = ZfsScheduledSnapshots::exec("zfs list -t snapshot -H -p -o used -d 1 $datasetArg | grep -E '^[0-9]+$'");
        if (!empty($result['output'])) {
            $total = 0;
            foreach ($result['output'] as $line) {
                $value = trim($line);
                if (is_numeric($value)) {
                    $total += (int) $value;
                }
            }

            return $total;
        }

        // Fallback: use dataset-level snapshot space if per-snapshot values are unavailable.
        $result = ZfsScheduledSnapshots::exec("zfs get -H -p -o value usedbysnapshots $datasetArg");
        if (!empty($result['output'][0])) {
            $value = trim($result['output'][0]);
            if ($value !== '-' && is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
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
                    'type' => $ds['type'],
                    'enabled' => $ds['enabled'],
                    'frequency' => $ds['frequency'],
                    'keep' => $ds['keep'],
                    'retain_days' => $ds['retain_days'],
                    'readonly' => $ds['readonly'],
                    'snapshot_count' => $ds['snapshot_count'],
                    'snapshot_used_bytes' => $ds['snapshot_used_bytes'],
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
        $datasetArg = self::quoteDatasetName($name);

        // 逐个设置属性
        if (isset($config['enabled'])) {
            $val = $config['enabled'] ? 'true' : 'false';
            $assignment = self::quotePropertyAssignment('com.sun:auto-snapshot', $val);
            $res = ZfsScheduledSnapshots::exec("zfs set $assignment $datasetArg");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set enabled';
            }
        }

        if (isset($config['frequency'])) {
            $assignment = self::quotePropertyAssignment('com.sun:auto-snapshot:frequency', $config['frequency']);
            $res = ZfsScheduledSnapshots::exec("zfs set $assignment $datasetArg");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set frequency';
            }
        }

        if (isset($config['keep'])) {
            $assignment = self::quotePropertyAssignment('com.sun:auto-snapshot:keep', (int) $config['keep']);
            $res = ZfsScheduledSnapshots::exec("zfs set $assignment $datasetArg");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set keep';
            }
        }

        if (isset($config['time'])) {
            $assignment = self::quotePropertyAssignment('com.sun:auto-snapshot:time', $config['time']);
            $res = ZfsScheduledSnapshots::exec("zfs set $assignment $datasetArg");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set time';
            }
        }

        if (isset($config['day'])) {
            $assignment = self::quotePropertyAssignment('com.sun:auto-snapshot:day', (int) $config['day']);
            $res = ZfsScheduledSnapshots::exec("zfs set $assignment $datasetArg");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set day';
            }
        }

        if (isset($config['readonly'])) {
            $val = $config['readonly'] ? 'true' : 'false';
            $assignment = self::quotePropertyAssignment('com.sun:auto-snapshot:readonly', $val);
            $res = ZfsScheduledSnapshots::exec("zfs set $assignment $datasetArg");
            if ($res['return_var'] !== 0) {
                $errors[] = 'Failed to set readonly';
            }
        }

        if (isset($config['retain_days'])) {
            $assignment = self::quotePropertyAssignment('com.sun:auto-snapshot:retain-days', (int) $config['retain_days']);
            $res = ZfsScheduledSnapshots::exec("zfs set $assignment $datasetArg");
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
        $totalSnapshotUsedBytes = 0;
        $lastSnapshotTime = null;
        $lastSnapshotDataset = null;

        foreach ($datasets as $ds) {
            if ($ds['enabled']) {
                $enabledCount++;
            }
            $totalSnapshots += $ds['snapshot_count'];
            if (isset($ds['snapshot_used_bytes']) && $ds['snapshot_used_bytes'] !== null) {
                $totalSnapshotUsedBytes += $ds['snapshot_used_bytes'];
            }

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
            'snapshot_used_bytes' => $totalSnapshotUsedBytes,
            'readonly_snapshot_count' => $totalReadonly,
            'last_snapshot_at' => $lastSnapshotTime,
            'last_snapshot_dataset' => $lastSnapshotDataset,
        ];
    }
}
