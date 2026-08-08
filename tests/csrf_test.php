<?php

$webRoot = dirname(__DIR__) . '/zfs.scheduled.snapshots/web';
require_once dirname(__DIR__) . '/zfs.scheduled.snapshots/include/validation.php';

zss_test('shell exposes the Unraid CSRF token to the WebUI', function() use ($webRoot) {
    $shell = file_get_contents($webRoot . '/layout/shell.php');

    zss_assert_true(
        strpos($shell, 'zss_csrf_token()') !== false,
        'Expected shell to resolve the Unraid CSRF token server-side'
    );
    zss_assert_true(
        strpos($shell, "require_once __DIR__ . '/../../include/validation.php';") !== false,
        'Expected shell to load validation.php from the plugin include root, not web/include'
    );
    zss_assert_true(
        strpos($shell, "window.ZSS_CSRF") !== false,
        'Expected shell to expose window.ZSS_CSRF to the WebUI'
    );
    zss_assert_true(
        strpos($shell, 'json_encode($nextCsrfToken') !== false,
        'Expected shell to serialize the token safely (no raw token in HTML attributes)'
    );
});

zss_test('postJson sends the CSRF token via header and mirrors it into the JSON body', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/next.js');

    zss_assert_true(
        strpos($script, "'X-CSRF-Token': token") !== false,
        'Expected postJson to send the X-CSRF-Token header that Unraid guard requires for JSON bodies'
    );
    zss_assert_true(
        strpos($script, 'body: JSON.stringify({ ...payload, csrf_token: token })') !== false,
        'Expected postJson to mirror csrf_token into the JSON body for plugin-side verification'
    );
});

zss_test('postJson refuses to send a write action when the client token is missing', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/next.js');

    zss_assert_true(
        strpos($script, "window.ZSS_CSRF") !== false,
        'Expected postJson to read the token from window.ZSS_CSRF'
    );
    zss_assert_true(
        strpos($script, "throw new Error(t('common.csrf_missing'") !== false,
        'Expected postJson to throw a clear error instead of sending when the token is unavailable'
    );
});

zss_test('postJson never reports false success on an empty or non-JSON write response', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/next.js');

    zss_assert_true(
        strpos($script, "if (text.trim() === '' || !contentType.includes('application/json'))") !== false,
        'Expected postJson to treat empty and non-JSON POST responses as failures'
    );
    zss_assert_true(
        strpos($script, "return { ok: true, data: null };") === false,
        'Expected postJson to no longer treat an empty response as a successful write'
    );
});

zss_test('csrf validation rejects a missing token', function() {
    $error = zss_validate_csrf_value('', 'expected-token-value');

    zss_assert_true($error !== null, 'Expected missing CSRF token to be rejected');
    zss_assert_true($error['code'] === 'CSRF_MISSING', 'Expected CSRF_MISSING error code');
    zss_assert_true($error['status'] === 403, 'Expected missing token to map to 403');
});

zss_test('csrf validation rejects a mismatched token with timing-safe comparison', function() {
    $error = zss_validate_csrf_value('wrong-token', 'expected-token-value');

    zss_assert_true($error !== null, 'Expected mismatched CSRF token to be rejected');
    zss_assert_true($error['code'] === 'CSRF_INVALID', 'Expected CSRF_INVALID error code');
    zss_assert_true($error['status'] === 403, 'Expected mismatched token to map to 403');
});

zss_test('csrf validation accepts the exact expected token', function() {
    $error = zss_validate_csrf_value('expected-token-value', 'expected-token-value');

    zss_assert_true($error === null, 'Expected matching CSRF token to be accepted');
});

zss_test('csrf validation refuses to trust a request when no server token exists', function() {
    $error = zss_validate_csrf_value('anything', '');

    zss_assert_true($error !== null, 'Expected request to be rejected when no server token is available');
    zss_assert_true($error['code'] === 'CSRF_UNAVAILABLE', 'Expected CSRF_UNAVAILABLE error code');
    zss_assert_true($error['status'] === 503, 'Expected unavailable token to map to 503');
});

zss_test('all write APIs enforce CSRF through the shared action payload gate', function() use ($webRoot) {
    $apiDir = dirname(__DIR__) . '/zfs.scheduled.snapshots/api';
    $writeApis = [
        'snapshot-create.php',
        'snapshot-delete.php',
        'snapshot-hold.php',
        'snapshot-release.php',
        'snapshot-rollback.php',
        'dataset-create.php',
        'dataset-update.php',
        'logs.php',
    ];

    foreach ($writeApis as $api) {
        $content = file_get_contents($apiDir . '/' . $api);
        zss_assert_true(
            strpos($content, 'zss_get_action_payload()') !== false,
            "Expected $api to enforce CSRF via zss_get_action_payload()"
        );
    }
});

zss_test('action payload strips the csrf_token field before returning business data', function() use ($webRoot) {
    $validation = file_get_contents(dirname(__DIR__) . '/zfs.scheduled.snapshots/include/validation.php');

    zss_assert_true(
        strpos($validation, "unset(\$payload['csrf_token']);") !== false,
        'Expected csrf_token to be stripped from the payload after validation'
    );
});
