<?php
$nextCurrentPage = 'settings';
require __DIR__ . '/i18n.php';
$currentLocale = zss_current_locale();
$currentLocalePreference = zss_get_locale_preference();
$languages = zss_get_available_languages();
$nextPageTitle = zss_t('settings.title');
$nextPageDescription = zss_t('settings.description');
require __DIR__ . '/layout/shell.php';
?>

<div class="zss-settings-grid">
    <section class="zss-panel zss-panel-body">
        <h2><?php echo htmlspecialchars(zss_t('settings.language.title')); ?></h2>
        <p><?php echo htmlspecialchars(zss_t('settings.language.description')); ?></p>
        <label class="zss-field"><span><?php echo htmlspecialchars(zss_t('settings.language.current')); ?></span><select id="settings-language" class="zss-select" onchange="setLocale(this.value)"><option value="auto" <?php echo $currentLocalePreference === 'auto' ? 'selected' : ''; ?>><?php echo htmlspecialchars(zss_t('settings.language.option.auto')); ?></option><?php foreach ($languages as $locale => $label): ?><option value="<?php echo htmlspecialchars($locale); ?>" <?php echo $locale === $currentLocalePreference ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></label>
        <div class="zss-meta"><strong><?php echo htmlspecialchars(zss_t('settings.language.browser')); ?>:</strong> <span id="browser-language"></span></div>
    </section>

    <section class="zss-panel zss-panel-body">
        <h2><?php echo htmlspecialchars(zss_t('settings.theme.title')); ?></h2>
        <p><?php echo htmlspecialchars(zss_t('settings.theme.description')); ?></p>
        <label class="zss-field"><span><?php echo htmlspecialchars(zss_t('settings.theme.current')); ?></span><select id="settings-theme" class="zss-select" onchange="handleSettingsThemeChange(this.value)"><option value="auto"><?php echo htmlspecialchars(zss_t('settings.theme.option.auto')); ?></option><option value="light"><?php echo htmlspecialchars(zss_t('settings.theme.option.light')); ?></option><option value="dark"><?php echo htmlspecialchars(zss_t('settings.theme.option.dark')); ?></option></select></label>
        <div class="zss-meta"><strong><?php echo htmlspecialchars(zss_t('settings.theme.preview')); ?>:</strong> <span id="effective-theme-preview"></span></div>
        <div class="zss-meta"><strong><?php echo htmlspecialchars(zss_t('settings.theme.host')); ?>:</strong> <span id="host-theme-label"></span></div>
        <div class="zss-meta zss-meta-feedback" id="settings-theme-feedback" role="status"></div>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('browser-language').textContent = navigator.language || t('common.unknown', 'Unknown');
    const hostThemeLabel = document.getElementById('host-theme-label');
    if (hostThemeLabel) {
        const hostTheme = document.body.dataset.hostTheme || 'white';
        const hostDark = document.body.dataset.hostThemeDark === '1';
        hostThemeLabel.textContent = t('settings.theme.inherit.current', 'Host theme: {theme} ({mode})', {
            theme: hostTheme,
            mode: hostDark ? t('settings.theme.inherit.dark', 'dark') : t('settings.theme.inherit.light', 'light'),
        });
    }
    if (window.applyTheme) {
        window.applyTheme(window.getStoredTheme ? window.getStoredTheme() : 'auto');
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
