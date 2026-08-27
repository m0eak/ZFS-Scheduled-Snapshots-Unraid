<?php
$nextCurrentPage = 'overview';
require __DIR__ . '/i18n.php';
$nextPageTitle = zss_t('overview.title');
$nextPageDescription = zss_t('overview.dataset_status');
$nextPageScript = 'assets/js/overview.js';
require __DIR__ . '/layout/shell.php';
?>

<section class="zss-context-strip zss-context-strip--stats" data-zss-entrance="0" aria-label="<?php echo htmlspecialchars(zss_t('overview.title')); ?>">
    <div class="zss-context-stat">
        <span><?php echo htmlspecialchars(zss_t('overview.stats.dataset_count')); ?></span>
        <strong id="dataset-count">-</strong>
    </div>
    <div class="zss-context-stat">
        <span><?php echo htmlspecialchars(zss_t('overview.stats.enabled_count')); ?></span>
        <strong id="enabled-count">-</strong>
    </div>
    <div class="zss-context-stat">
        <span><?php echo htmlspecialchars(zss_t('overview.stats.snapshot_count')); ?></span>
        <strong id="snapshot-count">-</strong>
    </div>
    <div class="zss-context-stat">
        <span><?php echo htmlspecialchars(zss_t('overview.stats.readonly_count')); ?></span>
        <strong id="readonly-count">-</strong>
    </div>
    <div class="zss-context-stat">
        <span><?php echo htmlspecialchars(zss_t('overview.stats.snapshot_size')); ?></span>
        <strong id="snapshot-used-bytes">-</strong>
    </div>
</section>

<section class="zss-context-strip zss-context-strip--recency" data-zss-entrance="1" aria-label="<?php echo htmlspecialchars(zss_t('overview.stats.last_snapshot')); ?>">
    <div class="zss-context-stat">
        <span><?php echo htmlspecialchars(zss_t('overview.stats.last_dataset')); ?></span>
        <strong id="last-dataset">-</strong>
    </div>
    <div class="zss-context-stat">
        <span><?php echo htmlspecialchars(zss_t('overview.stats.last_snapshot')); ?></span>
        <strong id="last-snapshot">-</strong>
    </div>
</section>

<section class="zss-panel" data-zss-entrance="2">
    <div class="zss-console-grid">
        <div class="zss-console-cell zss-ring-zone">
            <h2 class="zss-console-kicker"><?php echo htmlspecialchars(zss_t('overview.console.protected_ring')); ?></h2>
            <div class="zss-ring" id="protection-ring" role="img" aria-label="<?php echo htmlspecialchars(zss_t('overview.console.protected_ring')); ?>">
                <b class="zss-ring-value"><span id="protection-ring-value">-</span><small><?php echo htmlspecialchars(zss_t('overview.console.protected_label')); ?></small></b>
            </div>
            <div class="zss-ring-legend">
                <span><i class="zss-legend-swatch is-good"></i><?php echo htmlspecialchars(zss_t('overview.console.legend_protected')); ?></span>
                <span><i class="zss-legend-swatch is-track"></i><?php echo htmlspecialchars(zss_t('overview.console.legend_unprotected')); ?></span>
            </div>
        </div>
        <div class="zss-console-cell">
            <h2 class="zss-console-kicker"><?php echo htmlspecialchars(zss_t('overview.console.space_usage')); ?></h2>
            <div class="zss-space-list" id="space-list"></div>
            <p class="zss-space-total" id="space-total" hidden><?php echo htmlspecialchars(zss_t('overview.console.total_space')); ?> <strong id="space-total-value"></strong></p>
        </div>
    </div>
</section>

<section class="zss-panel" data-zss-entrance="3">
    <div class="zss-panel-header zss-panel-header--inventory">
        <div class="zss-panel-kicker"><?php echo htmlspecialchars(zss_t('overview.inventory.kicker')); ?></div>
        <h2><?php echo htmlspecialchars(zss_t('overview.dataset_status')); ?></h2>
        <p class="zss-panel-caption"><?php echo htmlspecialchars(zss_t('overview.inventory.caption')); ?> <span class="zss-panel-count" id="dataset-inventory-count">—</span></p>
    </div>
    <div class="zss-table-wrap">
        <table class="zss-table">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(zss_t('table.dataset')); ?></th>
                    <th><?php echo htmlspecialchars(zss_t('table.status')); ?></th>
                    <th><?php echo htmlspecialchars(zss_t('table.frequency')); ?></th>
                    <th><?php echo htmlspecialchars(zss_t('table.keep')); ?></th>
                    <th><?php echo htmlspecialchars(zss_t('table.readonly')); ?></th>
                    <th><?php echo htmlspecialchars(zss_t('table.snapshot_count')); ?></th>
                    <th><?php echo htmlspecialchars(zss_t('table.actions')); ?></th>
                </tr>
            </thead>
            <tbody id="datasets-table">
                <tr><td colspan="7" class="zss-table-message"><?php echo htmlspecialchars(zss_t('common.loading')); ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
