<?php

require_once dirname(__DIR__) . '/zfs.scheduled.snapshots/include/services/DatasetService.php';

zss_test('snapshot space source accounting preserves unknown and inconsistent states', function () {
    $directory = sys_get_temp_dir() . '/zss-space-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $fakeZfs = $directory . '/zfs';
    file_put_contents($fakeZfs, <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$ZSS_SPACE_COMMAND_LOG"
case "$*" in
  *"usedbysnapshots"*)
    printf '%s\n' "$ZSS_SPACE_TOTAL"
    exit "${ZSS_SPACE_TOTAL_EXIT:-0}"
    ;;
  *"name,used"*)
    if [ -n "$ZSS_SPACE_ROWS" ]; then
      printf '%s\n' "$ZSS_SPACE_ROWS"
    fi
    exit "${ZSS_SPACE_ROWS_EXIT:-0}"
    ;;
  *)
    echo "unexpected fake zfs command: $*" >&2
    exit 90
    ;;
esac
SH
    );
    chmod($fakeZfs, 0700);
    $commandLog = $directory . '/commands.log';
    $previousPath = getenv('PATH') ?: '';
    putenv('PATH=' . $directory . ':' . $previousPath);
    putenv('ZSS_SPACE_COMMAND_LOG=' . $commandLog);

    $readSpace = new ReflectionMethod(DatasetService::class, 'getDatasetSnapshotSpace');
    $readSpace->setAccessible(true);
    $readSpaceFor = static function ($total, $rows, $totalExit = 0, $rowsExit = 0) use ($readSpace) {
        putenv('ZSS_SPACE_TOTAL=' . $total);
        putenv('ZSS_SPACE_ROWS=' . $rows);
        putenv('ZSS_SPACE_TOTAL_EXIT=' . $totalExit);
        putenv('ZSS_SPACE_ROWS_EXIT=' . $rowsExit);
        return $readSpace->invoke(null, 'tank/data');
    };

    try {
        $space = $readSpaceFor('1000', implode("\n", [
            "tank/data@autosnap_2026-11-13_00:00:00_hourly\t100",
            "tank/data@manual_2026-11-13_01:00:00\t200",
            "tank/data@external\t300",
        ]));
        zss_assert_true($space === [
            'status' => 'ok',
            'total_bytes' => 1000,
            'exclusive_bytes' => ['autosnap' => 100, 'plugin_manual' => 200, 'external' => 300],
            'shared_bytes' => 400,
        ], 'Expected the three naming origins and shared remainder');
        $commands = file($commandLog, FILE_IGNORE_NEW_LINES) ?: [];
        zss_assert_true(count($commands) === 2, 'Expected one total read and one batched name/used snapshot read');
        zss_assert_true(count(array_filter($commands, static function ($command) {
            return strpos($command, '-o name,used') !== false;
        })) === 1, 'Expected the snapshot breakdown to use one batch command');

        $zero = $readSpaceFor('0', '');
        zss_assert_true($zero === [
            'status' => 'ok',
            'total_bytes' => 0,
            'exclusive_bytes' => ['autosnap' => 0, 'plugin_manual' => 0, 'external' => 0],
            'shared_bytes' => 0,
        ], 'Expected valid empty measurements to remain known zero');

        $failedTotal = $readSpaceFor('1000', "tank/data@autosnap_test\t100", 7, 0);
        zss_assert_true($failedTotal['status'] === 'unknown' && $failedTotal['total_bytes'] === null, 'Expected failed total command to remain unknown');
        zss_assert_true($failedTotal['exclusive_bytes'] === ['autosnap' => 100, 'plugin_manual' => 0, 'external' => 0], 'Expected a valid source breakdown to survive an unknown total');
        zss_assert_true($failedTotal['shared_bytes'] === null, 'Expected shared remainder to remain unknown when total is unavailable');

        $malformedTotal = $readSpaceFor("1000\nextra", "tank/data@autosnap_test\t100");
        zss_assert_true($malformedTotal['status'] === 'unknown' && $malformedTotal['total_bytes'] === null, 'Expected malformed total output to remain unknown');

        $failedBreakdown = $readSpaceFor('1000', "tank/data@autosnap_test\t100", 0, 7);
        zss_assert_true($failedBreakdown['status'] === 'unknown' && $failedBreakdown['total_bytes'] === 1000, 'Expected known total to survive failed breakdown');
        zss_assert_true($failedBreakdown['exclusive_bytes'] === ['autosnap' => null, 'plugin_manual' => null, 'external' => null] && $failedBreakdown['shared_bytes'] === null, 'Expected unavailable breakdown to stay null');

        foreach ([
            "tank/data@autosnap_good\t100\nmalformed",
            "tank/data@autosnap_good\tnot-a-number",
            "tank/other@autosnap_good\t100",
            "tank/data@autosnap_good\t-1",
            "tank/data@autosnap_good\t100\nmalformed",
            "tank/data@autosnap_good\t100\ntank/data@autosnap_good\t25",
        ] as $malformedRows) {
            $malformed = $readSpaceFor('1000', $malformedRows);
            zss_assert_true($malformed['status'] === 'unknown' && $malformed['total_bytes'] === 1000, 'Expected malformed or partial rows to invalidate only the breakdown');
        }

        $inconsistent = $readSpaceFor('100', "tank/data@autosnap_one\t80\ntank/data@external\t50");
        zss_assert_true($inconsistent['status'] === 'inconsistent' && $inconsistent['total_bytes'] === 100, 'Expected exclusive sum above total to be inconsistent');
        zss_assert_true($inconsistent['shared_bytes'] === null, 'Expected inconsistent data to have no fabricated shared remainder');

        $datasetRow = static function ($name, $snapshotSpace, $snapshotUsedBytes) {
            return [
                'name' => $name,
                'enabled' => false,
                'snapshot_count' => 0,
                'held_snapshot_count' => 0,
                'snapshot_used_bytes' => $snapshotUsedBytes,
                'snapshot_space' => $snapshotSpace,
                'latest_snapshot_at' => null,
            ];
        };
        $knownAggregate = DatasetService::getOverviewStats([
            $datasetRow('tank/known', $space, 1000),
            $datasetRow('tank/breakdown-unknown', $failedBreakdown, 1000),
            $datasetRow('tank/inconsistent', $inconsistent, 100),
        ]);
        zss_assert_true(($knownAggregate['snapshot_space'] ?? null) === [
            'status' => 'ok',
            'known_bytes' => 2100,
            'unknown_datasets' => 0,
        ], 'Expected known totals to aggregate despite unknown or inconsistent breakdowns');
        zss_assert_true(($knownAggregate['snapshot_used_bytes'] ?? null) === 2100, 'Expected legacy aggregate to retain the complete known total');

        $partialAggregate = DatasetService::getOverviewStats([
            $datasetRow('tank/known', $space, 1000),
            $datasetRow('tank/breakdown-unknown', $failedBreakdown, 1000),
            $datasetRow('tank/inconsistent', $inconsistent, 100),
            $datasetRow('tank/total-unknown', $failedTotal, 999),
        ]);
        zss_assert_true(($partialAggregate['snapshot_space'] ?? null) === [
            'status' => 'partial',
            'known_bytes' => 2100,
            'unknown_datasets' => 1,
        ], 'Expected only unknown total measurements to make the aggregate partial');
        zss_assert_true(array_key_exists('snapshot_used_bytes', $partialAggregate) && $partialAggregate['snapshot_used_bytes'] === null, 'Expected legacy aggregate total to be null when any dataset total is unknown');
    } finally {
        putenv('PATH=' . $previousPath);
        putenv('ZSS_SPACE_TOTAL');
        putenv('ZSS_SPACE_ROWS');
        putenv('ZSS_SPACE_TOTAL_EXIT');
        putenv('ZSS_SPACE_ROWS_EXIT');
        putenv('ZSS_SPACE_COMMAND_LOG');
        @unlink($fakeZfs);
        @unlink($commandLog);
        @rmdir($directory);
    }
});
