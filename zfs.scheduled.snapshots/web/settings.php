<?php
$currentPage = 'settings';
require_once __DIR__ . '/layout/header.php';
$currentLocale = zss_current_locale();
$languages = zss_get_available_languages();
?>

<h2><?php echo htmlspecialchars(zss_t('settings.title')); ?></h2>
<p class="page-description"><?php echo htmlspecialchars(zss_t('settings.description')); ?></p>

<div class="settings-grid">
    <section class="settings-card">
        <h3><?php echo htmlspecialchars(zss_t('settings.language.title')); ?></h3>
        <p class="settings-help"><?php echo htmlspecialchars(zss_t('settings.language.description')); ?></p>

        <div class="form-row">
            <label class="form-label" for="settings-language"><?php echo htmlspecialchars(zss_t('settings.language.current')); ?></label>
            <select id="settings-language" class="form-select" onchange="saveLanguagePreference(this.value)">
                <?php foreach ($languages as $locale => $label): ?>
                    <option value="<?php echo htmlspecialchars($locale); ?>" <?php echo $locale === $currentLocale ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="settings-meta">
            <div><strong><?php echo htmlspecialchars(zss_t('settings.language.browser')); ?>:</strong> <span id="browser-language"></span></div>
            <div id="settings-language-feedback" class="settings-feedback"></div>
        </div>
    </section>

    <section class="settings-card">
        <h3><?php echo htmlspecialchars(zss_t('settings.theme.title')); ?></h3>
        <p class="settings-help"><?php echo htmlspecialchars(zss_t('settings.theme.description')); ?></p>

        <div class="form-row">
            <label class="form-label" for="settings-theme"><?php echo htmlspecialchars(zss_t('settings.theme.current')); ?></label>
            <select id="settings-theme" class="form-select" onchange="handleThemeChange(this.value)">
                <option value="auto"><?php echo htmlspecialchars(zss_t('settings.theme.option.auto')); ?></option>
                <option value="light"><?php echo htmlspecialchars(zss_t('settings.theme.option.light')); ?></option>
                <option value="dark"><?php echo htmlspecialchars(zss_t('settings.theme.option.dark')); ?></option>
            </select>
        </div>

        <div class="settings-meta">
            <div><strong><?php echo htmlspecialchars(zss_t('settings.theme.preview')); ?>:</strong> <span id="effective-theme-preview"></span></div>
            <div id="settings-theme-feedback" class="settings-feedback"></div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('browser-language').textContent = navigator.language || 'unknown';

    const themeSelect = document.getElementById('settings-theme');
    themeSelect.value = window.ZSS_THEME || 'auto';
    updateThemePreview(getEffectiveTheme(window.ZSS_THEME || 'auto'));
});

function updateThemePreview(theme) {
    const preview = document.getElementById('effective-theme-preview');
    const key = theme === 'dark' ? 'settings.theme.option.dark' : 'settings.theme.option.light';
    preview.textContent = t(key, theme);
}

function saveLanguagePreference(locale) {
    setLocale(locale, {
        onSaved: function() {
            const feedback = document.getElementById('settings-language-feedback');
            feedback.textContent = t('settings.language.saved', 'Saved');
        }
    });
}

function handleThemeChange(theme) {
    saveThemePreference(theme, {
        onSaved: function(_theme, effectiveTheme) {
            updateThemePreview(effectiveTheme);
            const feedback = document.getElementById('settings-theme-feedback');
            feedback.textContent = t('settings.theme.saved', 'Saved');
        }
    });
}
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
