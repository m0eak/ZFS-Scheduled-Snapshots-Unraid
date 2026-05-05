<?php

function zss_allowed_frequencies() {
    return ['5min', '15min', 'hourly', 'daily', 'weekly', 'monthly'];
}

function zss_is_valid_frequency($value) {
    return in_array($value, zss_allowed_frequencies(), true);
}

function zss_is_valid_time_string($value) {
    return is_string($value) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
}

function zss_normalize_bool($value) {
    if (is_bool($value)) {
        return $value;
    }

    if (is_string($value)) {
        $value = strtolower(trim($value));
        if ($value === 'true' || $value === '1') {
            return true;
        }
        if ($value === 'false' || $value === '0' || $value === '') {
            return false;
        }
    }

    if (is_int($value)) {
        return $value === 1;
    }

    return false;
}

function zss_validate_dataset_name($value, $allowedNames) {
    if (!is_string($value) || trim($value) === '') {
        return 'Dataset name is required';
    }

    if (!in_array($value, $allowedNames, true)) {
        return 'Dataset does not exist';
    }

    return null;
}

function zss_validate_new_dataset_name($value, $allowedNames) {
    if (!is_string($value) || trim($value) === '') {
        return 'Dataset name is required';
    }

    $value = trim($value);
    if (strlen($value) > 255) {
        return 'Dataset name is too long';
    }

    if (strpos($value, '/') === false) {
        return 'Dataset name must include a pool and child name';
    }

    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]*(?:\/[A-Za-z0-9][A-Za-z0-9_.:-]*)+$/', $value) !== 1) {
        return 'Dataset name contains invalid characters';
    }

    if (in_array($value, $allowedNames, true)) {
        return 'Dataset already exists';
    }

    $parent = substr($value, 0, strrpos($value, '/'));
    if ($parent === '' || !in_array($parent, $allowedNames, true)) {
        return 'Parent dataset does not exist';
    }

    return null;
}

function zss_validate_dataset_payload($payload, $allowedNames) {
    $errors = [];

    $name = $payload['name'] ?? null;
    $nameError = zss_validate_dataset_name($name, $allowedNames);
    if ($nameError !== null) {
        $errors['name'] = $nameError;
    }

    $frequency = $payload['frequency'] ?? 'daily';
    if (!zss_is_valid_frequency($frequency)) {
        $errors['frequency'] = 'Invalid frequency';
    }

    $keep = intval($payload['keep'] ?? 0);
    if ($keep < 1) {
        $errors['keep'] = 'Keep must be greater than or equal to 1';
    }

    $retainDays = intval($payload['retain_days'] ?? 0);
    if ($retainDays < 0) {
        $errors['retain_days'] = 'retain_days must be greater than or equal to 0';
    }

    $time = $payload['time'] ?? '00:00';
    if (in_array($frequency, ['daily', 'weekly', 'monthly'], true) && !zss_is_valid_time_string($time)) {
        $errors['time'] = 'Invalid time format';
    }

    $day = intval($payload['day'] ?? 1);
    if ($frequency === 'weekly' && ($day < 1 || $day > 7)) {
        $errors['day'] = 'Weekly day must be between 1 and 7';
    }
    if ($frequency === 'monthly' && ($day < 1 || $day > 31)) {
        $errors['day'] = 'Monthly day must be between 1 and 31';
    }

    return $errors;
}

function zss_require_action_request() {
    if (($_SERVER['HTTP_X_ZSS_ACTION'] ?? '') !== '1') {
        zss_json_error('ACTION_HEADER_REQUIRED', 'Action header is required', 403);
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($origin !== '' && $host !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        if ($originHost !== null && strcasecmp($originHost, $host) !== 0) {
            zss_json_error('ACTION_ORIGIN_DENIED', 'Cross-origin action requests are not allowed', 403);
        }
    }

    $fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'none'], true)) {
        zss_json_error('ACTION_FETCH_SITE_DENIED', 'Cross-site action requests are not allowed', 403);
    }
}

function zss_get_action_payload() {
    $payload = $_GET;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawBody = file_get_contents('php://input');
        $jsonPayload = json_decode($rawBody, true);
        if (is_array($jsonPayload)) {
            $payload = array_merge($payload, $jsonPayload);
        } else {
            $payload = array_merge($payload, $_POST);
        }
    }

    return $payload;
}
