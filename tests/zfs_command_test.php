<?php

$zfsCommandPath = dirname(__DIR__) . '/zfs.scheduled.snapshots/include/ZfsCommand.php';
if (file_exists($zfsCommandPath)) {
    require_once $zfsCommandPath;
}

zss_test('zfs command builds shell-safe commands from argument arrays', function() {
    zss_assert_true(class_exists('ZfsCommand'), 'Expected ZfsCommand class to exist');

    $command = ZfsCommand::build('zfs', [
        'snapshot',
        'tank/data set@manual_2026-07-05_12:00:00',
    ]);

    zss_assert_true(
        $command === "zfs 'snapshot' 'tank/data set@manual_2026-07-05_12:00:00'",
        'Expected command arguments to be shell escaped'
    );
});

zss_test('zfs command run returns unified and legacy result fields', function() {
    zss_assert_true(class_exists('ZfsCommand'), 'Expected ZfsCommand class to exist');

    $result = ZfsCommand::run('php', [
        '-r',
        'echo "ok";',
    ]);

    zss_assert_true($result['success'] === true, 'Expected success=true');
    zss_assert_true($result['exit_code'] === 0, 'Expected exit_code=0');
    zss_assert_true($result['stdout'] === ['ok'], 'Expected stdout lines');
    zss_assert_true($result['output'] === ['ok'], 'Expected legacy output lines');
    zss_assert_true($result['return_var'] === 0, 'Expected legacy return_var=0');
});
