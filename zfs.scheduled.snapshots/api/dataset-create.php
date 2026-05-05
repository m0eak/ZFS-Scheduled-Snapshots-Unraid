<?php

require_once dirname(__DIR__) . '/include/bootstrap.php';

zss_api_run(function() {
    zss_require_action_request();
    $payload = zss_get_action_payload();

    $allowedNames = DatasetService::getManagedDatasetNames();
    $parentNames = DatasetService::getRootFilesystemDatasetNames();
    $parent = trim((string) ($payload['parent'] ?? ''));
    $child = trim((string) ($payload['child'] ?? ''));

    $allowIntermediateParents = false;

    if ($parent !== '' || $child !== '') {
        $parentError = zss_validate_dataset_name($parent, $parentNames);
        if ($parentError !== null) {
            zss_json_error('INVALID_PARENT_DATASET', $parentError, 400);
        }

        $childError = zss_validate_dataset_child_path($child);
        if ($childError !== null) {
            zss_json_error('INVALID_DATASET_CHILD', $childError, 400);
        }

        $name = zss_build_dataset_name_from_parent($parent, $child);
        $allowIntermediateParents = true;
    } else {
        $name = trim((string) ($payload['name'] ?? ''));
    }

    $nameError = zss_validate_new_dataset_name($name, $allowedNames, !$allowIntermediateParents);

    if ($nameError !== null) {
        zss_json_error('INVALID_DATASET', $nameError, 400);
    }

    $result = DatasetService::createDataset($name, $payload, $allowIntermediateParents);

    if ($result['success']) {
        zss_json_success([
            'message' => 'Dataset created',
            'dataset' => $result['dataset'],
        ]);
    } else {
        zss_json_error('CREATE_FAILED', $result['error'], 500);
    }
});
