<?php

class SchedulePolicy {

    private static $intervals = [
        '5min' => 300,
        '15min' => 900,
        'hourly' => 3600,
    ];

    public static function evaluate($config) {
        $frequency = $config['frequency'] ?? 'daily';
        $latestTimestamp = intval($config['latest_timestamp'] ?? 0);
        $now = intval($config['now'] ?? time());

        if (array_key_exists($frequency, self::$intervals)) {
            return self::evaluateInterval($frequency, $latestTimestamp, $now);
        }

        return self::evaluateCalendar($frequency, $config, $latestTimestamp, $now);
    }

    private static function evaluateInterval($frequency, $latestTimestamp, $now) {
        $targetInterval = self::$intervals[$frequency];

        if ($latestTimestamp <= 0) {
            return [
                'due' => true,
                'reason' => 'No previous auto-snapshot found',
            ];
        }

        $elapsed = $now - $latestTimestamp;

        return [
            'due' => $elapsed >= $targetInterval,
            'reason' => "Last snapshot was {$elapsed} seconds ago (target: {$targetInterval})",
            'target_timestamp' => $latestTimestamp + $targetInterval,
        ];
    }

    private static function evaluateCalendar($frequency, $config, $latestTimestamp, $now) {
        $targetTime = $config['time'] ?? '00:00';
        $targetDay = intval($config['day'] ?? 1);
        $parts = explode(':', $targetTime);
        $targetHour = intval($parts[0] ?? 0);
        $targetMinute = intval($parts[1] ?? 0);
        $currentYear = intval(date('Y', $now));
        $currentMonth = intval(date('n', $now));
        $currentDayNum = intval(date('j', $now));

        if ($frequency === 'weekly') {
            $currentDow = intval(date('N', $now));
            $todayAtTargetTime = mktime($targetHour, $targetMinute, 0, $currentMonth, $currentDayNum, $currentYear);
            $diffDays = $targetDay - $currentDow;
            $targetTimestamp = strtotime("$diffDays days", $todayAtTargetTime);
        } elseif ($frequency === 'monthly') {
            $daysInMonth = intval(date('t', $now));
            $actualTargetDay = min($targetDay, $daysInMonth);
            $targetTimestamp = mktime($targetHour, $targetMinute, 0, $currentMonth, $actualTargetDay, $currentYear);
        } else {
            $targetTimestamp = mktime($targetHour, $targetMinute, 0, $currentMonth, $currentDayNum, $currentYear);
        }

        return [
            'due' => $now >= $targetTimestamp && $latestTimestamp < $targetTimestamp,
            'reason' => "{$frequency} schedule {$targetTime}",
            'target_timestamp' => $targetTimestamp,
        ];
    }
}

