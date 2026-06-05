<?php
$nextCurrentPage = 'datasets';
require __DIR__ . '/../i18n.php';
$nextPageTitle = zss_t('datasets.title');
$nextPageDescription = zss_t('app.webui');
require __DIR__ . '/layout/shell.php';
?>

<section class="zss-panel zss-empty-state">
    <h2><?php echo htmlspecialchars(zss_t('datasets.title')); ?></h2>
    <p>New UI page shell is ready. This first pass keeps existing features unchanged while the visual layer is migrated.</p>
    <a class="zss-btn zss-btn-primary" href="<?php echo htmlspecialchars(withLang('../datasets.php')); ?>"><?php echo htmlspecialchars(zss_t('nav.datasets')); ?> · Classic UI</a>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
