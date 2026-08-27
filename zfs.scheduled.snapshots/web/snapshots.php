<?php
$nextCurrentPage = 'snapshots';
require __DIR__ . '/i18n.php';
$dataset = $_GET['dataset'] ?? '';
$nextPageTitle = zss_t('snapshots.title');
$nextPageDescription = $dataset ? zss_t('snapshots.all_snapshots_notice') : zss_t('snapshots.select_dataset');
$nextPageScript = 'assets/js/snapshots.js';
require __DIR__ . '/layout/shell.php';
?>

<div class="zss-snapshots-toolbar" data-zss-entrance="0">
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
    <section class="zss-context-strip" data-zss-entrance="1" aria-label="<?php echo htmlspecialchars(zss_t('snapshots.current_dataset')); ?>">
        <div class="zss-context-stat">
            <span><?php echo htmlspecialchars(zss_t('snapshots.current_dataset')); ?></span>
            <div class="zss-context-title-row">
                <strong><?php echo htmlspecialchars($dataset); ?></strong>
                <span class="zss-badge zss-badge-muted" id="snapshots-dataset-status"><?php echo htmlspecialchars(zss_t('common.loading')); ?></span>
            </div>
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

    <section class="zss-panel" data-zss-entrance="2">
        <div class="zss-panel-header zss-panel-header--inventory">
            <div class="zss-panel-kicker"><?php echo htmlspecialchars(zss_t('snapshots.inventory.title')); ?></div>
            <h2><?php echo htmlspecialchars(zss_t('snapshots.title')); ?></h2>
            <p class="zss-panel-caption"><?php echo htmlspecialchars(zss_t('snapshots.all_snapshots_notice')); ?> <span class="zss-panel-count" id="snapshots-inventory-count">—</span></p>
        </div>
        <div class="zss-table-wrap">
            <div id="snapshots-timeline" class="zss-timeline">
                <p class="zss-timeline-message"><?php echo htmlspecialchars(zss_t('common.loading')); ?></p>
            </div>
        </div>
    </section>

    <section class="zss-panel" id="activity-panel" hidden>
        <div class="zss-panel-header">
            <h2><?php echo htmlspecialchars(zss_t('snapshots.activity.title')); ?></h2>
        </div>
        <div class="zss-panel-body">
            <div class="zss-activity-strip" id="activity-strip"></div>
            <div class="zss-activity-axis"><span id="activity-axis-start"></span><span id="activity-axis-end"></span></div>
            <p class="zss-activity-legend" id="activity-legend"></p>
        </div>
    </section>
<?php else: ?>
    <section class="zss-panel zss-empty-state" data-zss-entrance="1">
        <div class="zss-panel-header zss-panel-header--inventory">
            <div class="zss-panel-kicker"><?php echo htmlspecialchars(zss_t('snapshots.inventory.title')); ?></div>
            <h2><?php echo htmlspecialchars(zss_t('snapshots.title')); ?></h2>
            <p class="zss-panel-caption"><?php echo htmlspecialchars(zss_t('snapshots.no_dataset_hint')); ?></p>
        </div>
    </section>

    <section class="zss-panel" data-zss-entrance="2">
        <div class="zss-panel-header zss-panel-header--inventory">
            <div class="zss-panel-kicker"><?php echo htmlspecialchars(zss_t('tree.title')); ?></div>
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
