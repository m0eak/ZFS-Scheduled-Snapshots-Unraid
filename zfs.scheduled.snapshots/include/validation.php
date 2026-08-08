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

function zss_validate_new_dataset_name($value, $allowedNames, $requireImmediateParent = true) {
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

    if ($requireImmediateParent) {
        $parent = substr($value, 0, strrpos($value, '/'));
        if ($parent === '' || !in_array($parent, $allowedNames, true)) {
            return 'Parent dataset does not exist';
        }
    }

    return null;
}

function zss_validate_dataset_child_path($value) {
    if (!is_string($value) || trim($value) === '') {
        return 'Dataset child path is required';
    }

    $value = trim($value, "/ \t\n\r\0\x0B");
    if ($value === '') {
        return 'Dataset child path is required';
    }

    if (strlen($value) > 200) {
        return 'Dataset child path is too long';
    }

    if (strpos($value, '//') !== false || strpos($value, '..') !== false) {
        return 'Dataset child path contains invalid path segments';
    }

    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]*(?:\/[A-Za-z0-9][A-Za-z0-9_.:-]*)*$/', $value) !== 1) {
        return 'Dataset child path contains invalid characters';
    }

    return null;
}

function zss_build_dataset_name_from_parent($parent, $child) {
    return trim((string) $parent, '/') . '/' . trim((string) $child, "/ \t\n\r\0\x0B");
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

function zss_action_origin_matches_host($origin, $host, $requestScheme = 'http') {
    $originParts = parse_url($origin);
    $hostParts = parse_url('//' . $host);

    if (!is_array($originParts) || !is_array($hostParts) || empty($originParts['scheme']) || empty($originParts['host']) || empty($hostParts['host'])) {
        return false;
    }

    if (strcasecmp($originParts['scheme'], $requestScheme) !== 0 || strcasecmp($originParts['host'], $hostParts['host']) !== 0) {
        return false;
    }

    $originPort = $originParts['port'] ?? (strtolower($originParts['scheme']) === 'https' ? 443 : 80);
    $hostPort = $hostParts['port'] ?? (strtolower($requestScheme) === 'https' ? 443 : 80);

    return (int) $originPort === (int) $hostPort;
}

function zss_action_request_scheme($server) {
    if (!empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off') {
        return 'https';
    }

    $scheme = strtolower((string) ($server['REQUEST_SCHEME'] ?? 'http'));
    return $scheme === 'https' ? 'https' : 'http';
}

function zss_validate_action_request($server, $query = []) {
    if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return [
            'code' => 'ACTION_METHOD_REQUIRED',
            'message' => 'POST method is required for action requests',
            'status' => 405,
        ];
    }

    if (!empty($query)) {
        return [
            'code' => 'ACTION_QUERY_DENIED',
            'message' => 'Action parameters must be sent in the JSON request body',
            'status' => 400,
        ];
    }

    if (($server['HTTP_X_ZSS_ACTION'] ?? '') !== '1') {
        return [
            'code' => 'ACTION_HEADER_REQUIRED',
            'message' => 'Action header is required',
            'status' => 403,
        ];
    }

    $origin = $server['HTTP_ORIGIN'] ?? '';
    $host = $server['HTTP_HOST'] ?? '';
    if ($origin !== '' && $host !== '' && !zss_action_origin_matches_host($origin, $host, zss_action_request_scheme($server))) {
        return [
            'code' => 'ACTION_ORIGIN_DENIED',
            'message' => 'Cross-origin action requests are not allowed',
            'status' => 403,
        ];
    }

    $fetchSite = $server['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'none'], true)) {
        return [
            'code' => 'ACTION_FETCH_SITE_DENIED',
            'message' => 'Cross-site action requests are not allowed',
            'status' => 403,
        ];
    }

    return null;
}

function zss_require_action_request() {
    $error = zss_validate_action_request($_SERVER, $_GET);
    if ($error !== null) {
        zss_json_error($error['code'], $error['message'], $error['status']);
    }
}

function zss_csrf_token() {
    static $token = null;

    if ($token !== null) {
        return $token;
    }

    // Unraid's POST guard reads the session CSRF token from this state file.
    // The same value is exposed by Dynamix pages as the JavaScript csrf_token.
    $var = @parse_ini_file('/var/local/emhttp/var.ini');
    $token = (is_array($var) && !empty($var['csrf_token'])) ? (string) $var['csrf_token'] : '';

    return $token;
}

function zss_validate_csrf_value($sent, $expected) {
    if ($expected === '') {
        return [
            'code' => 'CSRF_UNAVAILABLE',
            'message' => 'CSRF token is unavailable on this server',
            'status' => 503,
        ];
    }

    if (!is_string($sent) || $sent === '') {
        return [
            'code' => 'CSRF_MISSING',
            'message' => 'CSRF token is required',
            'status' => 403,
        ];
    }

    if (!hash_equals($expected, $sent)) {
        return [
            'code' => 'CSRF_INVALID',
            'message' => 'CSRF token does not match',
            'status' => 403,
        ];
    }

    return null;
}

function zss_get_action_payload() {
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') !== 0) {
        zss_json_error('ACTION_JSON_REQUIRED', 'Action requests must use an application/json body', 415);
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
        zss_json_error('ACTION_JSON_INVALID', 'Action request body must be valid JSON object', 400);
    }

    // Unraid's auto_prepend checks X-CSRF-Token for JSON requests and removes
    // that header before this plugin endpoint runs. The JSON body mirrors the
    // token solely for this defense-in-depth verification.
    $csrfError = zss_validate_csrf_value($payload['csrf_token'] ?? null, zss_csrf_token());
    if ($csrfError !== null) {
        zss_json_error($csrfError['code'], $csrfError['message'], $csrfError['status']);
    }

    unset($payload['csrf_token']);

    return $payload;
}

function zss_validate_action_confirmation($payload, $expected) {
    $confirm = $payload['confirm'] ?? null;

    if (!is_string($confirm) || trim($confirm) === '') {
        return [
            'code' => 'CONFIRMATION_REQUIRED',
            'message' => 'Confirmation is required',
        ];
    }

    if (!hash_equals((string) $expected, $confirm)) {
        return [
            'code' => 'CONFIRMATION_MISMATCH',
            'message' => 'Confirmation does not match the requested action',
        ];
    }

    return null;
}

function zss_require_action_confirmation($payload, $expected) {
    $error = zss_validate_action_confirmation($payload, $expected);
    if ($error !== null) {
        zss_json_error($error['code'], $error['message'], 400);
    }
}
