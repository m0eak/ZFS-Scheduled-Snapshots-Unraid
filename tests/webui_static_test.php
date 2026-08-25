<?php

$webRoot = dirname(__DIR__) . '/zfs.scheduled.snapshots/web';

zss_test('overview page does not contain a broken metric icon tag', function() use ($webRoot) {
    $index = file_get_contents($webRoot . '/index.php');

    zss_assert_true(
        strpos($index, '<div class="zss-metric-icon zss-icon-green"><?php echo zss_next_icon(\'play\'); ?></div>') !== false,
        'Expected enabled metric icon to render through the icon helper'
    );
});

zss_test('resource tree supports persistent accessible collapse controls', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/next.js');
    $styles = file_get_contents($webRoot . '/assets/css/next.css');

    zss_assert_true(
        strpos($script, "const ZSS_TREE_COLLAPSED_KEY = 'zss_tree_collapsed';") !== false,
        'Expected resource tree collapse state to persist per browser'
    );
    zss_assert_true(
        strpos($script, 'aria-expanded=') !== false && strpos($script, "closest('.zss-tree-toggle')") !== false,
        'Expected accessible delegated resource tree toggle controls'
    );
    zss_assert_true(
        strpos($script, 'treeNodeContainsDataset(node, currentDataset)') !== false,
        'Expected the active dataset branch to remain expanded'
    );
    zss_assert_true(
        strpos($styles, '.zss-tree-item.is-collapsed > ul') !== false,
        'Expected collapsed resource tree branches to hide child lists'
    );
});

zss_test('snapshot context and logs use compact non-wrapping presentation', function() use ($webRoot) {
    $snapshotsPage = file_get_contents($webRoot . '/snapshots.php');
    $snapshotsScript = file_get_contents($webRoot . '/assets/js/snapshots.js');
    $logsPage = file_get_contents($webRoot . '/logs.php');
    $styles = file_get_contents($webRoot . '/assets/css/next.css');

    zss_assert_true(
        strpos($snapshotsPage, 'zss-context-title-row') !== false,
        'Expected snapshot status to align with the current dataset title'
    );
    zss_assert_true(
        strpos($snapshotsScript, "const last = parts.pop()") === false,
        'Expected snapshot breadcrumb not to repeat the selected child dataset name'
    );
    zss_assert_true(
        strpos($logsPage, 'zss-table zss-log-table') !== false,
        'Expected the log table to have a dedicated layout scope'
    );
    zss_assert_true(
        strpos($styles, 'white-space: nowrap; cursor: pointer;') !== false
            && strpos($styles, '.zss-log-message { white-space: nowrap !important;') !== false,
        'Expected command buttons and log messages to remain on one line'
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

zss_test('panel entrance motion fails visible and plays only under data-zss-entered', function() use ($webRoot) {
    $styles = file_get_contents($webRoot . '/assets/css/next.css');
    // Comments may mention opacity values illustratively; parse the rules only.
    $css = preg_replace('/\/\*.*?\*\//s', '', $styles);

    // 1 · Fail-visible default: no rule may hide a shared surface unless the
    //     very same selector is scoped under [data-zss-entered]. Keyframe
    //     frames are exempt (they define the entrance itself).
    preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $pairs, PREG_SET_ORDER);
    $sharedSurfaces = ['zss-panel', 'zss-metrics-grid', 'zss-info-grid'];
    foreach ($pairs as $pair) {
        $selector = trim($pair[1]);
        $body = $pair[2];
        if ($selector === '' || $selector[0] === '@') {
            continue;
        }
        if (stripos($body, 'opacity: 0') === false && stripos($body, 'opacity:0') === false) {
            continue;
        }
        foreach (explode(',', $selector) as $part) {
            $part = trim($part);
            foreach ($sharedSurfaces as $surface) {
                if (strpos($part, $surface) !== false) {
                    zss_assert_true(
                        strpos($part, '[data-zss-entered]') !== false,
                        "Expected hiding rule '{$part}' to be gated by [data-zss-entered]"
                    );
                }
            }
        }
    }

    // 2 · Entrance is an enhancement armed exclusively by the Overview marker:
    //     exactly one application rule and it lives under [data-zss-entered].
    zss_assert_true(
        substr_count($css, 'animation: zssRiseIn') === 1,
        'Expected exactly one zssRiseIn application rule'
    );
    zss_assert_true(
        strpos($css, '[data-zss-entered] .zss-metrics-grid') !== false
            && strpos($css, '[data-zss-entered] .zss-info-grid') !== false
            && strpos($css, '[data-zss-entered] .zss-panel') !== false,
        'Expected entrance rules to exist only under the [data-zss-entered] scope'
    );

    // 3 · Reliable initial frame: explicit from/to keyframes plus a backwards
    //     fill (both) so delayed items start hidden and land on the final state.
    zss_assert_true(
        preg_match('/@keyframes\s+zssRiseIn\s*\{\s*from\s*\{\s*opacity:\s*0;\s*transform:\s*translateY\(10px\);\s*\}\s*to\s*\{\s*opacity:\s*1;\s*transform:\s*translateY\(0\);\s*\}\s*\}/', $css) === 1,
        'Expected zssRiseIn to declare explicit from/to frames'
    );
    zss_assert_true(
        preg_match('/animation:\s*zssRiseIn[^;}]*\bboth\b/', $css) === 1,
        'Expected the entrance animation to use the both fill mode'
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


zss_test('webui inherits Unraid theme variables and exposes per-browser theme control', function() use ($webRoot) {
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
        strpos($styles, '--zss-bg:') === false && strpos($styles, 'syncUnraidThemeFromOpener') === false && strpos($styles, 'zss_accent') === false,
        'Expected the old plugin-owned theme color system to be removed'
    );
    zss_assert_true(
        strpos($styles, '.zss-next[data-effective-theme="dark"]') !== false && strpos($styles, '.zss-next[data-effective-theme="light"]') !== false,
        'Expected scoped forced dark/light overrides to be present'
    );
    zss_assert_true(
        strpos($script, 'zss_theme') !== false,
        'Expected next.js to persist the per-browser theme under zss_theme'
    );
    zss_assert_true(
        strpos($script, 'syncUnraidThemeFromOpener') === false && strpos($script, 'zss_accent') === false,
        'Expected no leftover opener-sync or accent override logic'
    );
    zss_assert_true(
        strpos($settings, 'settings.accent.title') === false,
        'Expected Settings to stop exposing a plugin-owned accent preference'
    );
    zss_assert_true(
        strpos($settings, 'handleSettingsThemeChange') !== false,
        'Expected Settings to wire the Appearance select to the theme handler'
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

zss_test('app shell opens exactly one topbar, one locale switcher, and one content section', function() use ($webRoot) {
    $shell = file_get_contents($webRoot . '/layout/shell.php');

    zss_assert_true(
        substr_count($shell, '<header class="zss-topbar">') === 1 &&
        substr_count($shell, '</header>') === 1,
        'Expected the app shell to open and close exactly one topbar'
    );
    zss_assert_true(
        substr_count($shell, '<section class="zss-content">') === 1,
        'Expected the app shell to open exactly one content section (regression: duplicate zss-content pushed content below the sidebar)'
    );
    zss_assert_true(
        substr_count($shell, 'id="global-language-switcher"') === 1 &&
        substr_count($shell, '<select') === 1,
        'Expected exactly one uniquely-identified language select in the app shell'
    );
    zss_assert_true(
        strpos($shell, '<section class="zss-content">') > strpos($shell, '</header>'),
        'Expected the content section to open only after the topbar closes'
    );
});

zss_test('app shell footer closes exactly one content section, main, and shell wrapper in order', function() use ($webRoot) {
    $footer = file_get_contents($webRoot . '/layout/footer.php');

    $closeSection = strpos($footer, '</section>');
    $closeMain = strpos($footer, '</main>');
    $closeShell = strpos($footer, '</div>');

    zss_assert_true(
        $closeSection !== false && $closeMain !== false && $closeShell !== false,
        'Expected the footer to close the content section, main, and app-shell wrapper'
    );
    zss_assert_true(
        $closeSection < $closeMain && $closeMain < $closeShell,
        'Expected footer closing order to be </section> then </main> then </div>'
    );
    zss_assert_true(
        substr_count($footer, '</section>') === 1 &&
        substr_count($footer, '</main>') === 1 &&
        substr_count($footer, '</div>') === 1,
        'Expected the footer to close exactly one of each wrapper level'
    );
});

zss_test('theme preference uses a strict whitelist and falls back to auto', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/next.js');
    $shell = file_get_contents($webRoot . '/layout/shell.php');

    zss_assert_true(
        strpos($script, "const ZSS_THEME_VALUES = ['auto', 'light', 'dark']") !== false,
        'Expected next.js to define an explicit theme whitelist'
    );
    zss_assert_true(
        strpos($script, "return ZSS_THEME_VALUES.indexOf(stored) !== -1 ? stored : 'auto';") !== false,
        'Expected getStoredTheme to fall back to auto for invalid values'
    );
    zss_assert_true(
        strpos($script, "const safeTheme = ZSS_THEME_VALUES.indexOf(theme) !== -1 ? theme : 'auto';") !== false,
        'Expected applyTheme to coerce invalid themes back to auto'
    );
    zss_assert_true(
        strpos($shell, "theme = (stored === 'auto' || stored === 'light' || stored === 'dark') ? stored : 'auto';") !== false,
        'Expected the anti-flash bootstrap to validate the stored theme before applying'
    );
});

zss_test('anti-flash theme script runs before the host and next stylesheets', function() use ($webRoot) {
    $shell = file_get_contents($webRoot . '/layout/shell.php');

    $bootstrapPos = strpos($shell, "localStorage.getItem('zss_theme')");
    $palettePos = strpos($shell, '/webGui/styles/default-color-palette.css');
    $themeCssPos = strpos($shell, 'href="/webGui/styles/themes/');
    $nextCssPos = strpos($shell, "assets/css/next.css");

    zss_assert_true(
        $bootstrapPos !== false && $palettePos !== false && $themeCssPos !== false && $nextCssPos !== false,
        'Expected the anti-flash bootstrap and all stylesheet links to be present'
    );
    zss_assert_true(
        $bootstrapPos < $palettePos && $bootstrapPos < $themeCssPos && $bootstrapPos < $nextCssPos,
        'Expected the anti-flash script to run before any host or next stylesheet applies'
    );
    zss_assert_true(
        strpos($shell, "root.dataset.theme = theme;") !== false &&
        strpos($shell, "root.dataset.effectiveTheme = effective;") !== false &&
        strpos($shell, "root.style.colorScheme = effective;") !== false,
        'Expected the bootstrap to set data-theme, data-effective-theme, and colorScheme on the root element'
    );
});

zss_test('settings expose an accessible appearance select with auto/light/dark', function() use ($webRoot) {
    $settings = file_get_contents($webRoot . '/settings.php');

    zss_assert_true(
        strpos($settings, 'id="settings-theme"') !== false &&
        strpos($settings, "onchange=\"handleSettingsThemeChange(this.value)\"") !== false,
        'Expected the Appearance select to be wired to the theme handler'
    );
    zss_assert_true(
        strpos($settings, '<option value="auto">') !== false &&
        strpos($settings, '<option value="light">') !== false &&
        strpos($settings, '<option value="dark">') !== false,
        'Expected Browser default, Light, and Dark options in the Appearance select'
    );
    zss_assert_true(
        strpos($settings, '<label class="zss-field">') !== false,
        'Expected the Appearance select to be wrapped in a labelled field'
    );
    zss_assert_true(
        strpos($settings, 'id="effective-theme-preview"') !== false,
        'Expected an effective-theme preview target'
    );
    zss_assert_true(
        strpos($settings, 'document.body.dataset.hostTheme') !== false,
        'Expected the host theme label to read the split data-host-theme attribute'
    );
});

zss_test('auto theme follows the OS scheme but explicit modes stay pinned', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/next.js');

    zss_assert_true(
        strpos($script, "window.matchMedia('(prefers-color-scheme: dark)')") !== false,
        'Expected next.js to observe the OS color scheme'
    );
    zss_assert_true(
        strpos($script, "media.addEventListener('change', onSystemThemeChange)") !== false,
        'Expected next.js to register a change listener on the media query'
    );
    zss_assert_true(
        strpos($script, "if (getStoredTheme() === 'auto') {") !== false &&
        strpos($script, "applyTheme('auto');") !== false,
        'Expected the OS scheme change to re-apply only while the preference is auto'
    );
});

zss_test('forced light/dark overrides are scoped to the effective theme', function() use ($webRoot) {
    $styles = file_get_contents($webRoot . '/assets/css/next.css');

    zss_assert_true(
        strpos($styles, '.zss-next[data-effective-theme="dark"]') !== false &&
        strpos($styles, 'html[data-effective-theme="dark"] .zss-next') !== false,
        'Expected a scoped dark override keyed on data-effective-theme'
    );
    zss_assert_true(
        strpos($styles, '.zss-next[data-effective-theme="light"]') !== false &&
        strpos($styles, 'html[data-effective-theme="light"] .zss-next') !== false,
        'Expected a scoped light override keyed on data-effective-theme'
    );
    zss_assert_true(
        strpos($styles, '--background-color: #1e1e1e') !== false &&
        strpos($styles, '--text-color: #e0e0e0') !== false,
        'Expected the dark override to remap the core background and text tokens'
    );
    zss_assert_true(
        strpos($styles, '--background-color: #f2f2f2') !== false &&
        strpos($styles, '--text-color: #303030') !== false,
        'Expected the light override to remap the core background and text tokens'
    );
});

zss_test('host and user theme attributes are split on the body element', function() use ($webRoot) {
    $shell = file_get_contents($webRoot . '/layout/shell.php');
    $script = file_get_contents($webRoot . '/assets/js/next.js');

    zss_assert_true(
        strpos($shell, 'data-host-theme="<?php echo htmlspecialchars($zssTheme[\'name\']); ?>"') !== false &&
        strpos($shell, 'data-host-theme-dark="<?php echo $zssTheme[\'dark\'] ? \'1\' : \'0\'; ?>"') !== false,
        'Expected the host Unraid theme to be reported via data-host-theme attributes'
    );
    zss_assert_true(
        strpos($shell, 'data-theme="<?php echo htmlspecialchars($zssTheme[\'name\']); ?>"') === false,
        'Expected the host theme name to no longer be injected into the user data-theme attribute'
    );
    zss_assert_true(
        strpos($script, "document.body.dataset.theme = safeTheme;") !== false &&
        strpos($script, "document.body.dataset.effectiveTheme = effective;") !== false,
        'Expected next.js to mirror the per-browser theme onto the body user attributes'
    );
});

zss_test('overview page hosts the storage console ring and space usage containers', function() use ($webRoot) {
    $index = file_get_contents($webRoot . '/index.php');
    $script = file_get_contents($webRoot . '/assets/js/overview.js');
    $translations = file_get_contents($webRoot . '/i18n.php');

    zss_assert_true(
        strpos($index, 'id="protection-ring"') !== false && strpos($index, 'zss-ring-value') !== false,
        'Expected the overview page to contain the protection ring container with a center value'
    );
    zss_assert_true(
        strpos($index, 'id="space-list"') !== false && strpos($index, 'id="space-total"') !== false,
        'Expected the overview page to contain the snapshot space usage list and total footer'
    );
    zss_assert_true(
        strpos($script, 'renderProtectionRing') !== false && strpos($script, 'conic-gradient(') !== false,
        'Expected overview.js to render the conic-gradient protection ring'
    );
    zss_assert_true(
        strpos($script, 'total > 0 ?') !== false,
        'Expected the protection percentage to guard against divide-by-zero'
    );
    zss_assert_true(
        strpos($script, 'renderSpaceUsage') !== false && strpos($script, "Number(ds.snapshot_used_bytes)") !== false,
        'Expected overview.js to render per-dataset space usage from snapshot_used_bytes'
    );
    zss_assert_true(
        strpos($translations, "'overview.console.protected_ring'") !== false && strpos($translations, "'overview.console.space_usage'") !== false,
        'Expected i18n entries for the storage console sections'
    );
});

zss_test('datasets table renders keep gauge held badge and readonly window', function() use ($webRoot) {
    $page = file_get_contents($webRoot . '/datasets.php');
    $script = file_get_contents($webRoot . '/assets/js/datasets.js');
    $translations = file_get_contents($webRoot . '/i18n.php');

    zss_assert_true(
        substr_count($page, '<th>') === 10 && strpos($page, "colspan=\"10\"") !== false,
        'Expected the datasets table to carry the new held column and matching colspan'
    );
    zss_assert_true(
        strpos($script, 'renderKeepGauge') !== false && strpos($script, 'zss-keep-gauge') !== false,
        'Expected datasets.js to render the inline keep gauge'
    );
    zss_assert_true(
        strpos($script, 'count > keep ? \'high\'') !== false,
        'Expected the keep gauge to flag over-quota datasets with the high tier'
    );
    zss_assert_true(
        strpos($script, 'renderHeldBadge') !== false && strpos($script, 'held_snapshot_count') !== false,
        'Expected datasets.js to render the held badge from held_snapshot_count'
    );
    zss_assert_true(
        strpos($script, 'renderReadonlyWindow') !== false && strpos($script, "'datasets.readonly_window.off'") !== false,
        'Expected the readonly window column to fall back to an explicit off label'
    );
    zss_assert_true(
        strpos($script, 'snapshots.php?dataset=') !== false,
        'Expected dataset names to keep linking to the snapshots page'
    );
    zss_assert_true(
        strpos($translations, "'table.held'") !== false && strpos($translations, "'datasets.keep_gauge.of'") !== false,
        'Expected i18n entries for the held column and keep gauge count'
    );
});

zss_test('snapshot timeline groups by day with hover-revealed actions', function() use ($webRoot) {
    $page = file_get_contents($webRoot . '/snapshots.php');
    $script = file_get_contents($webRoot . '/assets/js/snapshots.js');
    $styles = file_get_contents($webRoot . '/assets/css/next.css');
    $translations = file_get_contents($webRoot . '/i18n.php');

    zss_assert_true(
        strpos($page, 'id="snapshots-timeline"') !== false && strpos($page, 'class="zss-timeline"') !== false,
        'Expected the snapshots page to host the timeline container instead of the old table body'
    );
    zss_assert_true(
        strpos($script, 'groupSnapshotsByDay') !== false,
        'Expected snapshots.js to group snapshots by day'
    );
    zss_assert_true(
        strpos($script, "'snapshots.timeline.today'") !== false && strpos($script, "'snapshots.timeline.days_ago'") !== false,
        'Expected day labels to use today/yesterday/relative-days translations'
    );
    zss_assert_true(
        strpos($script, "getElementById('snapshots-timeline').addEventListener") !== false,
        'Expected action delegation to move from the removed table to the timeline container'
    );
    zss_assert_true(
        strpos($styles, '.zss-event:focus-within .zss-event-actions') !== false,
        'Expected keyboard focus to reveal timeline actions via focus-within (no pointer-events blocking)'
    );
    zss_assert_true(
        strpos($styles, '.zss-origin-tag') !== false && strpos($styles, '.zss-hold-tag') !== false && strpos($styles, '.zss-agebar') !== false,
        'Expected origin tags, dashed hold tags, and age bars in the stylesheet'
    );
    zss_assert_true(
        strpos($translations, "'snapshots.timeline.today' => 'Today'") !== false && strpos($translations, "'snapshots.timeline.today' => '今天'") !== false,
        'Expected English and Chinese entries for the today day label'
    );
});

zss_test('snapshot activity strip aggregates the selected dataset client-side', function() use ($webRoot) {
    $page = file_get_contents($webRoot . '/snapshots.php');
    $script = file_get_contents($webRoot . '/assets/js/snapshots.js');
    $styles = file_get_contents($webRoot . '/assets/css/next.css');
    $translations = file_get_contents($webRoot . '/i18n.php');

    zss_assert_true(
        strpos($page, 'id="activity-panel"') !== false && strpos($page, 'id="activity-strip"') !== false,
        'Expected the snapshots page to host the 30-day activity strip panel'
    );
    zss_assert_true(
        strpos($page, 'id="activity-panel" hidden') !== false,
        'Expected the activity panel to start hidden until a dataset provides snapshots'
    );
    zss_assert_true(
        strpos($script, 'aggregateActivityByDay') !== false && strpos($script, 'renderActivityStrip') !== false,
        'Expected snapshots.js to aggregate created_at values into daily buckets'
    );
    zss_assert_true(
        strpos($script, 'hideActivityStrip') !== false && strpos($script, 'panel.hidden = true'),
        'Expected the activity strip to hide when no dataset is selected or empty'
    );
    zss_assert_true(
        strpos($styles, '.zss-activity-col.held::after') !== false,
        'Expected held days to be marked with a diamond on top of their bar'
    );
    zss_assert_true(
        strpos($translations, "'snapshots.activity.title'") !== false && strpos($translations, "'snapshots.activity.legend'") !== false,
        'Expected i18n entries for the activity strip title and legend'
    );
});

zss_test('visualization tokens define the rail color in root dark and light scopes', function() use ($webRoot) {
    $styles = file_get_contents($webRoot . '/assets/css/next.css');

    // Parse every custom property defined inside :root {...}.
    $rootStart = strpos($styles, ':root {');
    zss_assert_true($rootStart !== false, 'Expected a :root token block in next.css');
    $rootEnd = strpos($styles, '}', $rootStart);
    $rootBlock = substr($styles, $rootStart, $rootEnd - $rootStart);
    preg_match_all('/--[a-z0-9-]+(?=\s*:)/', $rootBlock, $rootVars);
    $rootSet = array_unique($rootVars[0]);

    foreach (['dark', 'light'] as $mode) {
        // Each forced-theme block appears twice (body-scoped and html-scoped
        // selector); parse the union of custom properties across both.
        // [^{}] keeps the match from crossing into later CSS rule bodies.
        preg_match_all(
            '/\.zss-next\[data-effective-theme="' . $mode . '"\][^{}]*\{(.*?)\}/s',
            $styles,
            $matches
        );
        $blockVars = [];
        foreach ($matches[1] as $blockBody) {
            preg_match_all('/--[a-z0-9-]+(?=\s*:)/', $blockBody, $vars);
            $blockVars = array_merge($blockVars, $vars[0]);
        }
        $blockSet = array_unique($blockVars);

        $missing = array_diff($rootSet, $blockSet);
        // Existing core tokens predate this rule; only newly introduced viz
        // variables must be mirrored into both override blocks.
        $allowedExceptions = ['--font-sans'];
        $missing = array_diff($missing, $allowedExceptions);

        zss_assert_true(
            count($missing) === 0,
            "Expected all :root custom properties to also be defined in the {$mode} override block; missing: " . implode(', ', $missing)
        );
    }

    zss_assert_true(
        strpos($styles, '--zss-viz-track:') !== false &&
        substr_count($styles, '--zss-viz-track:') >= 3,
        'Expected the visualization rail color to be defined at least three times (root, dark, light)'
    );
    zss_assert_true(
        strpos($styles, '@media (prefers-reduced-motion: reduce)') !== false,
        'Expected reduced-motion users to get transitions disabled'
    );
});

zss_test('webui shares one cached datasets request per page lifecycle', function() use ($webRoot) {
    $next = file_get_contents($webRoot . '/assets/js/next.js');
    $overview = file_get_contents($webRoot . '/assets/js/overview.js');
    $datasets = file_get_contents($webRoot . '/assets/js/datasets.js');
    $snapshots = file_get_contents($webRoot . '/assets/js/snapshots.js');

    zss_assert_true(
        strpos($next, 'function fetchDatasetsShared()') !== false && strpos($next, 'function invalidateDatasetsCache()') !== false,
        'Expected next.js to expose a shared datasets cache entrypoint with an invalidation hook'
    );
    zss_assert_true(
        substr_count($next, "fetchData('../api/datasets.php')") === 1,
        'Expected next.js to issue the datasets request from exactly one place (the shared cache)'
    );
    zss_assert_true(
        strpos($next, 'preloadedData || await fetchDatasetsShared()') !== false,
        'Expected loadResourceTree to consume the shared cache when no data is preloaded'
    );

    foreach (['overview' => $overview, 'datasets' => $datasets, 'snapshots' => $snapshots] as $page => $script) {
        zss_assert_true(
            strpos($script, "../api/datasets.php") === false,
            "Expected {$page}.js to stop fetching the datasets API directly"
        );
        zss_assert_true(
            strpos($script, 'fetchDatasetsShared()') !== false,
            "Expected {$page}.js to read datasets through the shared cache"
        );
    }

    // Every mutation path must invalidate before reloading, otherwise the
    // table would replay the stale cached response after create/update.
    zss_assert_true(
        substr_count($datasets, 'invalidateDatasetsCache()') >= 2,
        'Expected datasets.js mutations to invalidate the shared cache before refreshing'
    );
});

zss_test('i18n drops unused theme inherit copy while keeping live keys', function() use ($webRoot) {
    $translations = file_get_contents($webRoot . '/i18n.php');

    zss_assert_true(
        strpos($translations, "'settings.theme.inherit.title'") === false &&
        strpos($translations, "'settings.theme.inherit.description'") === false,
        'Expected unused theme inherit title/description keys to be removed'
    );
    foreach (['current', 'dark', 'light'] as $liveKey) {
        $count = substr_count($translations, "'settings.theme.inherit.{$liveKey}'");
        zss_assert_true(
            $count === 2,
            "Expected settings.theme.inherit.{$liveKey} to stay defined once per locale (found {$count})"
        );
    }
});

zss_test('dataset edit buttons use delegated handlers instead of inline onclick json', function() use ($webRoot) {
    $script = file_get_contents($webRoot . '/assets/js/datasets.js');

    zss_assert_true(
        strpos($script, 'onclick="openEdit(') === false,
        'Expected datasets.js to stop emitting inline openEdit onclick handlers'
    );
    zss_assert_true(
        strpos($script, 'data-action="edit-dataset"') !== false && strpos($script, 'escapeHtml(JSON.stringify(ds.name))') !== false,
        'Expected dataset rows to carry JSON-encoded names in escaped data attributes'
    );
    zss_assert_true(
        strpos($script, "getElementById('datasets-table').addEventListener('click'") !== false,
        'Expected the datasets table to delegate edit clicks through a single listener'
    );
    zss_assert_true(
        strpos($script, 'name = JSON.parse(button.dataset.name') !== false,
        'Expected the delegated handler to JSON decode the dataset name like the snapshots timeline does'
    );
});

zss_test('webui motion system shares one easing token across feedback surfaces', function() use ($webRoot) {
    $styles = file_get_contents($webRoot . '/assets/css/next.css');
    $next = file_get_contents($webRoot . '/assets/js/next.js');

    zss_assert_true(
        strpos($styles, '--zss-ease-out: cubic-bezier(0.22, 1, 0.36, 1)') !== false,
        'Expected a single shared motion easing token in next.css'
    );
    zss_assert_true(
        substr_count($styles, 'var(--zss-ease-out)') >= 4,
        'Expected the shared easing token to drive toast, dialog, entrance, and growth animations'
    );
    zss_assert_true(
        strpos($styles, 'animation: zssToastIn 220ms var(--zss-ease-out) forwards;') !== false,
        'Expected the toast entrance to use the unified easing at the study timing (220ms)'
    );
    zss_assert_true(
        strpos($styles, 'transition: opacity 180ms ease;') !== false,
        'Expected the action modal overlay fade to match the study timing (180ms)'
    );
    zss_assert_true(
        strpos($styles, 'transform: translateY(8px) scale(0.95); transition: transform 200ms var(--zss-ease-out);') !== false,
        'Expected the danger dialog to enter with scale+lift on the unified easing (200ms)'
    );

    // Exit paths must not blink: toast slides/fades out before removal and
    // the dialog waits out its overlay transition before being dropped.
    zss_assert_true(
        strpos($next, "toast.classList.add('is-leaving')") !== false,
        'Expected the toast to animate out through an is-leaving state before removal'
    );
    zss_assert_true(
        preg_match('/overlay\.classList\.remove\(\'is-open\'\);\s*\n\s*window\.setTimeout\(\(\) => overlay\.remove\(\), 200\)/', $next) === 1,
        'Expected the action dialog removal to wait out the closing transition (200ms)'
    );
});

zss_test('webui reduced-motion coverage lands animations on final states', function() use ($webRoot) {
    $styles = file_get_contents($webRoot . '/assets/css/next.css');
    $overview = file_get_contents($webRoot . '/assets/js/overview.js');

    $blockStart = strpos($styles, '@media (prefers-reduced-motion: reduce)');
    zss_assert_true($blockStart !== false, 'Expected a prefers-reduced-motion override block in next.css');

    if ($blockStart !== false) {
        // Extract exactly the media block via brace matching so the coverage
        // assertions below cannot be satisfied by later rule blocks.
        $depth = 0;
        $blockEnd = null;
        for ($i = strpos($styles, '{', $blockStart); $i < strlen($styles); $i++) {
            if ($styles[$i] === '{') {
                $depth++;
            } elseif ($styles[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $blockEnd = $i;
                    break;
                }
            }
        }
        zss_assert_true($blockEnd !== null, 'Expected the reduced-motion block to be well formed');
        $reducedBlock = $blockEnd !== null ? substr($styles, $blockStart, $blockEnd - $blockStart) : '';
        foreach (['.zss-metrics-grid > *', '.zss-activity-col', '.zss-space-list .zss-bar-fill', '.zss-toast'] as $surface) {
            zss_assert_true(
                strpos($reducedBlock, $surface) !== false,
                "Expected the reduced-motion block to cover {$surface}"
            );
        }
    }

    // The rAF sweep must have an explicit terminal-value branch so the ring
    // and count-up land instantly for reduced-motion users.
    zss_assert_true(
        strpos($overview, "zssPrefersReducedMotion()") !== false &&
        preg_match('/if \(zssPrefersReducedMotion\(\)\)\s*\{\s*finalize\(\);/', $overview) === 1,
        'Expected the ring sweep rAF branch to jump straight to final values under reduced motion'
    );
});

zss_test('webui entrance and growth animations replay only once per page load', function() use ($webRoot) {
    $overview = file_get_contents($webRoot . '/assets/js/overview.js');
    $snapshots = file_get_contents($webRoot . '/assets/js/snapshots.js');

    // One-shot flags guard every animated surface against refresh replays.
    zss_assert_true(
        strpos($overview, 'content.dataset.zssEntered') !== false,
        'Expected the Overview entrance sequence to be armed once via a dataset flag'
    );
    zss_assert_true(
        strpos($overview, 'ring.dataset.zssSwept') !== false,
        'Expected the protection ring sweep to be one-shot via a dataset flag'
    );
    zss_assert_true(
        strpos($overview, 'list.dataset.zssGrown') !== false,
        'Expected the Overview space bars to be one-shot via a dataset flag'
    );
    zss_assert_true(
        strpos($snapshots, 'strip.dataset.zssColsGrown') !== false,
        'Expected the Snapshots activity columns to be one-shot via a dataset flag'
    );

    // Later renders must bypass animation entirely, not restart it.
    zss_assert_true(
        strpos($snapshots, "col.style.animation = 'none'") !== false,
        'Expected re-rendered activity columns to disable animation explicitly'
    );
    zss_assert_true(
        strpos($overview, '--zss-bar-width:') !== false,
        'Expected space bar targets to be carried in --zss-bar-width instead of inline widths'
    );
});
