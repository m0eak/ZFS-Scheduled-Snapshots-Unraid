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
require __DIR__ . '/zfs_command_test.php';
require __DIR__ . '/snapshot_naming_test.php';
require __DIR__ . '/schedule_policy_test.php';
require __DIR__ . '/retention_policy_test.php';
require __DIR__ . '/action_confirmation_test.php';

if ($failures > 0) {
    exit(1);
}
