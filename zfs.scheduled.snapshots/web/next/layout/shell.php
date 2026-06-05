<?php
require_once __DIR__ . '/../../i18n.php';

$currentLocale = zss_current_locale();
$currentLocalePreference = zss_get_locale_preference();
$availableLanguages = zss_get_available_languages();
$currentTranslations = zss_get_locale_translations($currentLocale);
$nextCurrentPage = $nextCurrentPage ?? 'overview';
$nextPageTitle = $nextPageTitle ?? zss_t('overview.title');
$nextPageDescription = $nextPageDescription ?? zss_t('app.webui');

$nextNavItems = [
    'overview' => ['href' => 'index.php', 'label' => zss_t('nav.overview'), 'icon' => '⌂'],
    'datasets' => ['href' => 'datasets.php', 'label' => zss_t('nav.datasets'), 'icon' => '▣'],
    'snapshots' => ['href' => 'snapshots.php', 'label' => zss_t('nav.snapshots'), 'icon' => '◉'],
    'logs' => ['href' => 'logs.php', 'label' => zss_t('nav.logs'), 'icon' => '☰'],
    'settings' => ['href' => 'settings.php', 'label' => zss_t('nav.settings'), 'icon' => '⚙'],
];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($currentLocale); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nextPageTitle); ?> - <?php echo htmlspecialchars(zss_t('app.title')); ?></title>
    <script>
        (function() {
            const theme = localStorage.getItem('zss_theme') || 'auto';
            const effectiveTheme = theme === 'dark' || theme === 'light'
                ? theme
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
            document.documentElement.dataset.effectiveTheme = effectiveTheme;
            document.documentElement.style.colorScheme = 'dark';
        })();
    </script>
    <link rel="stylesheet" href="assets/css/next.css">
</head>
<body class="zss-next" data-locale="<?php echo htmlspecialchars($currentLocale); ?>">
    <script>
        window.ZSS_LOCALE = <?php echo json_encode($currentLocale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ZSS_LOCALE_PREFERENCE = <?php echo json_encode($currentLocalePreference, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ZSS_TRANSLATIONS = <?php echo json_encode($currentTranslations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
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
                        <span class="zss-nav-icon"><?php echo htmlspecialchars($item['icon']); ?></span>
                        <span><?php echo htmlspecialchars($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="zss-sidebar-footer">
                <div class="zss-service-pill"><span class="zss-dot"></span><?php echo htmlspecialchars(zss_t('common.enabled')); ?></div>
                <a class="zss-classic-link" href="<?php echo htmlspecialchars(withLang('../index.php')); ?>">Classic UI</a>
            </div>
        </aside>
        <main class="zss-main">
            <header class="zss-topbar">
                <div>
                    <h1><?php echo htmlspecialchars($nextPageTitle); ?></h1>
                    <p><?php echo htmlspecialchars($nextPageDescription); ?></p>
                </div>
                <div class="zss-topbar-actions">
                    <select class="zss-select" onchange="setLocale(this.value)">
                        <option value="auto" <?php echo $currentLocalePreference === 'auto' ? 'selected' : ''; ?>><?php echo htmlspecialchars(zss_t('settings.language.option.auto')); ?></option>
                        <?php foreach ($availableLanguages as $locale => $label): ?>
                            <option value="<?php echo htmlspecialchars($locale); ?>" <?php echo $locale === $currentLocalePreference ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a class="zss-btn zss-btn-secondary" href="<?php echo htmlspecialchars(withLang('../index.php')); ?>">Classic UI</a>
                </div>
            </header>
            <section class="zss-content">
