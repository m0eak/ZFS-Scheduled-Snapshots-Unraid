<?php

require_once dirname(__DIR__) . '/zfs.scheduled.snapshots/include/services/SnapshotService.php';

zss_test('automatic and manual hold tags are distinct', function() {
    zss_assert_true(ZfsScheduledSnapshots::AUTO_HOLD_TAG === 'zss_auto', 'Expected zss_auto to be the automatic hold tag');
    zss_assert_true(ZfsScheduledSnapshots::LEGACY_HOLD_TAG === 'autosnap', 'Expected autosnap to remain the legacy hold tag');
    zss_assert_true(ZfsScheduledSnapshots::MANUAL_HOLD_TAG === 'zss_manual', 'Expected zss_manual to be the permanent manual hold tag');
    zss_assert_true(ZfsScheduledSnapshots::AUTO_HOLD_TAG !== ZfsScheduledSnapshots::LEGACY_HOLD_TAG, 'Expected automatic and legacy hold tags to differ');
    zss_assert_true(ZfsScheduledSnapshots::AUTO_HOLD_TAG !== ZfsScheduledSnapshots::MANUAL_HOLD_TAG, 'Expected automatic and manual hold tags to differ');
});

zss_test('automatic or legacy hold does not block adding a permanent manual hold', function() {
    $automaticState = SnapshotService::buildSnapshotActionState(true, [ZfsScheduledSnapshots::AUTO_HOLD_TAG]);
    $legacyState = SnapshotService::buildSnapshotActionState(true, [ZfsScheduledSnapshots::LEGACY_HOLD_TAG]);
    $manualState = SnapshotService::buildSnapshotActionState(true, [ZfsScheduledSnapshots::AUTO_HOLD_TAG, ZfsScheduledSnapshots::MANUAL_HOLD_TAG]);

    zss_assert_true($automaticState['actions']['hold'] === true, 'Expected automatic hold to allow adding a permanent manual hold');
    zss_assert_true($legacyState['actions']['hold'] === true, 'Expected legacy hold to allow adding a permanent manual hold');
    zss_assert_true($manualState['actions']['hold'] === false, 'Expected existing permanent manual hold to prevent duplicate manual hold');
});

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

zss_test('external snapshots are valid for hold delete and release actions', function() {
    $error = SnapshotService::validateSnapshotName(
        'tank/data@before-upgrade',
        ['tank/data']
    );

    zss_assert_true($error === null, 'Expected external snapshot in a known dataset to be valid');
});

zss_test('external snapshots are rejected for rollback actions', function() {
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

zss_test('external snapshots allow hold delete and release but not rollback', function() {
    $state = SnapshotService::buildSnapshotActionState(false, []);
    $held = SnapshotService::buildSnapshotActionState(false, [ZfsScheduledSnapshots::AUTO_HOLD_TAG]);

    zss_assert_true($state['operable'] === true, 'Expected external snapshot to be operable for safe manual actions');
    zss_assert_true($state['destroyable'] === true, 'Expected unheld external snapshot to be destroyable');
    zss_assert_true($state['actions'] === [
        'hold' => true,
        'release' => false,
        'delete' => true,
        'rollback' => false,
    ], 'Expected unheld external snapshot to allow hold and delete only');
    zss_assert_true($held['destroyable'] === false, 'Expected held external snapshot to require release before delete');
    zss_assert_true($held['actions'] === [
        'hold' => true,
        'release' => true,
        'delete' => false,
        'rollback' => false,
    ], 'Expected auto-held external snapshot to allow adding permanent manual protection or releasing holds');
});

zss_test('managed snapshots expose actions that match hold state', function() {
    $normal = SnapshotService::buildSnapshotActionState(true, []);
    $held = SnapshotService::buildSnapshotActionState(true, [ZfsScheduledSnapshots::AUTO_HOLD_TAG]);

    zss_assert_true($normal['operable'] === true, 'Expected managed snapshot to be operable');
    zss_assert_true($normal['actions']['hold'] === true, 'Expected unheld managed snapshot to allow hold');
    zss_assert_true($normal['actions']['delete'] === true, 'Expected unheld managed snapshot to allow delete');
    zss_assert_true($normal['actions']['rollback'] === true, 'Expected managed snapshot to allow rollback');
    zss_assert_true($normal['actions']['release'] === false, 'Expected unheld managed snapshot not to show release');

    zss_assert_true($held['actions']['hold'] === true, 'Expected automatic hold not to block adding a permanent manual hold');
    zss_assert_true($held['actions']['delete'] === false, 'Expected held managed snapshot not to allow delete before release');
    zss_assert_true($held['actions']['release'] === true, 'Expected held managed snapshot to allow release');
    zss_assert_true($held['actions']['rollback'] === true, 'Expected held managed snapshot to allow rollback');
});
