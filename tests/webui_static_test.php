<?php

$webRoot = dirname(__DIR__) . '/zfs.scheduled.snapshots/web';

zss_test('overview page does not contain a broken metric icon tag', function() use ($webRoot) {
    $index = file_get_contents($webRoot . '/index.php');

    zss_assert_true(
        strpos($index, '<div class="zss-metric-icon zss-icon-green"><?php echo zss_next_icon(\'play\'); ?></div>') !== false,
        'Expected enabled metric icon to render through the icon helper'
    );
});

zss_test('snapshot destructive actions send backend confirmation payloads', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/snapshots.js');

    zss_assert_true(
        strpos($script, "postJson('../api/snapshot-delete.php', { name, confirm: name })") !== false,
        'Expected delete action to confirm with the snapshot name'
    );
    zss_assert_true(
        strpos($script, 'postJson(\'../api/snapshot-release.php\', { name, tag, confirm: `${name}:${tag}` })') !== false,
        'Expected release action to confirm with snapshot name and hold tag'
    );
    zss_assert_true(
        strpos($script, "postJson('../api/snapshot-rollback.php', { name, confirm: typedName })") !== false,
        'Expected rollback action to confirm with the typed snapshot name'
    );
});

zss_test('snapshot action buttons store raw names without html entity corruption', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/snapshots.js');

    zss_assert_true(
        strpos($script, 'const encodedName = escapeHtml(JSON.stringify(snap.name));') !== false,
        'Expected snapshot names to be JSON encoded before writing data attributes'
    );
    zss_assert_true(
        strpos($script, 'name = JSON.parse(button.dataset.name || \'""\');') !== false,
        'Expected click handler to JSON decode action names'
    );
});

zss_test('snapshot page documents external snapshot action scope', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/snapshots.js');
    $translations = file_get_contents($webRoot . '/i18n.php');

    zss_assert_true(
        strpos($script, "actions.rollback") !== false,
        'Expected snapshot actions renderer to honor per-action rollback visibility'
    );
    zss_assert_true(
        strpos($translations, 'External snapshots can be held, released, or deleted, but rollback is limited to plugin-managed snapshots.') !== false,
        'Expected English notice to describe external snapshot action scope'
    );
    zss_assert_true(
        strpos($translations, '外部快照支持设置只读、释放和删除，但回滚仅限插件管理的快照。') !== false,
        'Expected Chinese notice to describe external snapshot action scope'
    );
});

zss_test('hold action uses custom modal feedback instead of browser confirm', function() use ($webRoot) {
    $snapshotsScript = file_get_contents($webRoot . '/assets/js/snapshots.js');
    $sharedScript = file_get_contents($webRoot . '/assets/js/next.js');
    $styles = file_get_contents($webRoot . '/assets/css/next.css');

    zss_assert_true(
        strpos($snapshotsScript, "confirm(t('snapshots.confirm_hold'") === false,
        'Expected hold action not to use browser confirm'
    );
    zss_assert_true(
        strpos($snapshotsScript, 'zssConfirmAction({') !== false,
        'Expected hold action to use the custom action dialog'
    );
    zss_assert_true(
        strpos($snapshotsScript, 'zssToast(') !== false,
        'Expected hold action to show toast feedback'
    );
    zss_assert_true(
        strpos($sharedScript, "event.target === overlay") !== false,
        'Expected custom action dialog to close when clicking the backdrop'
    );
    zss_assert_true(
        strpos($styles, '.zss-toast') !== false && strpos($styles, '.zss-row-flash') !== false,
        'Expected toast and row feedback styles'
    );
});

zss_test('snapshot actions use unified custom dialogs and toast feedback', function() use ($webRoot) {
    $snapshotsScript = file_get_contents($webRoot . '/assets/js/snapshots.js');
    $sharedScript = file_get_contents($webRoot . '/assets/js/next.js');

    zss_assert_true(
        strpos($snapshotsScript, 'confirm(') === false,
        'Expected snapshot actions not to use browser confirm'
    );
    zss_assert_true(
        strpos($snapshotsScript, 'prompt(') === false,
        'Expected snapshot actions not to use browser prompt'
    );
    zss_assert_true(
        strpos($snapshotsScript, 'alert(') === false,
        'Expected snapshot actions not to use browser alert'
    );
    zss_assert_true(
        substr_count($snapshotsScript, 'zssConfirmAction({') >= 5,
        'Expected create delete hold release and rollback to use the custom action dialog'
    );
    zss_assert_true(
        strpos($sharedScript, 'inputLabel') !== false && strpos($sharedScript, 'inputValue') !== false,
        'Expected custom action dialog to support typed confirmation fields'
    );
});

zss_test('write actions use POST JSON transport and preserve business errors', function() use ($webRoot) {
    $sharedScript = file_get_contents($webRoot . '/assets/js/next.js');
    $logsScript = file_get_contents($webRoot . '/assets/js/logs.js');

    zss_assert_true(
        strpos($sharedScript, "method: 'POST'") !== false,
        'Expected write transport to use POST'
    );
    zss_assert_true(
        strpos($sharedScript, "'Content-Type': 'application/json'") !== false,
        'Expected write transport to send JSON content type'
    );
    zss_assert_true(
        strpos($sharedScript, 'body: JSON.stringify(payload)') !== false,
        'Expected write transport to serialize payload in the request body'
    );
    zss_assert_true(
        strpos($sharedScript, "error: data?.error || { code: 'HTTP_ERROR'") !== false,
        'Expected business API errors to be returned to action-specific UI handlers'
    );
    zss_assert_true(
        strpos($logsScript, "postJson('../api/logs.php', { action: 'clear' })") !== false,
        'Expected clear-logs action to use JSON body instead of query parameters'
    );
});

zss_test('manual snapshot hold UI documents the permanent manual tag', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/snapshots.js');
    $translations = file_get_contents($webRoot . '/i18n.php');

    zss_assert_true(
        strpos($script, 'Permanent manual hold tag zss_manual was added.') !== false,
        'Expected hold success feedback to name the permanent manual tag'
    );
    zss_assert_true(
        strpos($translations, 'Scheduled retention never releases it; legacy autosnap holds are also preserved.') !== false,
        'Expected English hold dialog to document legacy hold preservation'
    );
    zss_assert_true(
        strpos($translations, '历史 autosnap hold 也会被保留。') !== false,
        'Expected Chinese hold dialog to document legacy hold preservation'
    );
});

zss_test('scheduled protection uses zss_auto and preserves legacy autosnap holds', function() {
    $common = file_get_contents(dirname(__DIR__) . '/zfs.scheduled.snapshots/include/common.php');
    $runner = file_get_contents(dirname(__DIR__) . '/zfs.scheduled.snapshots/scripts/runner.php');

    zss_assert_true(
        strpos($common, "const AUTO_HOLD_TAG = 'zss_auto'") !== false,
        'Expected new scheduled protection tag to be zss_auto'
    );
    zss_assert_true(
        strpos($common, "const LEGACY_HOLD_TAG = 'autosnap'") !== false,
        'Expected legacy autosnap tag to remain explicitly documented'
    );
    zss_assert_true(
        strpos($common, 'releaseAutomaticHold') !== false && strpos($common, 'self::AUTO_HOLD_TAG') !== false,
        'Expected expiry maintenance to release only the new automatic tag'
    );
    zss_assert_true(
        strpos($runner, 'recoverPendingAutoHold') !== false,
        'Expected runner to recover pending automatic holds before maintenance'
    );
    zss_assert_true(
        strpos($runner, 'AUTO_HOLD_TAG') !== false,
        'Expected runner-created snapshots to use the new automatic hold tag'
    );
});

zss_test('snapshot create API normalizes readonly input and reports hold failures', function() {
    $api = file_get_contents(dirname(__DIR__) . '/zfs.scheduled.snapshots/api/snapshot-create.php');

    zss_assert_true(
        strpos($api, "zss_normalize_bool(\$payload['readonly'] ?? false)") !== false,
        'Expected snapshot-create endpoint to normalize readonly input'
    );
    zss_assert_true(
        strpos($api, "'SNAPSHOT_HOLD_FAILED'") === false,
        'Expected snapshot-create endpoint to use the service error code rather than hard-code a result'
    );
    zss_assert_true(
        strpos($api, "\$result['code'] ?? 'CREATE_FAILED'") !== false,
        'Expected snapshot-create endpoint to return explicit service error codes'
    );
});

zss_test('action API responses disable caching and do not expose exception paths', function() {
    $response = file_get_contents(dirname(__DIR__) . '/zfs.scheduled.snapshots/include/response.php');

    zss_assert_true(
        strpos($response, "header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');") !== false,
        'Expected API responses to disable caching'
    );
    zss_assert_true(
        strpos($response, "zss_json_error('INTERNAL_ERROR', 'An internal server error occurred', 500)") !== false,
        'Expected exceptions to return a generic internal error'
    );
    zss_assert_true(
        strpos($response, "'file' => \$error->getFile()") === false,
        'Expected exception responses not to expose server file paths'
    );
    zss_assert_true(
        strpos($response, "'file' => \$error['file']") === false,
        'Expected fatal responses not to expose server file paths'
    );
});

zss_test('webui assets are loaded with cache busting versions', function() use ($webRoot) {
    $shell = file_get_contents($webRoot . '/layout/shell.php');
    $footer = file_get_contents($webRoot . '/layout/footer.php');

    zss_assert_true(
        strpos($shell, 'function zss_asset_url') !== false,
        'Expected shell layout to define asset cache busting helper'
    );
    zss_assert_true(
        strpos($shell, "zss_asset_url('assets/css/next.css')") !== false,
        'Expected next.css to use cache busting URL'
    );
    zss_assert_true(
        strpos($footer, "zss_asset_url('assets/js/next.js')") !== false,
        'Expected next.js to use cache busting URL'
    );
    zss_assert_true(
        strpos($footer, 'zss_asset_url($nextPageScript)') !== false,
        'Expected page script to use cache busting URL'
    );
});
