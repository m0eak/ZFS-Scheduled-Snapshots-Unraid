<?php

require_once dirname(__DIR__) . '/zfs.scheduled.snapshots/include/OperationLock.php';

function operation_lock_test_process(string $code): array
{
    $process = proc_open(
        [PHP_BINARY, '-r', $code],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start PHP lock test process');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [$exitCode, trim($stdout), trim($stderr)];
}

zss_test('operation lock excludes a second PHP process and releases for a later process', function() {
    $directory = sys_get_temp_dir() . '/zss-operation-lock-' . bin2hex(random_bytes(8));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Could not create operation lock test directory');
    }
    $path = $directory . '/operation.lock';
    $lock = null;

    try {
        $lock = OperationLock::acquire($path);
        zss_assert_true($lock instanceof OperationLock, 'Expected to acquire the operation lock');

        $child = 'require ' . var_export(dirname(__DIR__) . '/zfs.scheduled.snapshots/include/OperationLock.php', true) . ';'
            . '$lock = OperationLock::acquire(' . var_export($path, true) . ');'
            . 'echo $lock === null ? "BUSY" : "ACQUIRED";';
        [$exitCode, $stdout, $stderr] = operation_lock_test_process($child);
        zss_assert_true($exitCode === 0 && $stdout === 'BUSY', 'Expected second PHP process to report busy; got ' . $stdout . ' ' . $stderr);

        $lock->release();
        $lock = null;
        [$exitCode, $stdout, $stderr] = operation_lock_test_process($child);
        zss_assert_true($exitCode === 0 && $stdout === 'ACQUIRED', 'Expected a later PHP process to acquire the released lock; got ' . $stdout . ' ' . $stderr);
        zss_assert_true(is_file($path), 'Expected releasing the lock to preserve its file');
    } finally {
        if ($lock instanceof OperationLock) {
            $lock->release();
        }
        @unlink($path);
        @rmdir($directory);
    }
});

zss_test('operation lock reports invalid lock paths as failures', function() {
    $invalidPath = sys_get_temp_dir() . '/zss-missing-' . bin2hex(random_bytes(8)) . '/operation.lock';
    $failed = false;
    try {
        OperationLock::acquire($invalidPath);
    } catch (RuntimeException $error) {
        $failed = true;
    }

    zss_assert_true($failed, 'Expected an invalid lock path to fail explicitly');
});

zss_test('duplicate runner exits before dataset or ZFS work', function() {
    $directory = sys_get_temp_dir() . '/zss-runner-lock-' . bin2hex(random_bytes(8));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Could not create duplicate runner test directory');
    }
    $path = $directory . '/operation.lock';
    $lock = OperationLock::acquire($path);

    try {
        $environment = array_merge($_ENV, ['ZSS_OPERATION_LOCK_PATH' => $path]);
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/zfs.scheduled.snapshots/scripts/runner.php'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__),
            $environment
        );
        zss_assert_true(is_resource($process), 'Could not start duplicate runner process');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        zss_assert_true($exitCode === 0, 'Expected a duplicate runner to exit successfully; stderr: ' . trim($stderr));
        zss_assert_true(strpos($stderr, 'another operation is active') !== false, 'Expected duplicate runner to log the busy condition');
        zss_assert_true(strpos($stdout, 'Runner started') === false, 'Expected duplicate runner to stop before starting scheduler work');
        zss_assert_true(strpos($stdout, 'Runner loaded') === false, 'Expected duplicate runner to stop before loading datasets');
    } finally {
        $lock->release();
        @unlink($path);
        @rmdir($directory);
    }
});

zss_test('runner fails closed when its operation lock cannot be opened', function() {
    $invalidPath = sys_get_temp_dir() . '/zss-missing-' . bin2hex(random_bytes(8)) . '/operation.lock';
    $process = proc_open(
        [PHP_BINARY, dirname(__DIR__) . '/zfs.scheduled.snapshots/scripts/runner.php'],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        dirname(__DIR__),
        array_merge($_ENV, ['ZSS_OPERATION_LOCK_PATH' => $invalidPath])
    );
    zss_assert_true(is_resource($process), 'Could not start fail-closed runner process');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    zss_assert_true($exitCode === 1, 'Expected lock failure to exit unsuccessfully');
    zss_assert_true(strpos($stderr, 'could not acquire operation lock') !== false, 'Expected lock failure to be logged');
    zss_assert_true(strpos($stdout, 'Runner started') === false, 'Expected lock failure to stop before scheduler work');
});
