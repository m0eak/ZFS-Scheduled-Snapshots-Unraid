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

zss_test('external snapshots are advertised as read-only in action state', function() {
    $state = SnapshotService::buildSnapshotActionState(false, []);

    zss_assert_true($state['operable'] === false, 'Expected external snapshot to be non-operable');
    zss_assert_true($state['destroyable'] === false, 'Expected external snapshot to be non-destroyable');
    zss_assert_true($state['actions'] === [
        'hold' => false,
        'release' => false,
        'delete' => false,
        'rollback' => false,
    ], 'Expected external snapshot actions to be hidden');
});

zss_test('managed snapshots expose actions that match hold state', function() {
    $normal = SnapshotService::buildSnapshotActionState(true, []);
    $held = SnapshotService::buildSnapshotActionState(true, ['autosnap']);

    zss_assert_true($normal['operable'] === true, 'Expected managed snapshot to be operable');
    zss_assert_true($normal['actions']['hold'] === true, 'Expected unheld managed snapshot to allow hold');
    zss_assert_true($normal['actions']['delete'] === true, 'Expected unheld managed snapshot to allow delete');
    zss_assert_true($normal['actions']['rollback'] === true, 'Expected managed snapshot to allow rollback');
    zss_assert_true($normal['actions']['release'] === false, 'Expected unheld managed snapshot not to show release');

    zss_assert_true($held['actions']['hold'] === false, 'Expected held managed snapshot not to allow duplicate hold');
    zss_assert_true($held['actions']['delete'] === false, 'Expected held managed snapshot not to allow delete before release');
    zss_assert_true($held['actions']['release'] === true, 'Expected held managed snapshot to allow release');
    zss_assert_true($held['actions']['rollback'] === true, 'Expected held managed snapshot to allow rollback');
});
