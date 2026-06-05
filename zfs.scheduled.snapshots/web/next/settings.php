<?php
$nextCurrentPage = 'settings';
require __DIR__ . '/../i18n.php';
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
        <p class="zss-muted-text">New UI currently uses its dedicated dark dashboard theme. Classic UI theme and accent settings remain available in Classic UI.</p>
        <a class="zss-btn zss-btn-secondary" href="<?php echo htmlspecialchars(withLang('../settings.php')); ?>"><?php echo htmlspecialchars(zss_t('nav.settings')); ?> · Classic UI</a>
    </section>

    <section class="zss-panel zss-panel-body">
        <h2>UI Mode</h2>
        <p>New UI and Classic UI are both kept available during the first redesign version. This page does not introduce new settings or APIs.</p>
        <div class="zss-action-row"><a class="zss-btn zss-btn-primary" href="<?php echo htmlspecialchars(withLang('index.php')); ?>">New UI</a><a class="zss-btn zss-btn-secondary" href="<?php echo htmlspecialchars(withLang('../index.php')); ?>">Classic UI</a></div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('browser-language').textContent = navigator.language || t('common.unknown', 'Unknown');
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
