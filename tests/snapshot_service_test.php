<?php

require_once dirname(__DIR__) . '/zfs.scheduled.snapshots/include/services/SnapshotService.php';

zss_test('managed autosnap snapshots are valid for snapshot actions', function() {
    $error = SnapshotService::validateOperableSnapshotName(
        'tank/data@autosnap_2026-06-27_12:00:00',
        ['tank/data']
    );

    zss_assert_true($error === null, 'Expected managed autosnap snapshot to be valid');
});

zss_test('managed manual snapshots are valid for snapshot actions', function() {
    $error = SnapshotService::validateOperableSnapshotName(
        'tank/data@manual_2026-06-27_12:00:00',
        ['tank/data']
    );

    zss_assert_true($error === null, 'Expected managed manual snapshot to be valid');
});

zss_test('external snapshots are rejected for snapshot actions', function() {
    $error = SnapshotService::validateOperableSnapshotName(
        'tank/data@before-upgrade',
        ['tank/data']
    );

    zss_assert_true($error !== null, 'Expected external snapshot to be rejected');
});

zss_test('snapshots outside known datasets are rejected', function() {
    $error = SnapshotService::validateOperableSnapshotName(
        'tank/other@autosnap_2026-06-27_12:00:00',
        ['tank/data']
    );

    zss_assert_true($error !== null, 'Expected unknown dataset snapshot to be rejected');
});

