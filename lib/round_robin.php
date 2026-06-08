<?php

function round_robin_schedule(array $player_ids): array {
    $n = count($player_ids);
    if ($n < 2) return [];
    if ($n === 2) return [[$player_ids[0], $player_ids[1]]];

    // FIDE Berger-Tabellen, organisiert nach Runden (0-basierte Index-Paare)
    // Für ungerade n: (n+1)-Tabelle + null-BYE, BYE-Partien werden gefiltert
    static $berger = [
        4 => [
            [[0,3],[1,2]],
            [[3,2],[0,1]],
            [[1,3],[2,0]],
        ],
        6 => [
            [[0,5],[1,4],[2,3]],
            [[5,3],[4,2],[0,1]],
            [[1,5],[2,0],[3,4]],
            [[5,4],[0,3],[1,2]],
            [[2,5],[3,1],[4,0]],
        ],
        8 => [
            [[0,7],[1,6],[2,5],[3,4]],
            [[7,4],[5,3],[6,2],[0,1]],
            [[1,7],[2,0],[3,6],[4,5]],
            [[7,5],[6,4],[0,3],[1,2]],
            [[2,7],[3,1],[4,0],[5,6]],
            [[7,6],[0,5],[1,4],[2,3]],
            [[3,7],[4,2],[5,1],[6,0]],
        ],
    ];

    $n_eff   = $n % 2 === 0 ? $n : $n + 1;
    $players = $n % 2 === 0 ? $player_ids : array_merge($player_ids, [null]);

    // Runden aufbauen, BYE-Spiele filtern
    $rounds = [];
    foreach ($berger[$n_eff] as $round_pairs) {
        $round = [];
        foreach ($round_pairs as [$i, $j]) {
            $p1 = $players[$i];
            $p2 = $players[$j];
            if ($p1 !== null && $p2 !== null) $round[] = [$p1, $p2];
        }
        if ($round) $rounds[] = $round;
    }

    // Greedy-Optimierung an Rundenübergängen: das erste Spiel der neuen Runde
    // soll keinen Spieler des letzten Spiels der Vorrunde enthalten (soweit möglich)
    $matches   = [];
    $prev_last = null;
    foreach ($rounds as $round) {
        if ($prev_last !== null && count($round) > 1) {
            $prev_set = [$prev_last[0] => true, $prev_last[1] => true];
            foreach ($round as $k => [$a, $b]) {
                if (!isset($prev_set[$a]) && !isset($prev_set[$b])) {
                    if ($k !== 0) {
                        [$round[0], $round[$k]] = [$round[$k], $round[0]];
                    }
                    break;
                }
            }
        }
        foreach ($round as $m) $matches[] = $m;
        $prev_last = end($round);
    }
    return $matches;
}
