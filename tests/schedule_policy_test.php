<?php

$schedulePolicyPath = dirname(__DIR__) . '/zfs.scheduled.snapshots/include/SchedulePolicy.php';
if (file_exists($schedulePolicyPath)) {
    require_once $schedulePolicyPath;
}

zss_test('schedule policy marks interval snapshots due after elapsed interval', function() {
    zss_assert_true(class_exists('SchedulePolicy'), 'Expected SchedulePolicy class to exist');

    $now = strtotime('2026-07-05 12:00:00');
    $result = SchedulePolicy::evaluate([
        'frequency' => '15min',
        'latest_timestamp' => $now - 901,
        'now' => $now,
    ]);

    zss_assert_true($result['due'] === true, 'Expected interval schedule to be due');
});

zss_test('schedule policy skips daily snapshot before target time', function() {
    zss_assert_true(class_exists('SchedulePolicy'), 'Expected SchedulePolicy class to exist');

    $now = strtotime('2026-07-05 11:59:00');
    $result = SchedulePolicy::evaluate([
        'frequency' => 'daily',
        'time' => '12:00',
        'latest_timestamp' => 0,
        'now' => $now,
    ]);

    zss_assert_true($result['due'] === false, 'Expected daily schedule to wait for target time');
});

zss_test('schedule policy marks monthly snapshot due on clamped month day', function() {
    zss_assert_true(class_exists('SchedulePolicy'), 'Expected SchedulePolicy class to exist');

    $now = strtotime('2026-02-28 12:01:00');
    $result = SchedulePolicy::evaluate([
        'frequency' => 'monthly',
        'time' => '12:00',
        'day' => 31,
        'latest_timestamp' => 0,
        'now' => $now,
    ]);

    zss_assert_true($result['due'] === true, 'Expected monthly schedule to clamp to last day of month');
});

