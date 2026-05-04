<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

$limit = intval($_GET['limit'] ?? 200);
if ($limit < 1) {
    $limit = 200;
}

// TODO: 后面补 LogService + 插件专用日志
// 现阶段先返回占位结构，让 UI 能跑起来
zss_json_success([
    'limit' => $limit,
    'entries' => [],
    'source' => 'placeholder',
]);
