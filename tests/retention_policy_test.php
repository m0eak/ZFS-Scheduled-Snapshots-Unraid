<?php

$retentionPolicyPath = dirname(__DIR__) . '/zfs.scheduled.snapshots/include/RetentionPolicy.php';
if (file_exists($retentionPolicyPath)) {
    require_once $retentionPolicyPath;
}

zss_test('retention policy selects oldest snapshots beyond keep count', function() {
    zss_assert_true(class_exists('RetentionPolicy'), 'Expected RetentionPolicy class to exist');

    $candidates = RetentionPolicy::selectPruneCandidates([
        ['name' => 'tank/data@autosnap_newest', 'creation' => 300],
        ['name' => 'tank/data@autosnap_middle', 'creation' => 200],
        ['name' => 'tank/data@autosnap_oldest', 'creation' => 100],
    ], 2);

    zss_assert_true($candidates === ['tank/data@autosnap_oldest'], 'Expected oldest snapshot beyond keep count');
});

zss_test('retention policy does not prune when keep count is disabled', function() {
    zss_assert_true(class_exists('RetentionPolicy'), 'Expected RetentionPolicy class to exist');

    $candidates = RetentionPolicy::selectPruneCandidates([
        ['name' => 'tank/data@autosnap_oldest', 'creation' => 100],
    ], 0);

    zss_assert_true($candidates === [], 'Expected keep <= 0 to disable pruning');
});

