<?php
$nextCurrentPage = 'snapshots';
require __DIR__ . '/../i18n.php';
$nextPageTitle = zss_t('snapshots.title');
$nextPageDescription = zss_t('snapshots.all_snapshots_notice');
require __DIR__ . '/layout/shell.php';
?>

<section class="zss-panel zss-empty-state">
    <h2><?php echo htmlspecialchars(zss_t('snapshots.title')); ?></h2>
    <p>New UI page shell is ready. Snapshot operations stay on the existing UI until this page is visually migrated.</p>
    <a class="zss-btn zss-btn-primary" href="<?php echo htmlspecialchars(withLang('../snapshots-list.php')); ?>"><?php echo htmlspecialchars(zss_t('nav.snapshots')); ?> · Classic UI</a>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
