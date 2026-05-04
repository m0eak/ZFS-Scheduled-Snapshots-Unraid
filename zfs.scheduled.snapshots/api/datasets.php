<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_json_success(DatasetService::listManagedDatasets());
