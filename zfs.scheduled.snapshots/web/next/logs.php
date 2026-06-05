<?php
$nextCurrentPage = 'logs';
require __DIR__ . '/../i18n.php';
$nextPageTitle = zss_t('logs.title');
$nextPageDescription = zss_t('app.webui');
require __DIR__ . '/layout/shell.php';
?>

<section class="zss-panel zss-empty-state">
    <h2><?php echo htmlspecialchars(zss_t('logs.title')); ?></h2>
    <p>New UI page shell is ready. Log display will be visually migrated without changing log APIs.</p>
    <a class="zss-btn zss-btn-primary" href="<?php echo htmlspecialchars(withLang('../logs.php')); ?>"><?php echo htmlspecialchars(zss_t('nav.logs')); ?> · Classic UI</a>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
