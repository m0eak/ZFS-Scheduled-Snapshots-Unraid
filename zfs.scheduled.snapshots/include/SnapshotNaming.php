<?php

class SnapshotNaming {

    public static function parse($fullName) {
        if (!is_string($fullName) || strpos($fullName, '@') === false) {
            return null;
        }

        [$dataset, $shortName] = explode('@', $fullName, 2);

        if ($dataset === '' || $shortName === '') {
            return null;
        }

        return [
            'dataset' => $dataset,
            'short_name' => $shortName,
        ];
    }

    public static function classifyShortName($shortName) {
        $autoPrefix = ZfsScheduledSnapshots::AUTO_SNAPSHOT_PREFIX . '_';
        $manualPrefix = ZfsScheduledSnapshots::MANUAL_SNAPSHOT_PREFIX . '_';

        if (strpos($shortName, $autoPrefix) === 0) {
            return [
                'origin' => 'autosnap',
                'managed' => true,
            ];
        }

        if (strpos($shortName, $manualPrefix) === 0) {
            return [
                'origin' => 'plugin_manual',
                'managed' => true,
            ];
        }

        return [
            'origin' => 'external',
            'managed' => false,
        ];
    }
}

