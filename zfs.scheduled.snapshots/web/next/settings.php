<?php
$nextCurrentPage = 'settings';
require __DIR__ . '/../i18n.php';
$nextPageTitle = zss_t('settings.title');
$nextPageDescription = zss_t('app.webui');
require __DIR__ . '/layout/shell.php';
?>

<section class="zss-panel zss-empty-state">
    <h2><?php echo htmlspecialchars(zss_t('settings.title')); ?></h2>
    <p>New UI page shell is ready. Settings remain unchanged and can be managed in Classic UI for now.</p>
    <a class="zss-btn zss-btn-primary" href="<?php echo htmlspecialchars(withLang('../settings.php')); ?>"><?php echo htmlspecialchars(zss_t('nav.settings')); ?> · Classic UI</a>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
