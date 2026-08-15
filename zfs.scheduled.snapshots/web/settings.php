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
        <div class="zss-field"><span><?php echo htmlspecialchars(zss_t('settings.accent.title')); ?></span><div class="zss-accent-options" id="accent-options"><?php foreach ([ 'blue' => '#3b82f6', 'amber' => '#f59e0b', 'mint' => '#10b981', 'sky' => '#0ea5e9', 'lavender' => '#8b5cf6', 'rose' => '#f43f5e' ] as $accent => $color): ?><button type="button" class="zss-accent-choice" data-accent-choice="<?php echo htmlspecialchars($accent); ?>" onclick="handleSettingsAccentChange('<?php echo htmlspecialchars($accent); ?>')"><span class="zss-accent-swatch" style="--accent-color: <?php echo htmlspecialchars($color); ?>"></span><?php echo htmlspecialchars(zss_t('settings.accent.option.' . $accent)); ?></button><?php endforeach; ?></div></div>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('browser-language').textContent = navigator.language || t('common.unknown', 'Unknown');
    const themeSelect = document.getElementById('settings-theme');
    themeSelect.value = window.ZSS_THEME || 'auto';
    updateSettingsThemePreview(getEffectiveTheme(window.ZSS_THEME || 'auto'));
    updateAccentControls(window.ZSS_ACCENT || 'blue');
});
function updateSettingsThemePreview(theme) {
    document.getElementById('effective-theme-preview').textContent = t(theme === 'dark' ? 'settings.theme.option.dark' : 'settings.theme.option.light', theme);
}
function handleSettingsThemeChange(theme) {
    const effectiveTheme = saveThemePreference(theme);
    updateSettingsThemePreview(effectiveTheme);
}
function handleSettingsAccentChange(accent) {
    saveAccentPreference(accent);
}
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
