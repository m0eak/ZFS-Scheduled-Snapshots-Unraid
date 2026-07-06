<?php

require_once dirname(__DIR__) . '/zfs.scheduled.snapshots/include/validation.php';

zss_test('destructive action confirmation is required', function() {
    $error = zss_validate_action_confirmation([], 'tank/data@autosnap_2026-06-27_12:00:00');

    zss_assert_true($error !== null, 'Expected missing confirmation to be rejected');
    zss_assert_true($error['code'] === 'CONFIRMATION_REQUIRED', 'Expected required confirmation error code');
});

zss_test('destructive action confirmation must match expected value', function() {
    $error = zss_validate_action_confirmation(
        ['confirm' => 'tank/data@other'],
        'tank/data@autosnap_2026-06-27_12:00:00'
    );

    zss_assert_true($error !== null, 'Expected mismatched confirmation to be rejected');
    zss_assert_true($error['code'] === 'CONFIRMATION_MISMATCH', 'Expected mismatch confirmation error code');
});

zss_test('destructive action confirmation accepts exact expected value', function() {
    $error = zss_validate_action_confirmation(
        ['confirm' => 'tank/data@autosnap_2026-06-27_12:00:00'],
        'tank/data@autosnap_2026-06-27_12:00:00'
    );

    zss_assert_true($error === null, 'Expected matching confirmation to be accepted');
});

zss_test('hold release confirmation includes snapshot name and hold tag', function() {
    $error = zss_validate_action_confirmation(
        ['confirm' => 'tank/data@autosnap_2026-06-27_12:00:00:autosnap'],
        'tank/data@autosnap_2026-06-27_12:00:00:autosnap'
    );

    zss_assert_true($error === null, 'Expected release confirmation to include snapshot name and tag');
});
