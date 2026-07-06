<?php

class ZfsCommand {

    public static function escapeArg($arg) {
        return escapeshellarg((string) $arg);
    }

    public static function build($binary, $args = []) {
        $parts = [(string) $binary];

        foreach ($args as $arg) {
            $parts[] = self::escapeArg($arg);
        }

        return implode(' ', $parts);
    }

    public static function run($binary, $args = []) {
        return self::runShell(self::build($binary, $args));
    }

    public static function runShell($command) {
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        return [
            'success' => $returnVar === 0,
            'stdout' => $output,
            'stderr' => [],
            'exit_code' => $returnVar,
            'command' => $command,
            'output' => $output,
            'return_var' => $returnVar,
        ];
    }
}

