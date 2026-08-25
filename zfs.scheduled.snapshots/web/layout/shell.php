<?php
require_once __DIR__ . '/../i18n.php';
require_once __DIR__ . '/../../include/validation.php';
require_once __DIR__ . '/icons.php';

$currentLocale = zss_current_locale();
$currentLocalePreference = zss_get_locale_preference();
$availableLanguages = zss_get_available_languages();
$currentTranslations = zss_get_locale_translations($currentLocale);
$nextCsrfToken = zss_csrf_token();
$nextCurrentPage = $nextCurrentPage ?? 'overview';
$nextPageTitle = $nextPageTitle ?? zss_t('overview.title');
$nextPageDescription = $nextPageDescription ?? zss_t('app.webui');

/**
 * Resolve the active Unraid Dynamix theme without applying a plugin-owned
 * colour scheme. This mirrors Dynamix's theme-name convention and accepts
 * installed custom themes when a matching stylesheet exists.
 *
 * @return array{name: string, dark: bool}
 */
function zss_unraid_theme(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $theme = 'white';
    $configPath = '/boot/config/plugins/dynamix/dynamix.cfg';
    if (is_file($configPath)) {
        $cfg = @parse_ini_file($configPath, true);
        if (is_array($cfg) && isset($cfg['display']) && is_array($cfg['display'])) {
            $display = $cfg['display'];
            $candidate = strtok((string) ($display['theme'] ?? ''), '-');
            if (is_string($candidate) && preg_match('/^[A-Za-z0-9_-]+$/', $candidate) === 1) {
                $themePath = '/usr/local/emhttp/webGui/styles/themes/' . $candidate . '.css';
                if (is_file($themePath)) {
                    $theme = $candidate;
                }
            }
        }
    }

    $dark = in_array($theme, ['black', 'gray'], true);

    $cached = [
        'name' => $theme,
        'dark' => $dark,
    ];

    return $cached;
}

$zssTheme = zss_unraid_theme();

if (!function_exists('zss_asset_url')) {
    function zss_asset_url($path) {
        $relativePath = ltrim((string)$path, '/');
        $absolutePath = realpath(__DIR__ . '/../' . $relativePath);
        $webRoot = realpath(__DIR__ . '/..');
        $version = null;

        if ($absolutePath !== false && $webRoot !== false && strpos($absolutePath, $webRoot . DIRECTORY_SEPARATOR) === 0) {
            $mtime = filemtime($absolutePath);
            if ($mtime !== false) {
                $version = (string)$mtime;
            }
        }

        if ($version === null) {
            return $relativePath;
        }

        $separator = strpos($relativePath, '?') === false ? '?' : '&';
        return $relativePath . $separator . 'v=' . rawurlencode($version);
    }
}

$nextNavItems = [
    'overview' => ['href' => 'index.php', 'label' => zss_t('nav.overview'), 'icon' => 'overview'],
    'datasets' => ['href' => 'datasets.php', 'label' => zss_t('nav.datasets'), 'icon' => 'datasets'],
    'snapshots' => ['href' => 'snapshots.php', 'label' => zss_t('nav.snapshots'), 'icon' => 'snapshots'],
    'logs' => ['href' => 'logs.php', 'label' => zss_t('nav.logs'), 'icon' => 'logs'],
    'settings' => ['href' => 'settings.php', 'label' => zss_t('nav.settings'), 'icon' => 'settings'],
];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($currentLocale); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nextPageTitle); ?> - <?php echo htmlspecialchars(zss_t('app.title')); ?></title>
    <script>
        // Anti-flash theme bootstrap: read and validate the per-browser theme
        // preference before any stylesheet applies, so the first paint already
        // matches the chosen light/dark mode. Values outside the strict
        // auto/light/dark whitelist fall back to auto.
        (function() {
            var theme = 'auto';
            try {
                var stored = localStorage.getItem('zss_theme');
                theme = (stored === 'auto' || stored === 'light' || stored === 'dark') ? stored : 'auto';
            } catch (error) {}
            var effective = theme === 'light' || theme === 'dark'
                ? theme
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            var root = document.documentElement;
            root.dataset.theme = theme;
            root.dataset.effectiveTheme = effective;
            root.style.colorScheme = effective;
            window.ZSS_THEME = theme;
            window.ZSS_EFFECTIVE_THEME = effective;
        })();
    </script>
    <!--
        Inherit the host Unraid WebGUI theme (including Dynamix night mode).
        These two official stylesheets only define CSS custom properties
        (color palette + active theme variables); the page's own styles below
        consume those variables and fall back to neutral values when the files
        are unavailable (e.g. local development).
    -->
    <link rel="stylesheet" href="/webGui/styles/default-color-palette.css">
    <link rel="stylesheet" href="/webGui/styles/themes/<?php echo htmlspecialchars($zssTheme['name']); ?>.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(zss_asset_url('assets/css/next.css')); ?>">
</head>
<!--
    Theme attribute split: data-host-theme / data-host-theme-dark report the
    active Unraid Dynamix theme (read-only host info), while data-theme and
    data-effective-theme carry the per-browser user preference and its resolved
    light/dark mode (applied by the anti-flash script and next.js).
-->
<body class="zss-next" data-locale="<?php echo htmlspecialchars($currentLocale); ?>" data-host-theme="<?php echo htmlspecialchars($zssTheme['name']); ?>" data-host-theme-dark="<?php echo $zssTheme['dark'] ? '1' : '0'; ?>">
    <script>
        window.ZSS_LOCALE = <?php echo json_encode($currentLocale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ZSS_LOCALE_PREFERENCE = <?php echo json_encode($currentLocalePreference, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ZSS_TRANSLATIONS = <?php echo json_encode($currentTranslations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ZSS_CSRF = <?php echo json_encode($nextCsrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <div class="zss-app-shell">
        <aside class="zss-sidebar">
            <a class="zss-brand" href="<?php echo htmlspecialchars(withLang('index.php')); ?>">
                <span class="zss-brand-mark">Z</span>
                <span class="zss-brand-text"><?php echo htmlspecialchars(zss_t('app.title')); ?></span>
            </a>
            <nav class="zss-sidebar-nav" aria-label="<?php echo htmlspecialchars(zss_t('app.title')); ?>">
                <?php foreach ($nextNavItems as $key => $item): ?>
                    <a class="zss-nav-item <?php echo $nextCurrentPage === $key ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(withLang($item['href'])); ?>">
                        <span class="zss-nav-icon"><?php echo zss_next_icon($item['icon']); ?></span>
                        <span><?php echo htmlspecialchars($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <section class="zss-resource-nav" aria-labelledby="zss-resource-heading">
                <h2 id="zss-resource-heading" class="zss-resource-heading"><?php echo htmlspecialchars(zss_t('tree.title')); ?></h2>
                <nav id="zss-resource-tree" class="zss-tree" aria-label="<?php echo htmlspecialchars(zss_t('tree.title')); ?>">
                    <div class="zss-tree-message" role="status"><?php echo htmlspecialchars(zss_t('tree.loading')); ?></div>
                </nav>
            </section>
            <div class="zss-sidebar-footer">
                <div class="zss-service-pill"><span class="zss-dot"></span><?php echo htmlspecialchars(zss_t('common.enabled')); ?></div>
            </div>
        </aside>
        <main class="zss-main">
            <header class="zss-topbar">
                <div>
                    <h1><?php echo htmlspecialchars($nextPageTitle); ?></h1>
                    <p><?php echo htmlspecialchars($nextPageDescription); ?></p>
                </div>
                <div class="zss-topbar-actions">
                    <button id="global-theme-toggle" class="zss-icon-action" type="button" onclick="cycleThemePreference()" aria-label="<?php echo htmlspecialchars(zss_t('settings.theme.toggle')); ?>" title="<?php echo htmlspecialchars(zss_t('settings.theme.toggle')); ?>">
                        <span id="global-theme-toggle-icon" aria-hidden="true"><?php echo zss_next_icon('sun'); ?></span>
                    </button>
                    <select id="global-language-switcher" class="zss-select" onchange="setLocale(this.value)">
                        <option value="auto" <?php echo $currentLocalePreference === 'auto' ? 'selected' : ''; ?>><?php echo htmlspecialchars(zss_t('settings.language.option.auto')); ?></option>
                        <?php foreach ($availableLanguages as $locale => $label): ?>
                            <option value="<?php echo htmlspecialchars($locale); ?>" <?php echo $locale === $currentLocalePreference ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </header>
            <!-- data-zss-entered arms the shared page entrance in the initial
                 HTML (before first paint), so it plays exactly once per full
                 page load and is decoupled from async API data loading. -->
            <section class="zss-content" data-zss-entered="1">
