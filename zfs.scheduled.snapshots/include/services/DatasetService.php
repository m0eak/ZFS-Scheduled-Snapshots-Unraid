<?php

require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/../SnapshotNaming.php';
require_once __DIR__ . '/../validation.php';

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
        $result = self::collectManagedDatasetNames();
        return $result['success'] ? $result['names'] : [];
    }

    private static function collectManagedDatasetNames() {
        $names = [];
        $result = ZfsScheduledSnapshots::exec("zfs list -H -o name -t filesystem,volume");
        if (($result['return_var'] ?? 0) !== 0) {
            return [
                'success' => false,
                'error' => !empty($result['output']) ? implode("\n", $result['output']) : 'Failed to list managed datasets',
                'code' => 'DATASET_CATALOG_FAILED',
            ];
        }
        foreach (($result['output'] ?? []) as $line) {
            $name = trim($line);
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return ['success' => true, 'names' => $names];
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

    public static function getRootFilesystemDatasetNames() {
        $names = [];

        foreach (self::getFilesystemDatasetNames() as $name) {
            if (strpos($name, '/') === false) {
                $names[] = $name;
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

    private static function getDatasetZfsDetails($name) {
        $details = [];
        $properties = [
            'used',
            'available',
            'referenced',
            'encryption',
            'keystatus',
            'mountpoint',
            'compression',
            'compressratio',
            'usedbysnapshots',
            'quota',
            'recordsize',
            'atime',
            'xattr',
            'primarycache',
            'readonly',
            'casesensitivity',
            'sync',
            'creation',
            'origin',
            'type',
            'volblocksize',
        ];

        $datasetArg = self::quoteDatasetName($name);
        $propertyList = implode(',', $properties);
        $result = ZfsScheduledSnapshots::exec("zfs get -H -p -o property,value $propertyList $datasetArg");

        foreach ($properties as $property) {
            $details[$property] = null;
        }

        if (!empty($result['output'])) {
            foreach ($result['output'] as $line) {
                $parts = explode("\t", $line, 2);
                if (count($parts) < 2) {
                    continue;
                }

                $property = $parts[0];
                $value = trim($parts[1]);
                if (array_key_exists($property, $details)) {
                    $details[$property] = $value === '-' ? null : $value;
                }
            }
        }

        return $details;
    }

    /**
     * 获取单个数据集的完整配置
     */
    public static function getManagedDataset($name) {
        $allowedNames = self::getManagedDatasetNames();
        if (!in_array($name, $allowedNames)) {
            return null;
        }

        return self::hydrateManagedDataset($name);
    }

    /**
     * Hydrate a dataset after the caller has established that it is managed.
     * Collection callers use this to avoid re-enumerating membership per row.
     */
    private static function hydrateManagedDataset($name) {
        $config = [
            'name' => $name,
            'type' => self::getDatasetType($name),
            'is_root' => strpos($name, '/') === false,
            'details' => [],
            'enabled' => false,
            'frequency' => 'daily',
            'keep' => 31,
            'time' => '00:00',
            'day' => 1,
            'readonly' => false,
            'retain_days' => 0,
            'snapshot_count' => 0,
            'held_snapshot_count' => 0,
            'snapshot_used_bytes' => null,
            'snapshot_space' => [
                'status' => 'unknown',
                'total_bytes' => null,
                'exclusive_bytes' => [
                    'autosnap' => null,
                    'plugin_manual' => null,
                    'external' => null,
                ],
                'shared_bytes' => null,
            ],
            'latest_snapshot_at' => null,
        ];

        $config['details'] = self::getDatasetZfsDetails($name);
        if (!empty($config['details']['type'])) {
            $config['type'] = $config['details']['type'];
        }

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
        $config['held_snapshot_count'] = $stats['held'];
        $config['snapshot_space'] = self::getDatasetSnapshotSpace($name);
        $config['snapshot_used_bytes'] = $config['snapshot_space']['total_bytes'];

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
            'held' => 0,
        ];

        $datasetArg = self::quoteDatasetName($name);

        // List all snapshots so the Protected metric covers plugin and external holds.
        $result = ZfsScheduledSnapshots::exec("zfs list -t snapshot -H -o name,userrefs -d 1 $datasetArg");
        if (!empty($result['output'])) {
            foreach ($result['output'] as $line) {
                $parts = explode("\t", $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $stats['total']++;
                // Count protected snapshots, not the number of holds per snapshot.
                if ((int)$parts[1] > 0) {
                    $stats['held']++;
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

        $result = ZfsScheduledSnapshots::exec("zfs get -H -p -o value usedbysnapshots $datasetArg");
        if (($result['return_var'] ?? 1) !== 0 || count($result['output'] ?? []) !== 1) {
            return null;
        }

        $value = $result['output'][0];
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        return preg_match('/^[0-9]+$/D', $value) === 1 && $parsed !== false ? $parsed : null;
    }

    private static function getDatasetSnapshotSpace($name) {
        $totalBytes = self::getDatasetSnapshotUsedBytes($name);
        $unknown = [
            'status' => 'unknown',
            'total_bytes' => $totalBytes,
            'exclusive_bytes' => [
                'autosnap' => null,
                'plugin_manual' => null,
                'external' => null,
            ],
            'shared_bytes' => null,
        ];
        $datasetArg = self::quoteDatasetName($name);
        $result = ZfsScheduledSnapshots::exec("zfs list -t snapshot -H -p -o name,used -d 1 $datasetArg");
        if (($result['return_var'] ?? 1) !== 0) {
            return $unknown;
        }

        $exclusive = [
            'autosnap' => 0,
            'plugin_manual' => 0,
            'external' => 0,
        ];
        $seen = [];
        $exclusiveTotal = 0;
        foreach ($result['output'] ?? [] as $line) {
            if (!preg_match('/^([^\s]+)\t([0-9]+)$/D', $line, $matches)) {
                return $unknown;
            }
            $snapshotName = $matches[1];
            $usedBytes = filter_var($matches[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $parsedName = SnapshotNaming::parse($snapshotName);
            if ($usedBytes === false || $parsedName === null || $parsedName['dataset'] !== $name || isset($seen[$snapshotName])) {
                return $unknown;
            }
            $seen[$snapshotName] = true;

            $classification = SnapshotNaming::classifyShortName($parsedName['short_name']);
            $origin = $classification['origin'];
            if ($usedBytes > PHP_INT_MAX - $exclusiveTotal) {
                return $unknown;
            }
            $exclusive[$origin] += $usedBytes;
            $exclusiveTotal += $usedBytes;
        }

        if ($totalBytes === null) {
            $unknown['exclusive_bytes'] = $exclusive;
            return $unknown;
        }

        if ($exclusiveTotal > $totalBytes) {
            return [
                'status' => 'inconsistent',
                'total_bytes' => $totalBytes,
                'exclusive_bytes' => $exclusive,
                'shared_bytes' => null,
            ];
        }

        return [
            'status' => 'ok',
            'total_bytes' => $totalBytes,
            'exclusive_bytes' => $exclusive,
            'shared_bytes' => $totalBytes - $exclusiveTotal,
        ];
    }

    /**
     * 获取所有数据集的列表（精简信息）
     */
    public static function getManagedDatasets() {
        $catalog = self::collectManagedDatasetNames();
        if (!$catalog['success']) {
            return $catalog;
        }
        $names = $catalog['names'];
        $datasets = [];

        foreach ($names as $name) {
            $ds = self::hydrateManagedDataset($name);
            if ($ds) {
                // 只返回列表页需要的字段
                $datasets[] = [
                    'name' => $ds['name'],
                    'type' => $ds['type'],
                    'is_root' => $ds['is_root'],
                    'details' => $ds['details'],
                    'enabled' => $ds['enabled'],
                    'frequency' => $ds['frequency'],
                    'keep' => $ds['keep'],
                    'time' => $ds['time'],
                    'day' => $ds['day'],
                    'retain_days' => $ds['retain_days'],
                    'readonly' => $ds['readonly'],
                    'snapshot_count' => $ds['snapshot_count'],
                    'held_snapshot_count' => $ds['held_snapshot_count'],
                    'snapshot_used_bytes' => $ds['snapshot_used_bytes'],
                    'snapshot_space' => $ds['snapshot_space'],
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
        $properties = [
            'enabled' => ['com.sun:auto-snapshot', static function($value) { return $value ? 'yes' : 'no'; }],
            'frequency' => ['com.sun:auto-snapshot:frequency', static function($value) { return (string)$value; }],
            'keep' => ['com.sun:auto-snapshot:keep', static function($value) { return (string)$value; }],
            'time' => ['com.sun:auto-snapshot:time', static function($value) { return (string)$value; }],
            'day' => ['com.sun:auto-snapshot:day', static function($value) { return (string)$value; }],
            'readonly' => ['com.sun:auto-snapshot:readonly', static function($value) { return $value ? 'yes' : 'no'; }],
            'retain_days' => ['com.sun:auto-snapshot:retain-days', static function($value) { return (string)$value; }],
        ];
        if (!is_array($config) || $config === []) {
            return ['success' => false, 'error' => 'Invalid dataset configuration'];
        }
        foreach ($config as $key => $value) {
            if (!isset($properties[$key]) || (!is_scalar($value) && $value !== null)) {
                return ['success' => false, 'error' => 'Invalid dataset configuration'];
            }
            if (in_array($key, ['keep', 'day', 'retain_days'], true)
                && !(is_int($value) || (is_string($value) && preg_match('/^-?\d+$/D', trim($value))))) {
                return ['success' => false, 'error' => 'Invalid dataset configuration'];
            } elseif (in_array($key, ['keep', 'day', 'retain_days'], true)) {
                $config[$key] = (int)$value;
            } elseif ($key === 'frequency' && !zss_is_valid_frequency($value)) {
                return ['success' => false, 'error' => 'Invalid dataset configuration'];
            }
        }

        try {
            $operationLock = OperationLock::acquire(OperationLock::configuredPath());
        } catch (Throwable $error) {
            return [
                'success' => false, 'error' => 'Unable to acquire configuration lock',
                'code' => 'OPERATION_LOCK_ERROR', 'retryable' => false,
                'fields' => [], 'changed_fields' => [],
                'rollback' => ['status' => 'not_started', 'recovered_fields' => [], 'failed_fields' => []],
            ];
        }
        if ($operationLock === null) {
            return [
                'success' => false, 'error' => 'Another snapshot operation is active; retry the configuration update',
                'code' => 'OPERATION_BUSY', 'retryable' => true,
                'fields' => [], 'changed_fields' => [],
                'rollback' => ['status' => 'not_started', 'recovered_fields' => [], 'failed_fields' => []],
            ];
        }

        $allowedNames = self::getManagedDatasetNames();
        if (!in_array($name, $allowedNames, true)) {
            $operationLock->release();
            return ['success' => false, 'error' => 'Dataset not found'];
        }

        $datasetArg = escapeshellarg($name);
        $original = [];
        foreach ($properties as $key => $_value) {
            $property = $properties[$key][0];
            $propertyArg = escapeshellarg($property);
            $read = ZfsScheduledSnapshots::exec("zfs get -H -o value,source $propertyArg $datasetArg");
            $parts = $read['return_var'] === 0 && !empty($read['output'])
                ? preg_split('/\s+/', trim($read['output'][0]), 2)
                : [];
            if (count($parts) !== 2 || $parts[1] === '') {
                return [
                    'success' => false, 'error' => "Unable to capture original $key property and source",
                    'code' => 'CONFIG_CAPTURE_FAILED', 'retryable' => false,
                    'fields' => [$key => 'Unable to read current value and source'],
                    'changed_fields' => [], 'actual' => self::getManagedDataset($name),
                    'rollback' => ['status' => 'not_needed', 'recovered_fields' => [], 'failed_fields' => []],
                ];
            }
            $original[$key] = ['value' => $parts[0], 'source' => $parts[1]];
        }

        $defaults = [
            'enabled' => false, 'frequency' => 'daily', 'keep' => 31, 'time' => '00:00',
            'day' => 1, 'readonly' => false, 'retain_days' => 0,
        ];
        $proposed = ['name' => $name];
        foreach ($properties as $key => $_definition) {
            $raw = $original[$key]['value'];
            if ($raw === '-') {
                $proposed[$key] = $defaults[$key];
            } elseif (in_array($key, ['enabled', 'readonly'], true)) {
                $normalized = strtolower(trim($raw));
                if (!in_array($normalized, ['yes', 'no', 'true', 'false', '1', '0'], true)) {
                    $operationLock->release();
                    return ['success' => false, 'error' => "Unable to capture original $key property and source", 'code' => 'CONFIG_CAPTURE_FAILED', 'retryable' => false, 'fields' => [$key => 'Malformed current value'], 'changed_fields' => [], 'rollback' => ['status' => 'not_needed', 'recovered_fields' => [], 'failed_fields' => []]];
                }
                $proposed[$key] = in_array($normalized, ['yes', 'true', '1'], true);
            } elseif (in_array($key, ['keep', 'day', 'retain_days'], true)) {
                if (!preg_match('/^-?\d+$/D', $raw)) {
                    $operationLock->release();
                    return ['success' => false, 'error' => "Unable to capture original $key property and source", 'code' => 'CONFIG_CAPTURE_FAILED', 'retryable' => false, 'fields' => [$key => 'Malformed current value'], 'changed_fields' => [], 'rollback' => ['status' => 'not_needed', 'recovered_fields' => [], 'failed_fields' => []]];
                }
                $proposed[$key] = (int)$raw;
            } else {
                $proposed[$key] = $raw;
            }
        }
        foreach ($config as $key => $value) {
            if (in_array($key, ['enabled', 'readonly'], true)) {
                $validBoolean = is_bool($value)
                    || (is_int($value) && ($value === 0 || $value === 1))
                    || (is_string($value) && in_array(strtolower(trim($value)), ['true', 'false', '1', '0'], true));
                if (!$validBoolean) {
                    $operationLock->release();
                    return ['success' => false, 'error' => 'Invalid dataset configuration', 'code' => 'VALIDATION_FAILED', 'retryable' => false, 'fields' => [$key => 'Invalid boolean value'], 'changed_fields' => [], 'rollback' => ['status' => 'not_needed', 'recovered_fields' => [], 'failed_fields' => []]];
                }
                $value = zss_normalize_bool($value);
            }
            $proposed[$key] = $value;
        }
        $validationErrors = zss_validate_dataset_payload($proposed, $allowedNames);
        if (!empty($validationErrors)) {
            $operationLock->release();
            return ['success' => false, 'error' => 'Invalid dataset configuration', 'code' => 'VALIDATION_FAILED', 'retryable' => false, 'fields' => $validationErrors, 'changed_fields' => [], 'rollback' => ['status' => 'not_needed', 'recovered_fields' => [], 'failed_fields' => []]];
        }

        $changed = [];
        $attempted = [];
        $failedKey = null;
        $writeKeys = array_keys($config);
        usort($writeKeys, static function($left, $right) {
            return ($left === 'enabled' ? 1 : 0) <=> ($right === 'enabled' ? 1 : 0);
        });
        foreach ($writeKeys as $key) {
            $value = $config[$key];
            $property = $properties[$key][0];
            $propertyArg = escapeshellarg($property);
            $assignment = escapeshellarg($property . '=' . $properties[$key][1]($value));
            $attempted[] = $key;
            $write = ZfsScheduledSnapshots::exec("zfs set $assignment $datasetArg");
            if ($write['return_var'] !== 0) {
                $failedKey = $key;
                break;
            }
            $changed[] = $key;
            $verify = ZfsScheduledSnapshots::exec("zfs get -H -o value $propertyArg $datasetArg");
            if (
                $verify['return_var'] !== 0
                || trim(implode("\n", $verify['output'])) !== $properties[$key][1]($value)
            ) {
                $failedKey = $key;
                break;
            }
        }

        if ($failedKey !== null) {
            $recovered = [];
            $recoveryFailed = [];
            foreach (array_reverse($attempted) as $key) {
                $property = escapeshellarg($properties[$key][0]);
                $prior = $original[$key];
                if ($prior['source'] === 'local') {
                    $restore = ZfsScheduledSnapshots::exec(
                        'zfs set ' . escapeshellarg($properties[$key][0] . '=' . $prior['value']) . " $datasetArg"
                    );
                } elseif ($prior['source'] === 'received') {
                    $restore = ZfsScheduledSnapshots::exec("zfs inherit -S $property $datasetArg");
                } else {
                    $restore = ZfsScheduledSnapshots::exec("zfs inherit $property $datasetArg");
                }
                if ($restore['return_var'] !== 0) {
                    $recoveryFailed[$key] = 'Recovery command failed';
                    continue;
                }
                $verify = ZfsScheduledSnapshots::exec("zfs get -H -o value,source $property $datasetArg");
                $actualParts = $verify['return_var'] === 0 && !empty($verify['output'])
                    ? preg_split('/\s+/', trim($verify['output'][0]), 2)
                    : [];
                if (count($actualParts) !== 2 || $actualParts[0] !== $prior['value'] || $actualParts[1] !== $prior['source']) {
                    $recoveryFailed[$key] = 'Recovered value or source did not match the original';
                } else {
                    $recovered[] = $key;
                }
            }
            $rollback = empty($recoveryFailed) ? 'complete' : 'partial';
            return [
                'success' => false,
                'error' => "Failed to update $failedKey; " . ($rollback === 'complete' ? 'earlier changes were restored' : 'recovery was incomplete'),
                'code' => $rollback === 'complete' ? 'CONFIG_UPDATE_ROLLED_BACK' : 'CONFIG_RECOVERY_FAILED',
                'retryable' => false, 'fields' => [$failedKey => 'Unable to apply requested value'],
                'changed_fields' => $changed, 'actual' => self::getManagedDataset($name),
                'rollback' => ['status' => $rollback, 'recovered_fields' => $recovered, 'failed_fields' => $recoveryFailed],
            ];
        }

        ZfsScheduledSnapshots::log("Updated configuration for dataset: $name");
        return ['success' => true, 'dataset' => self::getManagedDataset($name)];
    }

    /**
     * 获取概览统计
     */
    public static function getOverviewStats($datasets = null) {
        if ($datasets === null) {
            $datasets = self::getManagedDatasets();
        }
        if (!is_array($datasets) || (isset($datasets['success']) && $datasets['success'] === false)) {
            return is_array($datasets) ? $datasets : [
                'success' => false,
                'error' => 'Failed to collect managed datasets',
                'code' => 'DATASET_CATALOG_FAILED',
            ];
        }
        
        $enabledCount = 0;
        $totalSnapshots = 0;
        $totalHeld = 0;
        $totalSnapshotUsedBytes = 0;
        $unknownSnapshotSpaceDatasets = 0;
        $lastSnapshotTime = null;
        $lastSnapshotDataset = null;
        $warnings = [];

        foreach ($datasets as $ds) {
            if ($ds['enabled']) {
                $enabledCount++;
            }
            $totalSnapshots += $ds['snapshot_count'];
            $totalHeld += $ds['held_snapshot_count'] ?? 0;
            $datasetTotalBytes = isset($ds['snapshot_space']) && is_array($ds['snapshot_space'])
                ? ($ds['snapshot_space']['total_bytes'] ?? null)
                : ($ds['snapshot_used_bytes'] ?? null);
            if (is_int($datasetTotalBytes) && $datasetTotalBytes >= 0) {
                $totalSnapshotUsedBytes += $datasetTotalBytes;
            } else {
                $unknownSnapshotSpaceDatasets++;
            }

            if ($ds['latest_snapshot_at']) {
                if ($lastSnapshotTime === null || $ds['latest_snapshot_at'] > $lastSnapshotTime) {
                    $lastSnapshotTime = $ds['latest_snapshot_at'];
                    $lastSnapshotDataset = $ds['name'];
                }
            }
            if (($ds['enabled'] ?? false) && ($ds['latest_snapshot_at'] ?? null) === null) {
                $warnings[] = 'Enabled dataset without managed snapshot: ' . $ds['name'];
            }
        }

        return [
            'dataset_count' => count($datasets),
            'enabled_count' => $enabledCount,
            'snapshot_count' => $totalSnapshots,
            'snapshot_used_bytes' => $unknownSnapshotSpaceDatasets === 0 ? $totalSnapshotUsedBytes : null,
            'snapshot_space' => [
                'status' => $unknownSnapshotSpaceDatasets === 0 ? 'ok' : 'partial',
                'known_bytes' => $totalSnapshotUsedBytes,
                'unknown_datasets' => $unknownSnapshotSpaceDatasets,
            ],
            'readonly_snapshot_count' => $totalHeld,
            'last_snapshot_at' => $lastSnapshotTime,
            'last_snapshot_dataset' => $lastSnapshotDataset,
            'last_runner_at' => null,
            'last_runner_status' => 'unknown',
            'warning_count' => count($warnings),
            'warnings' => $warnings,
        ];
    }
}
