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

zss_test('action boolean normalization treats false strings as false', function() {
    zss_assert_true(zss_normalize_bool('false') === false, 'Expected string false to normalize to false');
    zss_assert_true(zss_normalize_bool('true') === true, 'Expected string true to normalize to true');
});

zss_test('action requests require POST JSON without query parameters', function() {
    $valid = zss_validate_action_request([
        'REQUEST_METHOD' => 'POST',
        'HTTP_X_ZSS_ACTION' => '1',
        'HTTP_ORIGIN' => 'http://unraid.local',
        'HTTP_HOST' => 'unraid.local',
        'REQUEST_SCHEME' => 'http',
        'HTTP_SEC_FETCH_SITE' => 'same-origin',
    ]);
    zss_assert_true($valid === null, 'Expected same-origin POST action request to be accepted');

    $wrongMethod = zss_validate_action_request([
        'REQUEST_METHOD' => 'GET',
        'HTTP_X_ZSS_ACTION' => '1',
    ]);
    zss_assert_true($wrongMethod['code'] === 'ACTION_METHOD_REQUIRED', 'Expected GET action request to be rejected');

    $queryPayload = zss_validate_action_request([
        'REQUEST_METHOD' => 'POST',
        'HTTP_X_ZSS_ACTION' => '1',
    ], ['name' => 'tank/data@autosnap_2026-06-27_12:00:00']);
    zss_assert_true($queryPayload['code'] === 'ACTION_QUERY_DENIED', 'Expected action query parameters to be rejected');

    $wrongPort = zss_validate_action_request([
        'REQUEST_METHOD' => 'POST',
        'HTTP_X_ZSS_ACTION' => '1',
        'HTTP_ORIGIN' => 'https://unraid.local:4443',
        'HTTP_HOST' => 'unraid.local:8443',
        'REQUEST_SCHEME' => 'https',
    ]);
    zss_assert_true($wrongPort['code'] === 'ACTION_ORIGIN_DENIED', 'Expected mismatched origin port to be rejected');

    $wrongScheme = zss_validate_action_request([
        'REQUEST_METHOD' => 'POST',
        'HTTP_X_ZSS_ACTION' => '1',
        'HTTP_ORIGIN' => 'http://unraid.local',
        'HTTP_HOST' => 'unraid.local',
        'REQUEST_SCHEME' => 'https',
    ]);
    zss_assert_true($wrongScheme['code'] === 'ACTION_ORIGIN_DENIED', 'Expected mismatched origin scheme to be rejected');
});
