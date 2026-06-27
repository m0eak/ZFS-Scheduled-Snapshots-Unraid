<?php

$failures = 0;

function zss_test($name, callable $test) {
    global $failures;

    try {
        $test();
        echo "PASS $name" . PHP_EOL;
    } catch (Throwable $error) {
        $failures++;
        echo "FAIL $name: " . $error->getMessage() . PHP_EOL;
    }
}

function zss_assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require __DIR__ . '/snapshot_service_test.php';

if ($failures > 0) {
    exit(1);
}

