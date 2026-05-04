<?php

function zss_json_success($data = [], $meta = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'data' => $data,
        'meta' => array_merge([
            'generated_at' => time(),
        ], $meta),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function zss_json_error($code, $message, $status = 400, $meta = []) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
        'meta' => array_merge([
            'generated_at' => time(),
        ], $meta),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
