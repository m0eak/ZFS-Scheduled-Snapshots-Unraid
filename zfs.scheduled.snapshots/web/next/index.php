<?php
$nextCurrentPage = 'overview';
require __DIR__ . '/../i18n.php';
$nextPageTitle = zss_t('overview.title');
$nextPageDescription = zss_t('overview.dataset_status');
$nextPageScript = 'assets/js/overview.js';
require __DIR__ . '/layout/shell.php';
?>

<div class="zss-metrics-grid">
    <article class="zss-metric-card">
        <div class="zss-metric-icon zss-icon-blue"><?php echo zss_next_icon('datasets'); ?></div>
        <div>
            <p><?php echo htmlspecialchars(zss_t('overview.stats.dataset_count')); ?></p>
            <strong id="dataset-count">-</strong>
        </div>
    </article>
    <article class="zss-metric-card">
        <div class="zss-metric-icon zss-icon-green">▶</div>
        <div>
            <p><?php echo htmlspecialchars(zss_t('overview.stats.enabled_count')); ?></p>
            <strong id="enabled-count">-</strong>
        </div>
    </article>
    <article class="zss-metric-card">
        <div class="zss-metric-icon zss-icon-purple"><?php echo zss_next_icon('camera'); ?></div>
        <div>
            <p><?php echo htmlspecialchars(zss_t('overview.stats.snapshot_count')); ?></p>
            <strong id="snapshot-count">-</strong>
        </div>
    </article>
    <article class="zss-metric-card">
        <div class="zss-metric-icon zss-icon-amber"><?php echo zss_next_icon('shield'); ?></div>
        <div>
            <p><?php echo htmlspecialchars(zss_t('overview.stats.readonly_count')); ?></p>
            <strong id="readonly-count">-</strong>
        </div>
    </article>
    <article class="zss-metric-card">
        <div class="zss-metric-icon zss-icon-blue"><?php echo zss_next_icon('drive'); ?></div>
        <div>
            <p><?php echo htmlspecialchars(zss_t('overview.stats.snapshot_size')); ?></p>
            <strong id="snapshot-used-bytes">-</strong>
        </div>
    </article>
</div>

<div class="zss-info-grid">
    <article class="zss-info-card">
        <span class="zss-info-icon"><?php echo zss_next_icon('clock'); ?></span>
        <div>
            <p><?php echo htmlspecialchars(zss_t('overview.stats.last_snapshot')); ?></p>
            <strong id="last-snapshot">-</strong>
        </div>
    </article>
    <article class="zss-info-card">
        <span class="zss-info-icon"><?php echo zss_next_icon('server'); ?></span>
        <div>
            <p><?php echo htmlspecialchars(zss_t('overview.stats.last_dataset')); ?></p>
            <strong id="last-dataset">-</strong>
        </div>
    </article>
</div>

<section class="zss-panel">
    <div class="zss-panel-header">
        <h2><?php echo htmlspecialchars(zss_t('overview.dataset_status')); ?></h2>
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
