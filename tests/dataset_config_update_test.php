<?php

require_once dirname(__DIR__) . '/zfs.scheduled.snapshots/include/services/DatasetService.php';

function zss_dataset_fixture($overrides = []) {
    $directory = sys_get_temp_dir() . '/zss-config-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $statePath = $directory . '/state.json';
    $logPath = $directory . '/commands.jsonl';
    $initial = [
        'com.sun:auto-snapshot' => ['yes', 'local'],
        'com.sun:auto-snapshot:frequency' => ['daily', 'default'],
        'com.sun:auto-snapshot:keep' => ['31', 'inherited from tank'],
        'com.sun:auto-snapshot:time' => ['00:00', 'received'],
        'com.sun:auto-snapshot:day' => ['1', 'default'],
        'com.sun:auto-snapshot:readonly' => ['no', 'default'],
        'com.sun:auto-snapshot:retain-days' => ['0', 'default'],
    ];
    foreach ($overrides as $property => $value) {
        $initial[$property] = $value;
    }
    file_put_contents($statePath, json_encode(['initial' => $initial, 'current' => $initial, 'failed' => false]));
    $zfs = <<<'FAKE'
#!/usr/bin/env php
<?php
$args = array_slice($argv, 1);
$statePath = getenv('ZSS_FIXTURE_STATE');
$state = json_decode(file_get_contents($statePath), true);
$logPath = getenv('ZSS_FIXTURE_LOG');
file_put_contents($logPath, json_encode($args) . "\n", FILE_APPEND);
$emit = static function($text = '', $status = 0) { if ($text !== '') echo $text . "\n"; exit($status); };
if (($args[0] ?? '') === 'list') $emit('tank/data');
if (($args[0] ?? '') === 'get') {
    $formatIndex = array_search('-o', $args, true);
    $format = $formatIndex === false ? '' : ($args[$formatIndex + 1] ?? '');
    $propertyArg = $formatIndex === false ? '' : ($args[$formatIndex + 2] ?? '');
    $properties = explode(',', $propertyArg);
    if (($args[0] ?? '') === 'get' && getenv('ZSS_FIXTURE_FAIL_READ_PROPERTY') !== false
        && in_array(getenv('ZSS_FIXTURE_FAIL_READ_PROPERTY'), $properties, true)) {
        $emit('simulated property read failure', 1);
    }
    $lines = [];
    foreach ($properties as $property) {
        $item = $state['current'][$property] ?? ['-', 'default'];
        if ($format === 'value,source') $lines[] = $item[0] . "\t" . $item[1];
        elseif ($format === 'property,value') $lines[] = $property . "\t" . $item[0];
        elseif ($format === 'value') $lines[] = $item[0];
        elseif ($format === 'name,value') $lines[] = 'tank/data' . "\t" . $item[0];
    }
    $emit(implode("\n", $lines));
}
if (($args[0] ?? '') === 'set') {
    $assignment = $args[1] ?? '';
    [$property, $value] = array_pad(explode('=', $assignment, 2), 2, '');
    $isRecovery = isset($state['initial'][$property]) && $state['initial'][$property][0] === $value;
    if (!$state['failed'] && getenv('ZSS_FIXTURE_FAIL_PROPERTY') === $property) {
        $state['failed'] = true;
        if (getenv('ZSS_FIXTURE_MUTATE_BEFORE_FAILURE') === '1') {
            $state['current'][$property] = [$value, 'local'];
        }
        file_put_contents($statePath, json_encode($state));
        $emit('simulated write failure', 1);
    }
    if ($isRecovery && getenv('ZSS_FIXTURE_FAIL_RECOVERY') === $property) $emit('simulated recovery failure', 1);
    if (getenv('ZSS_FIXTURE_SET_NOOP') === '1' && !$isRecovery) $emit('simulated successful no-op');
    $state['current'][$property] = [$value, 'local'];
    file_put_contents($statePath, json_encode($state));
    $emit();
}
if (($args[0] ?? '') === 'inherit') {
    $offset = ($args[1] ?? '') === '-S' ? 2 : 1;
    $property = $args[$offset] ?? '';
    if (getenv('ZSS_FIXTURE_FAIL_RECOVERY') === $property) $emit('simulated recovery failure', 1);
    $initial = $state['initial'][$property] ?? ['-', 'default'];
    $state['current'][$property] = $initial;
    file_put_contents($statePath, json_encode($state));
    $emit();
}
$emit();
FAKE;
    $binary = $directory . '/zfs';
    file_put_contents($binary, $zfs);
    chmod($binary, 0700);
    putenv('PATH=' . $directory . ':' . getenv('PATH'));
    putenv('ZSS_FIXTURE_STATE=' . $statePath);
    putenv('ZSS_FIXTURE_LOG=' . $logPath);
    putenv('ZSS_OPERATION_LOCK_PATH=' . $directory . '/operation.lock');
    putenv('ZSS_FIXTURE_FAIL_PROPERTY');
    putenv('ZSS_FIXTURE_FAIL_RECOVERY');
    return [$directory, $statePath, $logPath];
}

function zss_fixture_commands($path) {
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    return array_map(static function($line) { return json_decode($line, true); }, $lines);
}

function zss_assert_config_rejected_without_mutations($config, $label) {
    [$directory, , $log] = zss_dataset_fixture();
    $result = DatasetService::updateDatasetConfig('tank/data', $config);
    zss_assert_true(empty($result['success']), "$label should be rejected");
    $mutations = array_filter(zss_fixture_commands($log), static function($args) {
        return in_array($args[0] ?? '', ['set', 'inherit'], true);
    });
    zss_assert_true($mutations === [], "$label must be rejected before set/inherit");
}

zss_test('negative keep is rejected before mutation', function() {
    zss_assert_config_rejected_without_mutations(['keep' => -1], 'negative keep');
});

zss_test('invalid readonly boolean is rejected before mutation', function() {
    zss_assert_config_rejected_without_mutations(['readonly' => 'invalid'], 'invalid readonly');
});

zss_test('invalid time range is rejected before mutation', function() {
    zss_assert_config_rejected_without_mutations(['time' => '99:99'], 'invalid time');
});

zss_test('weekly day outside range is rejected before mutation', function() {
    zss_assert_config_rejected_without_mutations(['frequency' => 'weekly', 'day' => 10], 'weekly day 10');
});

zss_test('negative retain days is rejected before mutation', function() {
    zss_assert_config_rejected_without_mutations(['retain_days' => -2], 'negative retain_days');
});

zss_test('partial update validates against authoritative current properties', function() {
    [$directory, $statePath, $log] = zss_dataset_fixture();
    $result = DatasetService::updateDatasetConfig('tank/data', ['time' => '12:30']);
    $state = json_decode(file_get_contents($statePath), true);
    zss_assert_true(!empty($result['success']), 'Valid partial update should succeed');
    zss_assert_true($state['current']['com.sun:auto-snapshot:time'] === ['12:30', 'local'], 'Requested time should be applied');
});

zss_test('property capture read failure fails closed before mutation', function() {
    [$directory, , $log] = zss_dataset_fixture();
    putenv('ZSS_FIXTURE_FAIL_READ_PROPERTY=com.sun:auto-snapshot:keep');
    $result = DatasetService::updateDatasetConfig('tank/data', ['time' => '12:30']);
    putenv('ZSS_FIXTURE_FAIL_READ_PROPERTY');
    zss_assert_true(empty($result['success']) && ($result['code'] ?? '') === 'CONFIG_CAPTURE_FAILED', 'Read failure should be a capture failure');
    $mutations = array_filter(zss_fixture_commands($log), static function($args) {
        return in_array($args[0] ?? '', ['set', 'inherit'], true);
    });
    zss_assert_true($mutations === [], 'Read failure must perform no mutations');
});

zss_test('invalid dataset config makes no ZFS writes', function() {
    [$directory, , $log] = zss_dataset_fixture();
    $result = DatasetService::updateDatasetConfig('tank/data', ['unexpected' => true]);
    zss_assert_true(empty($result['success']), 'Expected invalid configuration to fail');
    $writes = array_filter(zss_fixture_commands($log), static function($args) { return in_array($args[0] ?? '', ['set', 'inherit'], true); });
    zss_assert_true($writes === [], 'Invalid configuration must not issue mutations');
});

zss_test('received properties are restored with the received source intact', function() {
    [$directory, $statePath, $log] = zss_dataset_fixture();
    putenv('ZSS_FIXTURE_FAIL_PROPERTY=com.sun:auto-snapshot');
    $result = DatasetService::updateDatasetConfig('tank/data', ['time' => '12:00', 'enabled' => false]);
    $state = json_decode(file_get_contents($statePath), true);
    zss_assert_true($result['rollback']['status'] === 'complete', 'Expected complete recovery before failed final enable write');
    zss_assert_true($state['current']['com.sun:auto-snapshot:time'] === ['00:00', 'received'], 'Received property provenance must be restored');
    $commands = zss_fixture_commands($log);
    $receivedInherit = array_filter($commands, static function($args) {
        return ($args[0] ?? '') === 'inherit' && ($args[1] ?? '') === '-S' && ($args[2] ?? '') === 'com.sun:auto-snapshot:time';
    });
    zss_assert_true(count($receivedInherit) === 1, 'Received properties must use the received-value inherit operation');
});

zss_test('mid-update failure restores inherited source and leaves enable disabled until last', function() {
    [$directory, $statePath, $log] = zss_dataset_fixture();
    putenv('ZSS_FIXTURE_FAIL_PROPERTY=com.sun:auto-snapshot:time');
    $result = DatasetService::updateDatasetConfig('tank/data', ['keep' => 45, 'time' => '12:00', 'enabled' => false]);
    $state = json_decode(file_get_contents($statePath), true);
    zss_assert_true(empty($result['success']), 'Expected update failure');
    zss_assert_true($result['rollback']['status'] === 'complete', 'Expected complete recovery');
    zss_assert_true($state['current']['com.sun:auto-snapshot:keep'] === ['31', 'inherited from tank'], 'Inherited property source must be restored');
    $commands = zss_fixture_commands($log);
    $writes = array_values(array_filter($commands, static function($args) { return ($args[0] ?? '') === 'set'; }));
    zss_assert_true(count($writes) === 2, 'Later writes must stop at the first failure');
    zss_assert_true(strpos($writes[0][1], 'com.sun:auto-snapshot:keep=45') === 0, 'Expected first requested property write');
    zss_assert_true(strpos($writes[1][1], 'com.sun:auto-snapshot:time=12:00') === 0, 'Expected failure before enable write');
});

zss_test('failed recovery is reported as partial and never as clean rollback', function() {
    [$directory, , $log] = zss_dataset_fixture();
    putenv('ZSS_FIXTURE_FAIL_PROPERTY=com.sun:auto-snapshot:time');
    putenv('ZSS_FIXTURE_FAIL_RECOVERY=com.sun:auto-snapshot:keep');
    $result = DatasetService::updateDatasetConfig('tank/data', ['keep' => 45, 'time' => '12:00']);
    zss_assert_true($result['rollback']['status'] === 'partial', 'Expected partial recovery status');
    zss_assert_true(isset($result['rollback']['failed_fields']['keep']), 'Expected failed recovery field detail');
    zss_assert_true($result['code'] === 'CONFIG_RECOVERY_FAILED', 'Expected recovery failure code');
});

zss_test('busy config writer returns retryable conflict and performs no writes', function() {
    [$directory, , $log] = zss_dataset_fixture();
    $held = OperationLock::acquire(OperationLock::configuredPath());
    $result = DatasetService::updateDatasetConfig('tank/data', ['keep' => 45]);
    zss_assert_true(($result['code'] ?? '') === 'OPERATION_BUSY' && !empty($result['retryable']), 'Expected retryable busy result');
    zss_assert_true(zss_fixture_commands($log) === [], 'Busy update must acquire the lock before authoritative ZFS reads');
    $held->release();
});

zss_test('failed write that mutates first is restored and verified', function() {
    [$directory, $statePath] = zss_dataset_fixture();
    putenv('ZSS_FIXTURE_FAIL_PROPERTY=com.sun:auto-snapshot:time');
    putenv('ZSS_FIXTURE_MUTATE_BEFORE_FAILURE=1');
    $result = DatasetService::updateDatasetConfig('tank/data', ['time' => '12:00']);
    $state = json_decode(file_get_contents($statePath), true);
    zss_assert_true(($result['rollback']['status'] ?? '') === 'complete', 'Expected verified complete recovery');
    zss_assert_true($state['current']['com.sun:auto-snapshot:time'] === ['00:00', 'received'], 'Failed attempted property must recover its original value and source');
    putenv('ZSS_FIXTURE_FAIL_PROPERTY');
    putenv('ZSS_FIXTURE_MUTATE_BEFORE_FAILURE');
});

zss_test('successful no-op write is detected and reported as failed update', function() {
    [$directory, $statePath] = zss_dataset_fixture();
    putenv('ZSS_FIXTURE_SET_NOOP=1');
    $result = DatasetService::updateDatasetConfig('tank/data', ['keep' => 45]);
    $state = json_decode(file_get_contents($statePath), true);
    zss_assert_true(empty($result['success']), 'A successful command with an unchanged effective value must fail');
    zss_assert_true(($result['code'] ?? '') === 'CONFIG_UPDATE_ROLLED_BACK', 'No-op update should report verified recovery');
    zss_assert_true($state['current']['com.sun:auto-snapshot:keep'] === ['31', 'inherited from tank'], 'No-op must preserve the original effective value and source');
    putenv('ZSS_FIXTURE_SET_NOOP');
});

zss_test('invalid frequency is rejected before any mutation', function() {
    [$directory, , $log] = zss_dataset_fixture();
    $result = DatasetService::updateDatasetConfig('tank/data', ['keep' => 45, 'frequency' => 'invalid']);
    zss_assert_true(empty($result['success']), 'Invalid frequency must be rejected');
    $writes = array_filter(zss_fixture_commands($log), static function($args) { return in_array($args[0] ?? '', ['set', 'inherit'], true); });
    zss_assert_true($writes === [], 'Invalid frequency must not issue mutations');
});
