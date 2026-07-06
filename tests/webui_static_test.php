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
