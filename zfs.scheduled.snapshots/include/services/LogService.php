<?php

require_once dirname(__DIR__) . '/common.php';

class LogService {

    /**
     * 获取日志列表
     */
    public static function getLogs($limit = 100, $level = null) {
        $logFile = ZfsScheduledSnapshots::LOG_FILE;
        
        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        // 最新的在前面
        $lines = array_reverse($lines);
        
        $logs = [];
        $count = 0;

        foreach ($lines as $line) {
            if ($limit > 0 && $count >= $limit) {
                break;
            }

            // 解析日志格式: [2026-05-04 14:00:00] [INFO] message
            if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] (.*)$/', $line, $matches)) {
                $logLevel = $matches[2];
                
                // 按级别过滤
                if ($level !== null && $level !== 'all' && $logLevel !== $level) {
                    continue;
                }

                $logs[] = [
                    'timestamp' => $matches[1],
                    'level' => $logLevel,
                    'message' => $matches[3],
                ];
                $count++;
            }
        }

        return $logs;
    }

    /**
     * 清空日志
     */
    public static function clearLogs() {
        $logFile = ZfsScheduledSnapshots::LOG_FILE;
        
        if (file_exists($logFile)) {
            $result = file_put_contents($logFile, '');
            if ($result !== false) {
                ZfsScheduledSnapshots::log('Log cleared manually');
                return true;
            }
        }
        
        return false;
    }
}
