<?php

class RetentionPolicy {

    public static function selectPruneCandidates($snapshots, $keep) {
        $keep = intval($keep);
        if ($keep <= 0) {
            return [];
        }

        if (count($snapshots) <= $keep) {
            return [];
        }

        $sorted = $snapshots;
        usort($sorted, function($left, $right) {
            return intval($right['creation'] ?? 0) <=> intval($left['creation'] ?? 0);
        });

        $toDelete = array_slice($sorted, $keep);
        return array_map(function($snapshot) {
            return $snapshot['name'];
        }, $toDelete);
    }
}

