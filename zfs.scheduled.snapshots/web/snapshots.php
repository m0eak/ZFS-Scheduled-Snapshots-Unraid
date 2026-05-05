<?php
require_once __DIR__ . '/i18n.php';

$query = [];
if (!empty($_GET['dataset'])) {
    $query['dataset'] = $_GET['dataset'];
}

$target = 'snapshots-list.php';
if (!empty($query)) {
    $target .= '?' . http_build_query($query);
}

header('Location: ' . withLang($target));
exit;
