<?php
require_once __DIR__ . '/../i18n.php';

$currentLocale = zss_current_locale();
$currentLocalePreference = zss_get_locale_preference();
$availableLanguages = zss_get_available_languages();
$currentTranslations = zss_get_locale_translations($currentLocale);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($currentLocale); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(zss_t('app.title')); ?> - <?php echo htmlspecialchars(zss_t('app.webui')); ?></title>
    <script>
        (function() {
            const theme = localStorage.getItem('zss_theme') || 'auto';
            const effectiveTheme = theme === 'dark' || theme === 'light'
                ? theme
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
            document.documentElement.dataset.effectiveTheme = effectiveTheme;
            document.documentElement.style.colorScheme = effectiveTheme;
        })();
    </script>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body data-locale="<?php echo htmlspecialchars($currentLocale); ?>" data-theme="auto">
    <script>
        window.ZSS_LOCALE = <?php echo json_encode($currentLocale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ZSS_LOCALE_PREFERENCE = <?php echo json_encode($currentLocalePreference, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ZSS_TRANSLATIONS = <?php echo json_encode($currentTranslations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ZSS_THEME = localStorage.getItem('zss_theme') || 'auto';
        document.body.dataset.locale = window.ZSS_LOCALE;
        document.body.dataset.theme = window.ZSS_THEME;
        document.body.dataset.effectiveTheme = document.documentElement.dataset.effectiveTheme || 'light';
    </script>
    <div class="container">
        <header class="header">
            <div class="header-top">
                <h1><?php echo htmlspecialchars(zss_t('app.title')); ?></h1>
                <div class="toolbar">
                    <label class="toolbar-label" for="global-language-switcher"><?php echo htmlspecialchars(zss_t('lang.label')); ?></label>
                    <select id="global-language-switcher" class="toolbar-select" onchange="setLocale(this.value)">
                        <option value="auto" <?php echo $currentLocalePreference === 'auto' ? 'selected' : ''; ?>><?php echo htmlspecialchars(zss_t('settings.language.option.auto')); ?></option>
                        <?php foreach ($availableLanguages as $locale => $label): ?>
                            <option value="<?php echo htmlspecialchars($locale); ?>" <?php echo $locale === $currentLocalePreference ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <nav class="nav">
                <a href="index.php" class="nav-link <?php echo ($currentPage ?? 'index') === 'index' ? 'active' : ''; ?>"><?php echo htmlspecialchars(zss_t('nav.overview')); ?></a>
                <a href="datasets.php" class="nav-link <?php echo ($currentPage ?? '') === 'datasets' ? 'active' : ''; ?>"><?php echo htmlspecialchars(zss_t('nav.datasets')); ?></a>
                <a href="snapshots.php" class="nav-link <?php echo ($currentPage ?? '') === 'snapshots' ? 'active' : ''; ?>"><?php echo htmlspecialchars(zss_t('nav.snapshots')); ?></a>
                <a href="logs.php" class="nav-link <?php echo ($currentPage ?? '') === 'logs' ? 'active' : ''; ?>"><?php echo htmlspecialchars(zss_t('nav.logs')); ?></a>
                <a href="settings.php" class="nav-link <?php echo ($currentPage ?? '') === 'settings' ? 'active' : ''; ?>"><?php echo htmlspecialchars(zss_t('nav.settings')); ?></a>
            </nav>
        </header>
        <main class="content">
