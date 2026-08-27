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

<section class="zss-context-strip zss-context-strip--env" data-zss-entrance="0" aria-label="<?php echo htmlspecialchars(zss_t('settings.context_strip.title')); ?>">
    <div class="zss-context-stat">
        <span><?php echo htmlspecialchars(zss_t('settings.context_strip.browser_language')); ?></span>
        <strong id="settings-env-browser-language"><?php echo htmlspecialchars(zss_current_locale() === 'zh-CN' ? '简体中文' : 'English'); ?></strong>
    </div>
    <div class="zss-context-stat">
        <span><?php echo htmlspecialchars(zss_t('settings.context_strip.effective_theme')); ?></span>
        <strong id="settings-env-effective-theme">auto</strong>
    </div>
    <div class="zss-context-stat">
        <span><?php echo htmlspecialchars(zss_t('settings.context_strip.host_theme')); ?></span>
        <strong id="settings-env-host-theme"><?php echo htmlspecialchars(isset($_COOKIE['zss_theme']) ? $_COOKIE['zss_theme'] : 'auto'); ?></strong>
    </div>
</section>

<div class="zss-settings-grid" data-zss-entrance="1">
    <section class="zss-panel zss-panel-body">
        <div class="zss-panel-header zss-panel-header--inventory">
            <div class="zss-panel-kicker"><?php echo htmlspecialchars(zss_t('settings.language.title')); ?></div>
            <h2><?php echo htmlspecialchars(zss_t('settings.language.title')); ?></h2>
            <p class="zss-panel-caption"><?php echo htmlspecialchars(zss_t('settings.language.description')); ?></p>
        </div>
        <label class="zss-field"><span><?php echo htmlspecialchars(zss_t('settings.language.current')); ?></span><select id="settings-language" class="zss-select" onchange="setLocale(this.value)"><option value="auto" <?php echo $currentLocalePreference === 'auto' ? 'selected' : ''; ?>><?php echo htmlspecialchars(zss_t('settings.language.option.auto')); ?></option><?php foreach ($languages as $locale => $label): ?><option value="<?php echo htmlspecialchars($locale); ?>" <?php echo $locale === $currentLocalePreference ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></label>
        <div class="zss-meta"><strong><?php echo htmlspecialchars(zss_t('settings.language.browser')); ?>:</strong> <span id="browser-language"></span></div>
    </section>

    <section class="zss-panel zss-panel-body">
        <div class="zss-panel-header zss-panel-header--inventory">
            <div class="zss-panel-kicker"><?php echo htmlspecialchars(zss_t('settings.theme.title')); ?></div>
            <h2><?php echo htmlspecialchars(zss_t('settings.theme.title')); ?></h2>
            <p class="zss-panel-caption"><?php echo htmlspecialchars(zss_t('settings.theme.description')); ?></p>
        </div>
        <label class="zss-field"><span><?php echo htmlspecialchars(zss_t('settings.theme.current')); ?></span><select id="settings-theme" class="zss-select" onchange="handleSettingsThemeChange(this.value)"><option value="auto"><?php echo htmlspecialchars(zss_t('settings.theme.option.auto')); ?></option><option value="light"><?php echo htmlspecialchars(zss_t('settings.theme.option.light')); ?></option><option value="dark"><?php echo htmlspecialchars(zss_t('settings.theme.option.dark')); ?></option></select></label>
        <div class="zss-meta"><strong><?php echo htmlspecialchars(zss_t('settings.theme.preview')); ?>:</strong> <span id="effective-theme-preview"></span></div>
        <div class="zss-meta"><strong><?php echo htmlspecialchars(zss_t('settings.theme.host')); ?>:</strong> <span id="host-theme-label"></span></div>
        <div class="zss-meta zss-meta-feedback" id="settings-theme-feedback" role="status"></div>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var browserLanguage = navigator.language || t('common.unknown', 'Unknown');
    var browserLanguageEl = document.getElementById('browser-language');
    if (browserLanguageEl) browserLanguageEl.textContent = browserLanguage;

    var envBrowserLanguage = document.getElementById('settings-env-browser-language');
    if (envBrowserLanguage) envBrowserLanguage.textContent = browserLanguage;

    var hostTheme = document.body.dataset.hostTheme || 'white';
    var hostDark = document.body.dataset.hostThemeDark === '1';
    var hostLabel = t('settings.theme.inherit.current', 'Host theme: {theme} ({mode})', {
        theme: hostTheme,
        mode: hostDark ? t('settings.theme.inherit.dark', 'dark') : t('settings.theme.inherit.light', 'light'),
    });
    var hostThemeLabelEl = document.getElementById('host-theme-label');
    if (hostThemeLabelEl) hostThemeLabelEl.textContent = hostLabel;

    var envHostTheme = document.getElementById('settings-env-host-theme');
    if (envHostTheme) envHostTheme.textContent = hostLabel;

    if (window.applyTheme) {
        var stored = window.getStoredTheme ? window.getStoredTheme() : 'auto';
        window.applyTheme(stored);
        var envEffectiveTheme = document.getElementById('settings-env-effective-theme');
        if (envEffectiveTheme) {
            var effective = window.getEffectiveTheme ? window.getEffectiveTheme() : (stored === 'auto' ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : stored);
            envEffectiveTheme.textContent = effective;
        }
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
