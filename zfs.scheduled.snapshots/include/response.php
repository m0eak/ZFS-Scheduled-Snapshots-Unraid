<?php

$GLOBALS['zss_json_response_sent'] = false;

function zss_emit_json($payload, $status = 200) {
    $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $jsonFlags);
    if ($json === false) {
        $status = 500;
        $json = json_encode([
            'ok' => false,
            'error' => [
                'code' => 'JSON_ENCODE_FAILED',
                'message' => json_last_error_msg(),
            ],
            'meta' => [
                'generated_at' => time(),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $GLOBALS['zss_json_response_sent'] = true;
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
    exit;
}

function zss_json_success($data = [], $meta = []) {
    zss_emit_json([
        'ok' => true,
        'data' => $data,
        'meta' => array_merge([
            'generated_at' => time(),
        ], $meta),
    ]);
}

function zss_json_error($code, $message, $status = 400, $meta = []) {
    zss_emit_json([
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
        'meta' => array_merge([
            'generated_at' => time(),
        ], $meta),
    ], $status);
}

function zss_api_run(callable $handler) {
    register_shutdown_function(function() {
        if (!empty($GLOBALS['zss_json_response_sent'])) {
            return;
        }

        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        if (ob_get_length() !== false) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        $GLOBALS['zss_json_response_sent'] = true;
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => 'PHP_FATAL',
                'message' => $error['message'],
            ],
            'meta' => [
                'generated_at' => time(),
                'file' => $error['file'] ?? null,
                'line' => $error['line'] ?? null,
            ],
        ], $jsonFlags);
    });

    ob_start();
    try {
        $handler();
    } catch (Throwable $error) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        zss_json_error('PHP_EXCEPTION', $error->getMessage(), 500, [
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);
    }
}
