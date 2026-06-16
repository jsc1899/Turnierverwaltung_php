<?php

/**
 * Erzeugt die Spielreihenfolge einer Gruppe (jeder gegen jeden, je einmal).
 *
 * Rundenbasierte Auslosung nach der Kreismethode: Der Spielplan wird in Runden
 * gegliedert, in denen jeder Teilnehmer genau einmal spielt. Beim Aneinanderreihen
 * der Runden hat damit jeder Teilnehmer seine Spiele gleichmäßig über den gesamten
 * Plan verteilt (genau ein Spiel je Runde) — kein „Front-Loading".
 *
 * Damit niemand zwei Partien direkt hintereinander spielt (Back-to-Back, v.a. an
 * Rundenübergängen), werden mehrere zufällige Kandidaten erzeugt und der mit den
 * wenigsten Back-to-Back-Übergängen gewählt (Abbruch sobald 0 erreicht ist). Eine
 * komplett back-to-back-freie Reihenfolge existiert ab 5 Teilnehmern immer; bei 3
 * und 4 Teilnehmern sind einzelne Übergänge mathematisch unvermeidbar.
 *
 * Die eigentliche Auslosung (wechselnde Spielpläne) entsteht durch Mischen der
 * Teilnehmer, der Runden­reihenfolge, der Spiele innerhalb einer Runde und der
 * Seitenwahl. Funktioniert für jede Teilnehmerzahl.
 */

/**
 * Liefert die Spielreihenfolge als flache Paarliste [[p1,p2], …] (Rückwärtskompatibel).
 */
function round_robin_schedule(array $player_ids): array {
    $r = round_robin_schedule_rounds($player_ids);
    return array_map(fn($m) => [$m['p1'], $m['p2']], $r['matches']);
}

/**
 * Liefert die Spielreihenfolge inklusive Rundeninformation:
 *   [ 'matches' => [ ['p1'=>id, 'p2'=>id, 'round'=>1-basiert], … ] (in Spielreihenfolge),
 *     'byes'    => [ rundenNr => spielfreie_ID|null, … ] ]
 * 'byes' enthält bei ungerader Teilnehmerzahl je Runde den spielfreien Teilnehmer.
 */
function round_robin_schedule_rounds(array $player_ids): array {
    $n = count($player_ids);
    if ($n < 2) return ['matches' => [], 'byes' => []];
    if ($n === 2) {
        return ['matches' => [['p1' => $player_ids[0], 'p2' => $player_ids[1], 'round' => 1]],
                'byes' => [1 => null]];
    }

    $best = null;
    $best_conf = PHP_INT_MAX;
    for ($t = 0; $t < 400; $t++) {
        $cand = rr_flatten(rr_build_once($player_ids));
        if ($cand['b2b'] < $best_conf) {
            $best = $cand;
            $best_conf = $cand['b2b'];
            if ($best_conf === 0) break;
        }
    }
    return ['matches' => $best['matches'], 'byes' => $best['byes']];
}

/**
 * Ein zufälliger, rundenbasierter Spielplan-Kandidat (Kreismethode).
 * Rückgabe: Liste von Runden, je Runde ['matches' => [[p1,p2], …], 'bye' => id|null].
 */
function rr_build_once(array $player_ids): array {
    $players = $player_ids;
    shuffle($players);                                // Zufalls-Auslosung
    if (count($players) % 2 === 1) $players[] = null; // BYE bei ungerader Anzahl
    $m    = count($players);
    $half = intdiv($m, 2);

    // Kreismethode: ein Spieler bleibt fix, die übrigen rotieren.
    $fixed    = array_pop($players);
    $rotating = $players;                             // $m-1 Elemente
    $rounds   = [];
    for ($r = 0; $r < $m - 1; $r++) {
        $matches = [];
        $bye     = null;
        $pairs   = [[$fixed, $rotating[0]]];
        for ($k = 1; $k < $half; $k++) {
            $pairs[] = [$rotating[$k], $rotating[$m - 1 - $k]];
        }
        foreach ($pairs as [$p1, $p2]) {
            if ($p1 === null)      { $bye = $p2; continue; }   // BYE: p2 ist spielfrei
            elseif ($p2 === null)  { $bye = $p1; continue; }   // BYE: p1 ist spielfrei
            $matches[] = mt_rand(0, 1) ? [$p1, $p2] : [$p2, $p1]; // zufällige Seitenwahl
        }
        shuffle($matches);                           // Reihenfolge innerhalb der Runde
        $rounds[] = ['matches' => $matches, 'bye' => $bye];
        array_unshift($rotating, array_pop($rotating)); // Rotation
    }
    shuffle($rounds);                                // Rundenreihenfolge
    return $rounds;
}

/**
 * Reiht Runden aneinander und vermeidet Back-to-Back an den Übergängen: das erste
 * Spiel einer neuen Runde soll keinen Spieler des letzten Spiels der Vorrunde
 * enthalten (soweit möglich). Liefert flache Spielreihenfolge + Byes + Back-to-Back-Zahl.
 */
function rr_flatten(array $rounds): array {
    $matches   = [];
    $byes      = [];
    $prev_last = null;
    $b2b       = 0;
    $ri        = 0;
    foreach ($rounds as $round) {
        $ri++;
        $ms = $round['matches'];
        if ($prev_last !== null && count($ms) > 1) {
            $prev_set = [$prev_last[0] => true, $prev_last[1] => true];
            foreach ($ms as $k => [$a, $b]) {
                if (!isset($prev_set[$a]) && !isset($prev_set[$b])) {
                    if ($k !== 0) [$ms[0], $ms[$k]] = [$ms[$k], $ms[0]];
                    break;
                }
            }
        }
        foreach ($ms as [$a, $b]) {
            if ($prev_last !== null &&
                ($a === $prev_last[0] || $a === $prev_last[1] ||
                 $b === $prev_last[0] || $b === $prev_last[1])) {
                $b2b++;
            }
            $matches[] = ['p1' => $a, 'p2' => $b, 'round' => $ri];
            $prev_last = [$a, $b];
        }
        $byes[$ri] = $round['bye'];
    }
    return ['matches' => $matches, 'byes' => $byes, 'b2b' => $b2b];
}
