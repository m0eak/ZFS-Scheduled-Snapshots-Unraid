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
        strpos($sharedScript, 'body: JSON.stringify({ ...payload, csrf_token: token })') !== false,
        'Expected write transport to serialize the payload (with CSRF token) in the request body'
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

zss_test('main plugin preview inherits the host Unraid theme instead of forcing white cards', function() {
    $page = file_get_contents(dirname(__DIR__) . '/zfs.scheduled.snapshots/ZFSScheduledSnapshots.page');

    zss_assert_true(
        strpos($page, '.zss-preview') !== false && strpos($page, 'background: var(--mild-background-color') !== false,
        'Expected the main plugin preview to use the host mild background color'
    );
    zss_assert_true(
        strpos($page, 'color: var(--text-color') !== false && strpos($page, 'color: var(--alt-text-color') !== false,
        'Expected main plugin preview text to inherit host theme colors'
    );
    zss_assert_true(
        strpos($page, 'background: #fff;') === false && strpos($page, 'background: #fafbfc;') === false,
        'Expected the main plugin preview to avoid forced light card backgrounds'
    );
});


zss_test('webui follows the active Unraid Dynamix theme without a plugin color override', function() use ($webRoot) {
    $shell = file_get_contents($webRoot . '/layout/shell.php');
    $styles = file_get_contents($webRoot . '/assets/css/next.css');
    $script = file_get_contents($webRoot . '/assets/js/next.js');
    $settings = file_get_contents($webRoot . '/settings.php');

    zss_assert_true(
        strpos($shell, '/webGui/styles/default-color-palette.css') !== false && strpos($shell, '/webGui/styles/themes/') !== false,
        'Expected standalone WebUI to load Unraid palette and active theme variables'
    );
    zss_assert_true(
        strpos($shell, "require_once __DIR__ . '/../../include/validation.php';") !== false,
        'Expected standalone WebUI to retain the CSRF validation include from the plugin root'
    );
    zss_assert_true(
        strpos($shell, "is_file(\$themePath)") !== false,
        'Expected installed custom Unraid themes to be accepted when their stylesheet exists'
    );
    zss_assert_true(
        strpos($styles, 'background: var(--background-color') !== false && strpos($styles, 'color: var(--text-color') !== false,
        'Expected standalone WebUI colors to inherit Unraid CSS variables'
    );
    zss_assert_true(
        strpos($styles, '--zss-bg:') === false && strpos($styles, 'data-effective-theme') === false,
        'Expected the old plugin-owned theme color system to be removed'
    );
    zss_assert_true(
        strpos($script, 'syncUnraidThemeFromOpener') === false && strpos($script, 'zss_theme') === false && strpos($script, 'zss_accent') === false,
        'Expected no ineffective client-side theme override to remain'
    );
    zss_assert_true(
        strpos($settings, 'settings.accent.title') === false && strpos($settings, 'handleSettingsThemeChange') === false,
        'Expected Settings to stop exposing a plugin-owned theme or accent preference'
    );
});

zss_test('webui provides a resource tree navigation fed by the datasets API', function() use ($webRoot) {
    $shell = file_get_contents($webRoot . '/layout/shell.php');
    $script = file_get_contents($webRoot . '/assets/js/next.js');

    zss_assert_true(
        strpos($shell, 'id="zss-resource-tree"') !== false,
        'Expected the sidebar to render a dataset resource tree container'
    );
    zss_assert_true(
        strpos($script, 'loadResourceTree') !== false && strpos($script, 'buildDatasetTree') !== false,
        'Expected next.js to build and render the resource tree'
    );
    zss_assert_true(
        strpos($script, "'../api/datasets.php'") !== false,
        'Expected the resource tree to be fed by the live datasets API'
    );
    zss_assert_true(
        strpos($script, 'refreshResourceTree') !== false,
        'Expected the resource tree to expose a refresh hook for dataset mutations'
    );
});

zss_test('snapshot page syncs dataset context from the URL and the selector', function() use ($webRoot) {
    $page = file_get_contents($webRoot . '/snapshots.php');
    $script = file_get_contents($webRoot . '/assets/js/snapshots.js');

    zss_assert_true(
        strpos($page, "\$_GET['dataset']") !== false,
        'Expected the snapshot page to read the current dataset from the URL'
    );
    zss_assert_true(
        strpos($page, 'id="snapshot-dataset-select"') !== false,
        'Expected the snapshot page to expose a dataset selector'
    );
    zss_assert_true(
        strpos($script, 'loadSnapshotDatasetSelector') !== false && strpos($script, 'updateSnapshotContext') !== false,
        'Expected snapshots.js to populate the selector and refresh the dataset context'
    );
    zss_assert_true(
        strpos($script, 'select.value = dataset') !== false,
        'Expected the selector to reflect the dataset from the URL'
    );
    zss_assert_true(
        strpos($script, "window.location.href = withLang(target)") !== false,
        'Expected changing the selector to navigate with the dataset in the URL'
    );
});

zss_test('webui styles avoid hardcoded white cards and rely on the host theme', function() use ($webRoot) {
    $styles = file_get_contents($webRoot . '/assets/css/next.css');

    zss_assert_true(
        strpos($styles, 'background: #fff;') === false && strpos($styles, 'background: #fafbfc;') === false,
        'Expected standalone WebUI cards to inherit the host theme instead of forcing white'
    );
});

zss_test('panel surfaces use the host panel variable and never the overlay color', function() use ($webRoot) {
    $styles = file_get_contents($webRoot . '/assets/css/next.css');
    $surfaces = ['.zss-sidebar', '.zss-modal-card', '.zss-action-dialog', '.zss-toast'];

    foreach ($surfaces as $surface) {
        $blockStart = strpos($styles, $surface . ' {');
        zss_assert_true($blockStart !== false, "Expected the {$surface} style rule to remain present");
        $blockEnd = strpos($styles, '}', $blockStart);
        zss_assert_true($blockEnd !== false, "Expected the {$surface} style rule to be well formed");
        $block = substr($styles, $blockStart, $blockEnd - $blockStart);
        zss_assert_true(
            strpos($block, '--opac-background-color') === false,
            "Expected {$surface} to stop referencing the semi-transparent --opac-background-color"
        );
        zss_assert_true(
            strpos($block, '--mild-background-color') !== false,
            "Expected {$surface} to pair with the host --mild-background-color variable"
        );
    }
});

zss_test('resource tree renders synthetic branches as semantic non-links', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/next.js');

    zss_assert_true(
        strpos($script, ": '#'") === false,
        'Expected the resource tree not to fall back to href="#" for synthetic branches'
    );
    zss_assert_true(
        strpos($script, 'aria-disabled="true"') === false,
        'Expected the resource tree not to render disabled anchor placeholders'
    );
    zss_assert_true(
        strpos($script, '<a class="${linkClass}"') !== false && strpos($script, '<span class="${linkClass}"') !== false,
        'Expected the resource tree to branch real dataset links from synthetic non-link branches'
    );
});

zss_test('resource tree announces load errors assertively', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/next.js');

    zss_assert_true(
        strpos($script, 'role="alert"') !== false,
        'Expected the resource tree load error to use an assertive alert role'
    );
});

zss_test('snapshot risk action groups carry semantic group markers', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/snapshots.js');
    $translations = file_get_contents($webRoot . '/i18n.php');

    zss_assert_true(
        strpos($script, 'role="group" aria-label=') !== false,
        'Expected risk-tiered action groups to be exposed as semantic groups'
    );
    zss_assert_true(
        strpos($translations, "'snapshots.actions.safe'") !== false && strpos($translations, "'snapshots.actions.destructive'") !== false,
        'Expected i18n labels for safe and destructive action groups'
    );
});

zss_test('snapshot context strip shows a fallback when the URL dataset does not exist', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/snapshots.js');
    $translations = file_get_contents($webRoot . '/i18n.php');

    zss_assert_true(
        strpos($script, 'applyContextStripFallback') !== false,
        'Expected a shared fallback helper for the snapshot context strip'
    );
    zss_assert_true(
        strpos($script, "t('snapshots.context.not_found'") !== false,
        'Expected the context strip to use the not-found fallback for unknown datasets'
    );
    zss_assert_true(
        strpos($translations, "'snapshots.context.not_found' => 'Dataset not found'") !== false,
        'Expected English fallback when the URL dataset does not exist'
    );
    zss_assert_true(
        strpos($translations, "'snapshots.context.not_found' => '数据集不存在'") !== false,
        'Expected Chinese fallback when the URL dataset does not exist'
    );
});
