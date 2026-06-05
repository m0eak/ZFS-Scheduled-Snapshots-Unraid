<?php
$nextCurrentPage = 'logs';
require __DIR__ . '/../i18n.php';
$nextPageTitle = zss_t('logs.title');
$nextPageDescription = zss_t('app.webui');
$nextPageScript = 'assets/js/logs.js';
require __DIR__ . '/layout/shell.php';
?>

<div class="zss-page-actions">
    <select id="log-level" class="zss-select">
        <option value="all"><?php echo htmlspecialchars(zss_t('logs.level.all')); ?></option>
        <option value="INFO">INFO</option>
        <option value="ERROR">ERROR</option>
    </select>
    <button class="zss-btn zss-btn-secondary" type="button" onclick="loadLogs()"><?php echo htmlspecialchars(zss_t('common.refresh')); ?></button>
    <button class="zss-btn zss-btn-danger" type="button" onclick="clearLogs()"><?php echo htmlspecialchars(zss_t('logs.clear')); ?></button>
</div>

<section id="log-status" class="zss-panel zss-status-panel"></section>

<section class="zss-panel">
    <div class="zss-panel-header">
        <h2><?php echo htmlspecialchars(zss_t('logs.title')); ?></h2>
    </div>
    <div class="zss-table-wrap zss-log-table-wrap">
        <table class="zss-table">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(zss_t('table.created_at')); ?></th>
                    <th><?php echo htmlspecialchars(zss_t('table.level')); ?></th>
                    <th><?php echo htmlspecialchars(zss_t('table.message')); ?></th>
                </tr>
            </thead>
            <tbody id="logs-table">
                <tr><td colspan="3" class="zss-table-message"><?php echo htmlspecialchars(zss_t('common.loading')); ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
