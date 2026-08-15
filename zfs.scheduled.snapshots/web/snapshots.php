<?php
$nextCurrentPage = 'snapshots';
require __DIR__ . '/i18n.php';
$dataset = $_GET['dataset'] ?? '';
$nextPageTitle = zss_t('snapshots.title');
$nextPageDescription = $dataset ? zss_t('snapshots.all_snapshots_notice') : zss_t('snapshots.select_dataset');
$nextPageScript = 'assets/js/snapshots.js';
require __DIR__ . '/layout/shell.php';
?>

<div class="zss-snapshots-toolbar">
    <label class="zss-dataset-select-field">
        <span><?php echo htmlspecialchars(zss_t('snapshots.selector_label')); ?></span>
        <select id="snapshot-dataset-select" class="zss-select" aria-label="<?php echo htmlspecialchars(zss_t('snapshots.selector_label')); ?>">
            <option value=""><?php echo htmlspecialchars(zss_t('snapshots.selector_placeholder')); ?></option>
        </select>
    </label>
    <?php if ($dataset): ?>
        <div class="zss-toolbar-actions">
            <a class="zss-btn zss-btn-secondary" href="<?php echo htmlspecialchars(withLang('snapshots.php')); ?>">&larr; <?php echo htmlspecialchars(zss_t('snapshots.back_all')); ?></a>
            <button class="zss-btn zss-btn-primary" type="button" onclick="createSnapshot()"><?php echo htmlspecialchars(zss_t('snapshots.create')); ?></button>
        </div>
    <?php endif; ?>
</div>

<?php if ($dataset): ?>
    <section class="zss-context-strip" aria-label="<?php echo htmlspecialchars(zss_t('snapshots.current_dataset')); ?>">
        <div class="zss-context-stat">
            <span><?php echo htmlspecialchars(zss_t('snapshots.current_dataset')); ?></span>
            <strong><?php echo htmlspecialchars($dataset); ?></strong>
            <span class="zss-badge zss-badge-muted" id="snapshots-dataset-status"><?php echo htmlspecialchars(zss_t('common.loading')); ?></span>
            <div class="zss-dataset-crumb" id="snapshots-dataset-crumb"></div>
        </div>
        <div class="zss-context-stat">
            <span><?php echo htmlspecialchars(zss_t('snapshots.context.retention')); ?></span>
            <strong id="snapshots-retention">-</strong>
        </div>
        <div class="zss-context-stat">
            <span><?php echo htmlspecialchars(zss_t('snapshots.context.protected')); ?></span>
            <strong id="snapshots-protected-count">-</strong>
        </div>
    </section>

    <section class="zss-panel">
        <div class="zss-panel-header">
            <h2><?php echo htmlspecialchars(zss_t('snapshots.title')); ?></h2>
            <p><?php echo htmlspecialchars(zss_t('snapshots.all_snapshots_notice')); ?></p>
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
<?php else: ?>
    <section class="zss-panel zss-empty-state">
        <h2><?php echo htmlspecialchars(zss_t('snapshots.title')); ?></h2>
        <p><?php echo htmlspecialchars(zss_t('snapshots.no_dataset_hint')); ?></p>
    </section>

    <section class="zss-panel">
        <div class="zss-panel-header">
            <h2><?php echo htmlspecialchars(zss_t('overview.dataset_status')); ?></h2>
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
<?php endif; ?>

<script>
const dataset = <?php echo json_encode($dataset); ?>;
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
