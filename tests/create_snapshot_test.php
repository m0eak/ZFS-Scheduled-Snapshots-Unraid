<?php

require_once dirname(__DIR__) . '/zfs.scheduled.snapshots/include/common.php';

class CreateSnapshotTestHarness extends ZfsScheduledSnapshots {
    public static $commands = [];
    public static $responses = [];
    public static $logs = [];

    public static function exec($command) {
        self::$commands[] = $command;
        return array_shift(self::$responses) ?? [
            'return_var' => 0,
            'output' => [],
        ];
    }

    public static function log($message, $level = 'INFO') {
        self::$logs[] = [$level, $message];
    }
}

zss_test('scheduled readonly snapshot uses the new automatic hold tag', function() {
    CreateSnapshotTestHarness::$commands = [];
    CreateSnapshotTestHarness::$logs = [];
    CreateSnapshotTestHarness::$responses = [
        ['return_var' => 0, 'output' => []],
        ['return_var' => 0, 'output' => []],
        ['return_var' => 0, 'output' => []],
        ['return_var' => 0, 'output' => []],
    ];

    $result = CreateSnapshotTestHarness::createSnapshot(
        'tank/data',
        ZfsScheduledSnapshots::AUTO_SNAPSHOT_PREFIX,
        true,
        ZfsScheduledSnapshots::AUTO_HOLD_TAG,
        true
    );

    zss_assert_true($result['success'] === true, 'Expected protected snapshot creation to succeed when hold succeeds');
    zss_assert_true($result['snapshot_created'] === true, 'Expected snapshot_created=true after snapshot command succeeds');
    zss_assert_true($result['protected'] === true, 'Expected protected=true after hold command succeeds');
    zss_assert_true($result['hold_tag'] === ZfsScheduledSnapshots::AUTO_HOLD_TAG, 'Expected scheduled readonly snapshot to use zss_auto');
    zss_assert_true(count(CreateSnapshotTestHarness::$commands) === 4, 'Expected pending marker, snapshot, hold, and marker clear commands');
    zss_assert_true(strpos(CreateSnapshotTestHarness::$commands[0], ZfsScheduledSnapshots::PENDING_AUTO_HOLD_PROPERTY) !== false, 'Expected pending recovery marker before creating readonly scheduled snapshot');
    zss_assert_true(strpos(CreateSnapshotTestHarness::$commands[1], 'zfs snapshot') === 0, 'Expected snapshot command after pending marker');
    zss_assert_true(strpos(CreateSnapshotTestHarness::$commands[2], "'" . ZfsScheduledSnapshots::AUTO_HOLD_TAG . "'") !== false, 'Expected zss_auto hold command');
    zss_assert_true(strpos(CreateSnapshotTestHarness::$commands[3], 'zfs inherit') === 0, 'Expected successful automatic hold to clear the pending marker');
});

zss_test('scheduled readonly snapshot hold failure leaves a pending recovery marker', function() {
    CreateSnapshotTestHarness::$commands = [];
    CreateSnapshotTestHarness::$logs = [];
    CreateSnapshotTestHarness::$responses = [
        ['return_var' => 0, 'output' => []],
        ['return_var' => 0, 'output' => []],
        ['return_var' => 1, 'output' => ['hold failed']],
    ];

    $result = CreateSnapshotTestHarness::createSnapshot(
        'tank/data',
        ZfsScheduledSnapshots::AUTO_SNAPSHOT_PREFIX,
        true,
        ZfsScheduledSnapshots::AUTO_HOLD_TAG,
        true
    );

    zss_assert_true($result['success'] === false, 'Expected hold failure not to be reported as complete success');
    zss_assert_true($result['snapshot_created'] === true, 'Expected partial failure to report that snapshot exists');
    zss_assert_true($result['protected'] === false, 'Expected partial failure to report missing protection');
    zss_assert_true($result['code'] === 'SNAPSHOT_HOLD_FAILED', 'Expected explicit hold failure code');
    zss_assert_true(count(CreateSnapshotTestHarness::$commands) === 3, 'Expected pending marker, snapshot, and failed hold commands');
});

zss_test('pending automatic hold recovery restores protection before maintenance', function() {
    CreateSnapshotTestHarness::$commands = [];
    CreateSnapshotTestHarness::$logs = [];
    CreateSnapshotTestHarness::$responses = [
        ['return_var' => 0, 'output' => ['tank/data@autosnap_pending']],
        ['return_var' => 0, 'output' => ['tank/data@autosnap_pending']],
        ['return_var' => 0, 'output' => []],
        ['return_var' => 0, 'output' => []],
    ];

    $result = CreateSnapshotTestHarness::recoverPendingAutoHold('tank/data');

    zss_assert_true($result['recovered'] === true, 'Expected pending automatic hold to be recovered');
    zss_assert_true(count(CreateSnapshotTestHarness::$commands) === 4, 'Expected marker lookup, snapshot existence check, hold, and marker clear commands');
    zss_assert_true(strpos(CreateSnapshotTestHarness::$commands[2], "'" . ZfsScheduledSnapshots::AUTO_HOLD_TAG . "'") !== false, 'Expected recovery to use zss_auto');
});

zss_test('manual readonly snapshot uses the permanent manual hold tag', function() {
    CreateSnapshotTestHarness::$commands = [];
    CreateSnapshotTestHarness::$logs = [];
    CreateSnapshotTestHarness::$responses = [
        ['return_var' => 0, 'output' => []],
        ['return_var' => 0, 'output' => []],
    ];

    $result = CreateSnapshotTestHarness::createSnapshot(
        'tank/data',
        ZfsScheduledSnapshots::MANUAL_SNAPSHOT_PREFIX,
        true,
        ZfsScheduledSnapshots::MANUAL_HOLD_TAG,
        false
    );

    zss_assert_true($result['success'] === true, 'Expected manual readonly snapshot creation to succeed');
    zss_assert_true($result['hold_tag'] === ZfsScheduledSnapshots::MANUAL_HOLD_TAG, 'Expected manual readonly snapshot to use zss_manual');
    zss_assert_true(count(CreateSnapshotTestHarness::$commands) === 2, 'Expected snapshot and manual hold commands without a pending marker');
});
