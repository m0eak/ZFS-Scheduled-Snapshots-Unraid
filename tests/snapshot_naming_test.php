<?php

$snapshotNamingPath = dirname(__DIR__) . '/zfs.scheduled.snapshots/include/SnapshotNaming.php';
if (file_exists($snapshotNamingPath)) {
    require_once $snapshotNamingPath;
}

zss_test('snapshot naming parses full snapshot names', function() {
    zss_assert_true(class_exists('SnapshotNaming'), 'Expected SnapshotNaming class to exist');

    $parsed = SnapshotNaming::parse('tank/data@autosnap_2026-07-05_12:00:00');

    zss_assert_true($parsed['dataset'] === 'tank/data', 'Expected dataset name');
    zss_assert_true($parsed['short_name'] === 'autosnap_2026-07-05_12:00:00', 'Expected short snapshot name');
});

zss_test('snapshot naming classifies plugin and external snapshots', function() {
    zss_assert_true(class_exists('SnapshotNaming'), 'Expected SnapshotNaming class to exist');

    $auto = SnapshotNaming::classifyShortName('autosnap_2026-07-05_12:00:00');
    $manual = SnapshotNaming::classifyShortName('manual_2026-07-05_12:00:00');
    $external = SnapshotNaming::classifyShortName('before-upgrade');

    zss_assert_true($auto['origin'] === 'autosnap' && $auto['managed'] === true, 'Expected autosnap to be managed');
    zss_assert_true($manual['origin'] === 'plugin_manual' && $manual['managed'] === true, 'Expected manual snapshot to be managed');
    zss_assert_true($external['origin'] === 'external' && $external['managed'] === false, 'Expected external snapshot to be external');
});

