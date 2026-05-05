<?php

function zss_get_available_languages(): array
{
    return [
        'en' => 'English',
        'zh-CN' => '简体中文',
    ];
}

function zss_get_locale_preference(): string
{
    if (isset($_GET['lang'])) {
        $queryLang = trim((string) $_GET['lang']);
        return $queryLang === '' || strtolower($queryLang) === 'auto' ? 'auto' : zss_normalize_locale($queryLang);
    }

    if (isset($_COOKIE['zss_lang'])) {
        $cookieLang = trim((string) $_COOKIE['zss_lang']);
        return $cookieLang === '' || strtolower($cookieLang) === 'auto' ? 'auto' : zss_normalize_locale($cookieLang);
    }

    return 'auto';
}

function zss_detect_browser_locale(?string $header): string
{
    if (!$header) {
        return 'en';
    }

    foreach (explode(',', $header) as $part) {
        $lang = trim(explode(';', $part)[0] ?? '');
        if ($lang === '') {
            continue;
        }

        $normalized = zss_normalize_locale($lang);
        if ($normalized === 'zh-CN') {
            return 'zh-CN';
        }

        if ($normalized === 'en') {
            return 'en';
        }
    }

    return 'en';
}

function zss_normalize_locale(?string $locale): string
{
    $available = zss_get_available_languages();
    if (!$locale) {
        return 'en';
    }

    if (isset($available[$locale])) {
        return $locale;
    }

    $lower = strtolower($locale);
    if (str_starts_with($lower, 'zh')) {
        return 'zh-CN';
    }

    if (str_starts_with($lower, 'en')) {
        return 'en';
    }

    return 'en';
}

function zss_translations(): array
{
    return [
        'en' => [
            'app.title' => 'ZFS Scheduled Snapshots',
            'app.webui' => 'WebUI',
            'nav.overview' => 'Overview',
            'nav.datasets' => 'Datasets',
            'nav.snapshots' => 'Snapshots',
            'nav.logs' => 'Logs',
            'nav.settings' => 'Settings',
            'nav.plugin' => 'Plugin Page',
            'lang.label' => 'Language',
            'lang.auto' => 'Browser default',
            'overview.title' => 'Overview',
            'overview.dataset_status' => 'Dataset Status',
            'overview.stats.dataset_count' => 'Datasets',
            'overview.stats.enabled_count' => 'Enabled',
            'overview.stats.snapshot_count' => 'Snapshots',
            'overview.stats.readonly_count' => 'Protected',
            'overview.stats.snapshot_size' => 'Snapshot Space',
            'overview.stats.last_snapshot' => 'Last Snapshot Time',
            'overview.stats.last_dataset' => 'Last Snapshot Dataset',
            'table.dataset' => 'Dataset',
            'table.status' => 'Status',
            'table.frequency' => 'Frequency',
            'table.keep' => 'Keep',
            'table.keep_days' => 'Retention Days',
            'table.readonly' => 'Read-Only',
            'table.snapshot_count' => 'Snapshots',
            'table.last_snapshot' => 'Last Snapshot',
            'table.actions' => 'Actions',
            'table.snapshot_name' => 'Snapshot Name',
            'table.created_at' => 'Created At',
            'table.message' => 'Message',
            'table.level' => 'Level',
            'common.loading' => 'Loading...',
            'common.load_failed' => 'Load failed',
            'common.none' => 'None',
            'common.yes' => 'Yes',
            'common.no' => 'No',
            'common.enabled' => 'Enabled',
            'common.disabled' => 'Disabled',
            'common.protected' => 'Protected',
            'common.normal' => 'Normal',
            'common.refresh' => 'Refresh',
            'common.save' => 'Save',
            'common.cancel' => 'Cancel',
            'common.edit' => 'Edit',
            'common.delete' => 'Delete',
            'common.close' => 'Close',
            'common.back' => 'Back',
            'common.api_error' => 'API error',
            'common.api_returned_error' => 'API returned error',
            'common.request_failed' => 'Request failed',
            'common.unknown_error' => 'Unknown error',
            'common.unknown' => 'Unknown',
            'common.inherit' => 'Inherit',
            'datasets.title' => 'Dataset Management',
            'datasets.empty' => 'No datasets',
            'datasets.create.title' => 'Create dataset',
            'datasets.create.description' => 'Select an existing parent dataset, then enter only the child path to create.',
            'datasets.create.parent' => 'Parent dataset',
            'datasets.create.child' => 'Child dataset path',
            'datasets.create.name' => 'Dataset name',
            'datasets.create.mount' => 'Mount',
            'datasets.create.mountpoint' => 'Mountpoint',
            'datasets.create.atime' => 'Access time',
            'datasets.create.casesensitivity' => 'Case sensitivity',
            'datasets.create.compression' => 'Compression',
            'datasets.create.quota' => 'Quota',
            'datasets.create.action' => 'Create dataset',
            'datasets.create.name_required' => 'Parent dataset and child path are required',
            'datasets.create.confirm' => 'Create dataset {name}?',
            'datasets.create.success' => 'Dataset created',
            'datasets.create.failed' => 'Create failed',
            'datasets.modal.title' => 'Edit Dataset Configuration',
            'datasets.actions.snapshots' => 'Snapshots',
            'datasets.fields.enable' => 'Enable automatic snapshots',
            'datasets.fields.frequency' => 'Snapshot frequency',
            'datasets.fields.keep' => 'Snapshots to keep',
            'datasets.fields.time' => 'Snapshot time',
            'datasets.fields.day' => 'Day',
            'datasets.fields.weekday' => 'Weekday',
            'datasets.fields.readonly' => 'Mark new snapshots as read-only (Hold)',
            'datasets.fields.retain_days' => 'Read-only retention days (0 = unlimited)',
            'datasets.save_success' => 'Saved successfully',
            'datasets.save_failed' => 'Save failed',
            'snapshots.title' => 'Snapshot Management',
            'snapshots.current_dataset' => 'Current dataset',
            'snapshots.back_all' => 'Back to all snapshots',
            'snapshots.create' => 'Create snapshot manually',
            'snapshots.empty' => 'No snapshots',
            'snapshots.select_dataset' => 'Please choose a dataset first',
            'snapshots.dataset_empty' => 'No datasets',
            'snapshots.view' => 'View snapshots',
            'snapshots.hold' => 'Set read-only',
            'snapshots.release' => 'Release hold',
            'snapshots.create_success' => 'Snapshot created',
            'snapshots.create_failed' => 'Create failed',
            'snapshots.delete_success' => 'Snapshot deleted',
            'snapshots.delete_failed' => 'Delete failed',
            'snapshots.hold_success' => 'Protection enabled',
            'snapshots.hold_failed' => 'Failed to enable protection',
            'snapshots.release_success' => 'Protection released',
            'snapshots.release_failed' => 'Failed to release protection',
            'snapshots.confirm_create' => 'Create a snapshot manually now?',
            'snapshots.confirm_delete' => 'Delete snapshot {name}?',
            'snapshots.confirm_hold' => 'Add read-only protection to snapshot {name}?',
            'snapshots.confirm_release' => 'Release read-only protection for snapshot {name}?',
            'logs.title' => 'Execution Logs',
            'logs.level.all' => 'All levels',
            'logs.clear' => 'Clear Logs',
            'logs.no_logs' => 'No logs yet',
            'logs.file_missing' => 'Log file has not been created yet',
            'logs.status_missing' => 'Unable to load log status',
            'logs.status_read_failed' => 'Failed to read logs: {error}',
            'logs.status_path' => 'Path: {path}',
            'logs.status_missing_file' => 'Log file does not exist yet: {path}',
            'logs.status_dir_state' => 'Directory exists: {dir_exists}; writable: {dir_writable}',
            'logs.status_unreadable' => 'Log file exists but is not readable: {path}',
            'logs.status_ok' => 'Log file is available: {path}',
            'logs.clear_success' => 'Logs cleared',
            'logs.clear_failed' => 'Failed to clear logs',
            'logs.confirm_clear' => 'Clear all logs?',
            'settings.title' => 'Settings',
            'settings.description' => 'This is the first minimal settings page. Dark mode, auto theme, and more plugin-level settings can be added next.',
            'settings.language.title' => 'Language & Localization',
            'settings.language.description' => 'Switch the WebUI display language. The current version stores the preference in the browser.',
            'settings.language.current' => 'Current language',
            'settings.language.browser' => 'Browser language detection',
            'settings.language.saved' => 'Language preference saved',
            'settings.language.option.auto' => 'Browser default',
            'settings.theme.title' => 'Appearance',
            'settings.theme.description' => 'Choose how the WebUI theme should behave on this browser.',
            'settings.theme.current' => 'Theme mode',
            'settings.theme.saved' => 'Theme preference saved',
            'settings.theme.preview' => 'Effective theme',
            'settings.theme.option.auto' => 'Auto (follow system)',
            'settings.theme.option.light' => 'Light',
            'settings.theme.option.dark' => 'Dark',
            'settings.accent.title' => 'Accent color',
            'settings.accent.description' => 'Choose a fresh accent color for this browser.',
            'settings.accent.saved' => 'Accent color saved',
            'settings.accent.option.blue' => 'Unraid blue',
            'settings.accent.option.amber' => 'Amber',
            'settings.accent.option.mint' => 'Mint',
            'settings.accent.option.sky' => 'Sky',
            'settings.accent.option.lavender' => 'Lavender',
            'settings.accent.option.rose' => 'Rose',
            'settings.language.option.zh-CN' => '简体中文',
            'settings.language.option.en' => 'English',
            'frequency.5min' => 'Every 5 min',
            'frequency.15min' => 'Every 15 min',
            'frequency.hourly' => 'Hourly',
            'frequency.daily' => 'Daily',
            'frequency.weekly' => 'Weekly',
            'frequency.monthly' => 'Monthly',
            'weekday.1' => 'Mon',
            'weekday.2' => 'Tue',
            'weekday.3' => 'Wed',
            'weekday.4' => 'Thu',
            'weekday.5' => 'Fri',
            'weekday.6' => 'Sat',
            'weekday.7' => 'Sun',
        ],
        'zh-CN' => [
            'app.title' => 'ZFS Scheduled Snapshots',
            'app.webui' => 'WebUI',
            'nav.overview' => '概览',
            'nav.datasets' => '数据集',
            'nav.snapshots' => '快照',
            'nav.logs' => '日志',
            'nav.settings' => '设置',
            'nav.plugin' => '插件页',
            'lang.label' => '语言',
            'lang.auto' => '跟随浏览器',
            'overview.title' => '概览',
            'overview.dataset_status' => '数据集状态',
            'overview.stats.dataset_count' => '数据集总数',
            'overview.stats.enabled_count' => '已启用',
            'overview.stats.snapshot_count' => '快照总数',
            'overview.stats.readonly_count' => '受保护快照',
            'overview.stats.snapshot_size' => '快照占用空间',
            'overview.stats.last_snapshot' => '最后快照时间',
            'overview.stats.last_dataset' => '最后快照数据集',
            'table.dataset' => '数据集',
            'table.status' => '状态',
            'table.frequency' => '频率',
            'table.keep' => '保留数量',
            'table.keep_days' => '保留天数',
            'table.readonly' => '只读',
            'table.snapshot_count' => '快照数',
            'table.last_snapshot' => '最后快照',
            'table.actions' => '操作',
            'table.snapshot_name' => '快照名称',
            'table.created_at' => '创建时间',
            'table.message' => '消息',
            'table.level' => '级别',
            'common.loading' => '加载中...',
            'common.load_failed' => '加载失败',
            'common.none' => '无',
            'common.yes' => '是',
            'common.no' => '否',
            'common.enabled' => '启用',
            'common.disabled' => '禁用',
            'common.protected' => '受保护',
            'common.normal' => '普通',
            'common.refresh' => '刷新',
            'common.save' => '保存',
            'common.cancel' => '取消',
            'common.edit' => '编辑',
            'common.delete' => '删除',
            'common.close' => '关闭',
            'common.back' => '返回',
            'common.api_error' => '接口异常',
            'common.api_returned_error' => '接口返回错误',
            'common.request_failed' => '请求失败',
            'common.unknown_error' => '未知错误',
            'common.unknown' => '未知',
            'common.inherit' => '继承',
            'datasets.title' => '数据集管理',
            'datasets.empty' => '暂无数据集',
            'datasets.create.title' => '创建数据集',
            'datasets.create.description' => '先选择已有父数据集，再只填写要创建的子路径。',
            'datasets.create.parent' => '父数据集',
            'datasets.create.child' => '子数据集路径',
            'datasets.create.name' => '数据集名称',
            'datasets.create.mount' => '挂载',
            'datasets.create.mountpoint' => '挂载点',
            'datasets.create.atime' => '访问时间',
            'datasets.create.casesensitivity' => '大小写敏感',
            'datasets.create.compression' => '压缩',
            'datasets.create.quota' => '配额',
            'datasets.create.action' => '创建数据集',
            'datasets.create.name_required' => '请选择父数据集并填写子路径',
            'datasets.create.confirm' => '确定要创建数据集 {name} 吗？',
            'datasets.create.success' => '数据集已创建',
            'datasets.create.failed' => '创建失败',
            'datasets.modal.title' => '编辑数据集配置',
            'datasets.actions.snapshots' => '快照',
            'datasets.fields.enable' => '启用自动快照',
            'datasets.fields.frequency' => '快照频率',
            'datasets.fields.keep' => '保留快照数量',
            'datasets.fields.time' => '快照时间',
            'datasets.fields.day' => '日期',
            'datasets.fields.weekday' => '星期',
            'datasets.fields.readonly' => '新快照设为只读（Hold 保护）',
            'datasets.fields.retain_days' => '只读快照保留天数（0=不限制）',
            'datasets.save_success' => '保存成功',
            'datasets.save_failed' => '保存失败',
            'snapshots.title' => '快照管理',
            'snapshots.current_dataset' => '当前数据集',
            'snapshots.back_all' => '返回全部快照',
            'snapshots.create' => '手动创建快照',
            'snapshots.empty' => '暂无快照',
            'snapshots.select_dataset' => '请先选择一个数据集',
            'snapshots.dataset_empty' => '暂无数据集',
            'snapshots.view' => '查看快照',
            'snapshots.hold' => '设为只读',
            'snapshots.release' => '释放保护',
            'snapshots.create_success' => '创建成功',
            'snapshots.create_failed' => '创建失败',
            'snapshots.delete_success' => '删除成功',
            'snapshots.delete_failed' => '删除失败',
            'snapshots.hold_success' => '设置成功',
            'snapshots.hold_failed' => '设置失败',
            'snapshots.release_success' => '释放成功',
            'snapshots.release_failed' => '释放失败',
            'snapshots.confirm_create' => '确定要手动创建快照吗？',
            'snapshots.confirm_delete' => '确定要删除快照 {name} 吗？',
            'snapshots.confirm_hold' => '确定要为快照 {name} 添加只读保护吗？',
            'snapshots.confirm_release' => '确定要释放快照 {name} 的只读保护吗？',
            'logs.title' => '执行日志',
            'logs.level.all' => '全部级别',
            'logs.clear' => '清空日志',
            'logs.no_logs' => '暂无日志',
            'logs.file_missing' => '日志文件尚未生成',
            'logs.status_missing' => '无法获取日志状态信息',
            'logs.status_read_failed' => '日志读取失败：{error}',
            'logs.status_path' => '路径：{path}',
            'logs.status_missing_file' => '日志文件尚不存在：{path}',
            'logs.status_dir_state' => '目录存在：{dir_exists}；目录可写：{dir_writable}',
            'logs.status_unreadable' => '日志文件存在但不可读：{path}',
            'logs.status_ok' => '日志文件正常：{path}',
            'logs.clear_success' => '日志已清空',
            'logs.clear_failed' => '清空失败',
            'logs.confirm_clear' => '确定要清空所有日志吗？',
            'settings.title' => '设置',
            'settings.description' => '先提供最小可用设置项，后续再扩展暗色模式、自动主题与更多插件级配置。',
            'settings.language.title' => '语言与本地化',
            'settings.language.description' => '切换 WebUI 显示语言。当前版本会记住浏览器端偏好。',
            'settings.language.current' => '当前语言',
            'settings.language.browser' => '浏览器语言检测',
            'settings.language.saved' => '语言设置已保存',
            'settings.language.option.auto' => '跟随浏览器',
            'settings.theme.title' => '外观',
            'settings.theme.description' => '选择当前浏览器中 WebUI 的主题行为。',
            'settings.theme.current' => '主题模式',
            'settings.theme.saved' => '主题设置已保存',
            'settings.theme.preview' => '当前生效主题',
            'settings.theme.option.auto' => '自动（跟随系统）',
            'settings.theme.option.light' => '浅色',
            'settings.theme.option.dark' => '深色',
            'settings.accent.title' => '强调色',
            'settings.accent.description' => '选择当前浏览器使用的清爽主题强调色。',
            'settings.accent.saved' => '强调色已保存',
            'settings.accent.option.blue' => 'Unraid 蓝',
            'settings.accent.option.amber' => '琥珀',
            'settings.accent.option.mint' => '薄荷',
            'settings.accent.option.sky' => '天蓝',
            'settings.accent.option.lavender' => '薰衣草',
            'settings.accent.option.rose' => '玫瑰',
            'settings.language.option.zh-CN' => '简体中文',
            'settings.language.option.en' => 'English',
            'frequency.5min' => '每 5 分钟',
            'frequency.15min' => '每 15 分钟',
            'frequency.hourly' => '每小时',
            'frequency.daily' => '每天',
            'frequency.weekly' => '每周',
            'frequency.monthly' => '每月',
            'weekday.1' => '周一',
            'weekday.2' => '周二',
            'weekday.3' => '周三',
            'weekday.4' => '周四',
            'weekday.5' => '周五',
            'weekday.6' => '周六',
            'weekday.7' => '周日',
        ],
    ];
}

function zss_get_locale_translations(?string $locale = null): array
{
    $translations = zss_translations();
    $resolved = zss_normalize_locale($locale ?? zss_current_locale());
    return $translations[$resolved] ?? $translations['en'];
}

function zss_current_locale(): string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $preference = zss_get_locale_preference();
    if ($preference !== 'auto') {
        $resolved = $preference;
        return $resolved;
    }

    $resolved = zss_detect_browser_locale($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null);
    return $resolved;
}

function zss_t(string $key, array $replace = []): string
{
    $translations = zss_translations();
    $locale = zss_current_locale();
    $fallback = 'en';

    $text = $translations[$locale][$key] ?? $translations[$fallback][$key] ?? $key;

    foreach ($replace as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }

    return $text;
}

if (!function_exists('withLang')) {
    function withLang(string $url): string
    {
        $preference = zss_get_locale_preference();
        if ($preference === 'auto') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        if (preg_match('/(?:^|[?&])lang=/', $url) === 1) {
            return $url;
        }

        return $url . $separator . 'lang=' . rawurlencode(zss_current_locale());
    }
}
