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
function round_robin_schedule(array $player_ids, bool $force_bye = false): array {
    $r = round_robin_schedule_rounds($player_ids, $force_bye);
    return array_map(fn($m) => [$m['p1'], $m['p2']], $r['matches']);
}

/**
 * Liefert die Spielreihenfolge inklusive Rundeninformation:
 *   [ 'matches' => [ ['p1'=>id, 'p2'=>id, 'round'=>1-basiert], … ] (in Spielreihenfolge),
 *     'byes'    => [ rundenNr => spielfreie_ID|null, … ] ]
 * 'byes' enthält bei ungerader Teilnehmerzahl je Runde den spielfreien Teilnehmer.
 */
function round_robin_schedule_rounds(array $player_ids, bool $force_bye = false, string $mode = 'random', int $round_limit = 0): array {
    $n = count($player_ids);
    if ($n < 2) return ['matches' => [], 'byes' => []];
    if ($n === 2 && !$force_bye) {
        return rr_apply_limit(['matches' => [['p1' => $player_ids[0], 'p2' => $player_ids[1], 'round' => 1]],
                'byes' => [1 => null]], $round_limit);
    }

    // "Nach Position": deterministischer Spielplan (Kreismethode ohne Zufall), der der
    // Teilnehmerreihenfolge (= Gruppenposition) folgt. Keine Back-to-Back-Optimierung.
    if ($mode === 'position') {
        return rr_apply_limit(rr_flatten_plain(rr_build_position($player_ids, $force_bye)), $round_limit);
    }

    // Ohne Rundenbegrenzung: nur nach Back-to-Back optimieren (bisheriges Verhalten).
    if ($round_limit < 1) {
        $best = null; $best_conf = PHP_INT_MAX;
        for ($t = 0; $t < 400; $t++) {
            $cand = rr_flatten(rr_build_once($player_ids, $force_bye));
            if ($cand['b2b'] < $best_conf) {
                $best = $cand; $best_conf = $cand['b2b'];
                if ($best_conf === 0) break;
            }
        }
        return ['matches' => $best['matches'], 'byes' => $best['byes']];
    }

    // Mit Rundenbegrenzung: den Basis-Spielplan wählen, der nach der Ausgleichsrunde am besten
    // balanciert (kleinste Differenz Max−Min Spiele je Teilnehmer), Back-to-Back als Nebenkriterium.
    $best = null; $best_imb = PHP_INT_MAX; $best_b2b = PHP_INT_MAX;
    for ($t = 0; $t < 400; $t++) {
        $cand    = rr_flatten(rr_build_once($player_ids, $force_bye));
        $applied = rr_apply_limit(['matches' => $cand['matches'], 'byes' => $cand['byes']], $round_limit);
        $imb     = rr_imbalance($applied['matches']);
        if ($imb < $best_imb || ($imb === $best_imb && $cand['b2b'] < $best_b2b)) {
            $best = $applied; $best_imb = $imb; $best_b2b = $cand['b2b'];
            if ($imb === 0 && $cand['b2b'] === 0) break;
        }
    }
    return $best;
}

// Differenz zwischen meist- und wenigst-gespielter Spielanzahl (0 = perfekt ausgeglichen).
function rr_imbalance(array $matches): int {
    $count = [];
    foreach ($matches as $m) { $count[$m['p1']] = ($count[$m['p1']] ?? 0) + 1; $count[$m['p2']] = ($count[$m['p2']] ?? 0) + 1; }
    if (!$count) return 0;
    return max($count) - min($count);
}

// Begrenzt den Spielplan auf die ersten $limit Runden (0/<1 = unbegrenzt = voller Spielplan)
// und gleicht Freilose durch eine zusätzliche Ausgleichsrunde aus: Teilnehmer, die in den
// ersten $limit Runden ein Freilos hatten (also ein Spiel zu wenig), spielen in Runde $limit+1
// ein zusätzliches Spiel gegeneinander (nur noch nicht gespielte Paarungen). So haben am Ende
// möglichst alle gleich viele Spiele. Bei ungerader Konstellation (ungerade Teilnehmer- UND
// Rundenzahl) bleibt unvermeidbar ein Teilnehmer mit einem Spiel weniger.
function rr_apply_limit(array $res, int $limit): array {
    if ($limit < 1) return $res;
    $all = $res['matches'];

    $kept = []; $remaining = [];
    foreach ($all as $m) {
        if ((int)$m['round'] <= $limit) $kept[] = $m; else $remaining[] = $m;
    }
    if (!$remaining) { $res['matches'] = $kept; return $res; }  // nichts abzuschneiden

    // Spielanzahl je Teilnehmer in den behaltenen Runden (alle Teilnehmer initialisieren).
    $teams = [];
    foreach ($all as $m) { $teams[$m['p1']] = true; $teams[$m['p2']] = true; }
    $count = array_fill_keys(array_keys($teams), 0);
    foreach ($kept as $m) { $count[$m['p1']]++; $count[$m['p2']]++; }

    $target = $count ? max($count) : 0;          // = Teilnehmer ohne Freilos
    $need_init = [];
    foreach ($count as $t => $cnt) $need_init[$t] = $target - $cnt;   // 0 = genug, >0 = unterversorgt

    // Mögliche Ausgleichsspiele = noch nicht gespielte Paarungen (= $remaining) zwischen zwei
    // unterversorgten Teilnehmern. Eine einfache Greedy-Zuordnung findet nicht immer das
    // bestmögliche Matching → mehrere zufällige Reihenfolgen testen und das beste wählen
    // (Ziel: möglichst alle Teilnehmer auf gleiche Spielanzahl; min. Rest = Paritätsrest).
    $edges = [];
    foreach ($remaining as $m) {
        if (($need_init[$m['p1']] ?? 0) > 0 && ($need_init[$m['p2']] ?? 0) > 0) {
            $edges[] = [$m['p1'], $m['p2']];
        }
    }
    $target_left = array_sum($need_init) % 2;    // 0 = perfekt erreichbar, 1 = einer bleibt übrig
    $makeup = [];
    $best_left = PHP_INT_MAX;
    for ($try = 0; $try < 300; $try++) {
        shuffle($edges);
        $need = $need_init;
        $sel  = [];
        foreach ($edges as [$a, $b]) {
            if ($need[$a] > 0 && $need[$b] > 0) { $sel[] = [$a, $b]; $need[$a]--; $need[$b]--; }
        }
        $left = array_sum($need);
        if ($left < $best_left) { $best_left = $left; $makeup = $sel; if ($left <= $target_left) break; }
    }

    // Ausgleichsspiele ab Runde $limit+1 einplanen (kein Teilnehmer doppelt je Runde).
    $roundUse = [];
    foreach ($makeup as [$a, $b]) {
        $r = $limit + 1;
        while (isset($roundUse[$r][$a]) || isset($roundUse[$r][$b])) $r++;
        $roundUse[$r][$a] = true; $roundUse[$r][$b] = true;
        $kept[] = ['p1' => $a, 'p2' => $b, 'round' => $r];
    }

    $maxRound = $limit;
    foreach ($kept as $m) $maxRound = max($maxRound, (int)$m['round']);
    if (isset($res['byes'])) {
        $res['byes'] = array_filter($res['byes'], fn($r) => (int)$r <= $maxRound, ARRAY_FILTER_USE_KEY);
    }
    $res['matches'] = $kept;
    return $res;
}

/**
 * Ein zufälliger, rundenbasierter Spielplan-Kandidat (Kreismethode).
 * Rückgabe: Liste von Runden, je Runde ['matches' => [[p1,p2], …], 'bye' => id|null].
 */
function rr_build_once(array $player_ids, bool $force_bye = false): array {
    $players = $player_ids;
    shuffle($players);                                // Zufalls-Auslosung
    if (count($players) % 2 === 1) {
        $players[] = null;                            // BYE bei ungerader Anzahl
    } elseif ($force_bye) {
        // Gerade Anzahl + erzwungene Pause: zwei Phantom-Slots (Slot-Zahl bleibt gerade →
        // Kreismethode gültig). Je Runde bis zu zwei Spielfreie; jedes Team erhält ≥1 Pause.
        $players[] = null;
        $players[] = null;
    }
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

/**
 * Deterministischer, positionsbasierter Spielplan-Kandidat (Kreismethode OHNE Zufall).
 * Die Teilnehmerreihenfolge $player_ids entspricht der Gruppenposition (1, 2, 3, …).
 * Position 1 ist der feste Drehpunkt; gerundet wird wie in rr_build_once (BYE/Phantom-Slots).
 * Seitenwahl: niedrigere Position spielt als p1 (Heim). Reihenfolge innerhalb einer Runde:
 * nach niedrigster beteiligter Position. Rundenreihenfolge: natürliche Kreismethoden-Folge.
 */
function rr_build_position(array $player_ids, bool $force_bye = false): array {
    $pos     = array_flip(array_values($player_ids));   // id => 0-basierte Position
    $players = array_values($player_ids);
    if (count($players) % 2 === 1) {
        $players[] = null;                              // BYE bei ungerader Anzahl
    } elseif ($force_bye) {
        $players[] = null;                              // gerade Anzahl + erzwungene Pause
        $players[] = null;
    }
    $m    = count($players);
    $half = intdiv($m, 2);

    $fixed    = array_shift($players);                  // Position 1 bleibt fix
    $rotating = $players;                               // $m-1 Elemente
    $rounds   = [];
    for ($r = 0; $r < $m - 1; $r++) {
        $matches = [];
        $bye     = null;
        $pairs   = [[$fixed, $rotating[0]]];
        for ($k = 1; $k < $half; $k++) {
            $pairs[] = [$rotating[$k], $rotating[$m - 1 - $k]];
        }
        foreach ($pairs as [$p1, $p2]) {
            if ($p1 === null)      { $bye = $p2; continue; }
            elseif ($p2 === null)  { $bye = $p1; continue; }
            if ($pos[$p1] > $pos[$p2]) [$p1, $p2] = [$p2, $p1]; // niedrigere Position = Heim
            $matches[] = [$p1, $p2];
        }
        // Reihenfolge innerhalb der Runde: nach niedrigster beteiligter Position
        usort($matches, fn($a, $b) => $pos[$a[0]] <=> $pos[$b[0]]);
        $rounds[] = ['matches' => $matches, 'bye' => $bye];
        array_unshift($rotating, array_pop($rotating)); // Rotation
    }
    return $rounds;
}

/**
 * Reiht Runden in natürlicher Reihenfolge aneinander (ohne Back-to-Back-Optimierung).
 * Für den positionsbasierten Spielplan, damit die Reihenfolge exakt der Position folgt.
 */
function rr_flatten_plain(array $rounds): array {
    $matches = [];
    $byes    = [];
    $ri      = 0;
    foreach ($rounds as $round) {
        $ri++;
        foreach ($round['matches'] as [$a, $b]) {
            $matches[] = ['p1' => $a, 'p2' => $b, 'round' => $ri];
        }
        $byes[$ri] = $round['bye'];
    }
    return ['matches' => $matches, 'byes' => $byes];
}
