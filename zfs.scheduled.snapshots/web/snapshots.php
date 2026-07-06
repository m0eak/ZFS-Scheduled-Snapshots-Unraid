<?php
$nextCurrentPage = 'snapshots';
require __DIR__ . '/i18n.php';
$dataset = $_GET['dataset'] ?? '';
$nextPageTitle = zss_t('snapshots.title');
$nextPageDescription = $dataset ? zss_t('snapshots.all_snapshots_notice') : zss_t('snapshots.select_dataset');
$nextPageScript = 'assets/js/snapshots.js';
require __DIR__ . '/layout/shell.php';
?>

<div class="zss-page-actions">
    <?php if ($dataset): ?>
        <a class="zss-btn zss-btn-secondary" href="<?php echo htmlspecialchars(withLang('snapshots.php')); ?>">&larr; <?php echo htmlspecialchars(zss_t('snapshots.back_all')); ?></a>
        <button class="zss-btn zss-btn-primary" type="button" onclick="createSnapshot()"><?php echo htmlspecialchars(zss_t('snapshots.create')); ?></button>
    <?php else: ?>

    <?php endif; ?>
</div>

<?php if ($dataset): ?>
    <section class="zss-panel zss-context-panel">
        <div>
            <p><?php echo htmlspecialchars(zss_t('snapshots.current_dataset')); ?></p>
            <strong><?php echo htmlspecialchars($dataset); ?></strong>
        </div>
        <span class="zss-badge zss-badge-info"><?php echo htmlspecialchars(zss_t('snapshots.source')); ?></span>
    </section>
<?php endif; ?>

<section class="zss-panel">
    <div class="zss-panel-header">
        <h2><?php echo htmlspecialchars($dataset ? zss_t('snapshots.title') : zss_t('overview.dataset_status')); ?></h2>
        <?php if ($dataset): ?>
            <p><?php echo htmlspecialchars(zss_t('snapshots.all_snapshots_notice')); ?></p>
        <?php endif; ?>
    </div>
    <div class="zss-table-wrap">
        <table class="zss-table">
            <thead>
                <tr id="snapshots-table-head"></tr>
            </thead>
            <tbody id="snapshots-table">
                <tr><td class="zss-table-message"><?php echo htmlspecialchars(zss_t('common.loading')); ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<script>
const dataset = <?php echo json_encode($dataset); ?>;
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
