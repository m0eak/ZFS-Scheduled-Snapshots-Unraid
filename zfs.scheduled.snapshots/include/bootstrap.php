<?php

$pluginRoot = dirname(__DIR__);
$includeRoot = __DIR__;
$servicesRoot = $includeRoot . '/services';

require_once $includeRoot . '/common.php';
require_once $includeRoot . '/ZfsCommand.php';
require_once $includeRoot . '/SnapshotNaming.php';
require_once $includeRoot . '/SchedulePolicy.php';
require_once $includeRoot . '/RetentionPolicy.php';
require_once $includeRoot . '/response.php';
require_once $includeRoot . '/validation.php';
require_once $servicesRoot . '/DatasetService.php';
require_once $servicesRoot . '/SnapshotService.php';
require_once $servicesRoot . '/LogService.php';
