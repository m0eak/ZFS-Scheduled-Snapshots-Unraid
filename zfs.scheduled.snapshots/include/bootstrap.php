<?php

$pluginRoot = dirname(__DIR__);
$includeRoot = __DIR__;
$servicesRoot = $includeRoot . '/services';

require_once $includeRoot . '/common.php';
require_once $includeRoot . '/response.php';
require_once $includeRoot . '/validation.php';
require_once $servicesRoot . '/DatasetService.php';
require_once $servicesRoot . '/SnapshotService.php';
