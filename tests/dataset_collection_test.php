<?php

require_once dirname(__DIR__) . '/zfs.scheduled.snapshots/include/services/DatasetService.php';

function zss_collection_fixture($catalogFailure = false) {
    $directory = sys_get_temp_dir() . '/zss-collection-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $logPath = $directory . '/commands.log';
    $zfsPath = $directory . '/zfs';
    $script = <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$ZSS_COLLECTION_LOG"
case "$*" in
  *"-t filesystem,volume"*)
    if [ "$ZSS_COLLECTION_FAIL_CATALOG" = "1" ]; then
      echo "simulated catalog failure" >&2
      exit 7
    fi
    printf 'tank/a\ntank/b\n'
    ;;
  *"usedbysnapshots"*"tank/a"*)
    printf '1000\n'
    ;;
  *"usedbysnapshots"*"tank/b"*)
    printf '0\n'
    ;;
  *"-o name,used"*"tank/a"*)
    printf 'tank/a@autosnap_test\t100\n'
    ;;
  *"-o name,used"*"tank/b"*)
    ;;
  *"-o name,userrefs"*"tank/a"*)
    printf 'tank/a@auto-2026-11-13-00-00-00\t4\ntank/a@manual-2026-11-13-01-00-00\t0\n'
    ;;
  *"-o name,userrefs"*"tank/b"*)
    printf 'tank/b@auto-2026-11-13-00-00-00\t0\n'
    ;;
  *"com.sun:auto-snapshot:retain-days"*"tank/a"*)
    printf 'com.sun:auto-snapshot\ttrue\ncom.sun:auto-snapshot:frequency\tweekly\ncom.sun:auto-snapshot:keep\t9\ncom.sun:auto-snapshot:time\t03:45\ncom.sun:auto-snapshot:day\t5\ncom.sun:auto-snapshot:readonly\tfalse\ncom.sun:auto-snapshot:retain-days\t0\n'
    ;;
  *"-o type"*)
    printf 'filesystem\n'
    ;;
esac
exit 0
SH;
    file_put_contents($zfsPath, $script);
    chmod($zfsPath, 0700);
    return [$directory, $logPath];
}

function zss_collection_cleanup($directory) {
    foreach (glob($directory . '/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($directory);
}

function zss_collection_api($endpoint, $directory, $logPath, $failCatalog) {
    $environment = array_merge($_ENV, [
        'PATH' => $directory . ':' . getenv('PATH'),
        'ZSS_COLLECTION_LOG' => $logPath,
        'ZSS_COLLECTION_FAIL_CATALOG' => $failCatalog ? '1' : '0',
    ]);
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/zfs.scheduled.snapshots/api/' . $endpoint], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, dirname(__DIR__), $environment);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    return [$status, json_decode($output, true)];
}

zss_test('dataset collection enumerates membership once and counts protected snapshots from userrefs rows', function() {
    [$directory, $logPath] = zss_collection_fixture();
    $oldPath = getenv('PATH');
    putenv('PATH=' . $directory . ':' . $oldPath);
    putenv('ZSS_COLLECTION_LOG=' . $logPath);
    putenv('ZSS_COLLECTION_FAIL_CATALOG=0');
    try {
        $datasets = DatasetService::getManagedDatasets();
        $commands = file($logPath, FILE_IGNORE_NEW_LINES) ?: [];
        $membershipCalls = array_filter($commands, static function($command) {
            return strpos($command, '-t filesystem,volume') !== false;
        });
        $snapshotCalls = array_filter($commands, static function($command) {
            return strpos($command, '-o name,userrefs') !== false;
        });
        $holdCalls = array_filter($commands, static function($command) {
            return strpos($command, 'holds -H') !== false;
        });

        zss_assert_true(is_array($datasets) && count($datasets) === 2, 'Expected both managed datasets');
        zss_assert_true(count($membershipCalls) === 1, 'Expected exactly one managed-membership enumeration');
        zss_assert_true(count($snapshotCalls) === 2, 'Expected one batched snapshot/userrefs query per dataset');
        zss_assert_true(count($holdCalls) === 0, 'Expected no per-snapshot holds commands');
        zss_assert_true($datasets[0]['held_snapshot_count'] === 1, 'Expected two holds on one snapshot to count as one protected snapshot');
        zss_assert_true($datasets[0]['time'] === '03:45' && $datasets[0]['day'] === 5, 'Expected configured schedule values in list rows');
    } finally {
        putenv('PATH=' . $oldPath);
        putenv('ZSS_COLLECTION_LOG');
        putenv('ZSS_COLLECTION_FAIL_CATALOG');
        zss_collection_cleanup($directory);
    }
});

zss_test('dataset and overview APIs return structured catalog failures', function() {
    [$directory, $logPath] = zss_collection_fixture(true);
    try {
        foreach (['datasets.php', 'overview.php'] as $endpoint) {
            [$status, $payload] = zss_collection_api($endpoint, $directory, $logPath, true);
            zss_assert_true($status === 0, "Expected {$endpoint} to emit a JSON response");
            zss_assert_true(($payload['ok'] ?? null) === false, "Expected {$endpoint} to report catalog failure");
            zss_assert_true(($payload['error']['code'] ?? '') === 'DATASET_CATALOG_FAILED', "Expected {$endpoint} to preserve the catalog error code");
        }
    } finally {
        zss_collection_cleanup($directory);
    }
});

zss_test('successful dataset and overview APIs keep their response contracts and enumerate once', function() {
    [$directory, $logPath] = zss_collection_fixture();
    try {
        foreach (['datasets.php', 'overview.php'] as $endpoint) {
            file_put_contents($logPath, '');
            [$status, $payload] = zss_collection_api($endpoint, $directory, $logPath, false);
            zss_assert_true($status === 0 && ($payload['ok'] ?? null) === true, "Expected {$endpoint} to return success");
            $commands = file($logPath, FILE_IGNORE_NEW_LINES) ?: [];
            $membershipCalls = array_filter($commands, static function($command) {
                return strpos($command, '-t filesystem,volume') !== false;
            });
            $snapshotCalls = array_filter($commands, static function($command) {
                return strpos($command, '-o name,userrefs') !== false;
            });
            $spaceBreakdownCalls = array_filter($commands, static function($command) {
                return strpos($command, '-o name,used') !== false;
            });
            $holdCalls = array_filter($commands, static function($command) {
                return strpos($command, 'holds -H') !== false;
            });
            zss_assert_true(count($membershipCalls) === 1, "Expected {$endpoint} to enumerate the catalog once");
            zss_assert_true(count($snapshotCalls) === 2, "Expected {$endpoint} to issue one batched snapshot query per dataset");
            zss_assert_true(count($spaceBreakdownCalls) === 2, "Expected {$endpoint} to issue one batched space breakdown query per dataset");
            zss_assert_true(count($holdCalls) === 0, "Expected {$endpoint} to avoid per-snapshot hold commands");

            if ($endpoint === 'datasets.php') {
                zss_assert_true(is_array($payload['data'] ?? null) && array_is_list($payload['data']), 'Expected datasets.php data to remain an array');
                zss_assert_true(($payload['data'][0]['snapshot_space'] ?? null) === [
                    'status' => 'ok',
                    'total_bytes' => 1000,
                    'exclusive_bytes' => ['autosnap' => 100, 'plugin_manual' => 0, 'external' => 0],
                    'shared_bytes' => 900,
                ], 'Expected successful datasets API rows to expose complete source accounting');
                $overview = $payload['meta']['overview'] ?? [];
                zss_assert_true(($overview['snapshot_space'] ?? null) === [
                    'status' => 'ok',
                    'known_bytes' => 1000,
                    'unknown_datasets' => 0,
                ], 'Expected dataset API overview metadata to include the aggregate space contract');
                zss_assert_true(($overview['dataset_count'] ?? null) === 2, 'Expected dataset overview metadata');
                zss_assert_true(($overview['warning_count'] ?? null) === 1, 'Expected enabled-without-AUTO warning metadata');
                zss_assert_true(($overview['warnings'][0] ?? '') === 'Enabled dataset without managed snapshot: tank/a', 'Expected the existing managed AUTO warning semantics');
                zss_assert_true(($payload['data'][0]['time'] ?? '') === '03:45' && ($payload['data'][0]['day'] ?? null) === 5, 'Expected weekly time/day fields in dataset rows');
            } else {
                zss_assert_true(is_array($payload['data'] ?? null) && !array_is_list($payload['data']), 'Expected overview.php data to remain an aggregate object');
                zss_assert_true(($payload['data']['snapshot_space'] ?? null) === [
                    'status' => 'ok',
                    'known_bytes' => 1000,
                    'unknown_datasets' => 0,
                ], 'Expected overview API success data to include aggregate space status and known bytes');
                zss_assert_true(($payload['data']['dataset_count'] ?? null) === 2, 'Expected overview aggregate fields in data');
                zss_assert_true(($payload['data']['warning_count'] ?? null) === 1, 'Expected warning metadata in the overview aggregate');
                zss_assert_true(($payload['data']['warnings'][0] ?? '') === 'Enabled dataset without managed snapshot: tank/a', 'Expected the existing managed AUTO warning semantics');
            }
        }
    } finally {
        zss_collection_cleanup($directory);
    }
});
