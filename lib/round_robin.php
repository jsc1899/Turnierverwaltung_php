<?php

function round_robin_schedule(array $player_ids): array {
    $players = $player_ids;
    $n = count($players);
    if ($n < 2) return [];
    if ($n % 2 === 1) $players[] = null;  // bye slot for odd counts
    $n_eff   = count($players);
    $fixed   = $players[$n_eff - 1];
    $rotating = array_slice($players, 0, $n_eff - 1);
    $matches = [];

    for ($round = 0; $round < $n_eff - 1; $round++) {
        $p1 = $fixed; $p2 = $rotating[0];
        if ($p1 !== null && $p2 !== null) $matches[] = [$p1, $p2];

        for ($k = 1; $k < $n_eff / 2; $k++) {
            $p1 = $rotating[$k];
            $p2 = $rotating[$n_eff - 1 - $k];
            if ($p1 !== null && $p2 !== null) $matches[] = [$p1, $p2];
        }
        // Rotate: last element moves to front
        $rotating = array_merge([array_pop($rotating)], $rotating);
    }
    return $matches;
}
