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

    // "Nach Position": deterministischer Spielplan (immer nach dem gleichen Schema, kein Zufall),
    // dessen Paarungsstruktur der Teilnehmerreihenfolge (= Gruppenposition) folgt. Über zwei
    // deterministische Nachläufe wird bewusst verteilt: rr_sequence_position ordnet die Spiele so,
    // dass keine Mannschaft immer das erste Spiel der Runde hat und Back-to-Back vermieden wird;
    // rr_balance_sides gleicht aus, wie oft jede Mannschaft zuerst genannt wird (p1/p2).
    if ($mode === 'position') {
        $seq = rr_sequence_position(rr_build_position($player_ids, $force_bye));
        $seq['matches'] = rr_balance_sides($seq['matches']);
        return rr_apply_limit($seq, $round_limit);
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
 * Die Teilnehmerreihenfolge $player_ids entspricht der Gruppenposition (1, 2, 3, …). Liefert die
 * reine Paarungsstruktur je Runde (immer dasselbe Schema für dieselben Positionen). Die
 * Verteilung von Spielreihenfolge und Seitenwahl übernehmen rr_sequence_position/rr_balance_sides.
 * Basis-Seitenwahl: niedrigere Position = p1 (wird später ausgeglichen). Rundenreihenfolge:
 * natürliche Kreismethoden-Folge.
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

    $fixed    = array_shift($players);                  // Drehpunkt (Kreismethode)
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
            if ($pos[$p1] > $pos[$p2]) [$p1, $p2] = [$p2, $p1]; // Basis: niedrigere Position = p1
            $matches[] = [$p1, $p2];
        }
        $rounds[] = ['matches' => $matches, 'bye' => $bye];
        array_unshift($rotating, array_pop($rotating)); // Rotation
    }
    return $rounds;
}

/**
 * Deterministische Sequenzierung des positionsbasierten Spielplans (kein Zufall).
 * Erzeugt aus einigen deterministischen Startpunkten (Runden-Rotationen) je eine gut verteilte
 * Startlösung (rr_seq_start), optimiert sie lokal (rr_local_improve) und behält die beste: zuerst
 * möglichst wenig Back-to-Back, dann möglichst gleichmäßig verteiltes „erstes Spiel der Runde" —
 * damit nicht immer dieselbe Mannschaft (z.B. Position 1) das erste Spiel hat. Reproduzierbar für
 * dieselben Positionen. Liefert ['matches' => [...], 'byes' => [...]].
 */
function rr_sequence_position(array $rounds): array {
    $R = count($rounds);
    // Wenige Startpunkte genügen und halten auch große Gruppen schnell (Rundenreihenfolge unter den
    // Kreismethoden-Runden ist beliebig, daher ist das Rotieren als Startvariante zulässig).
    $starts = min(max(1, $R), 8);
    $best = null; $bestScore = null;
    for ($rot = 0; $rot < $starts; $rot++) {
        $rot_rounds = [];
        for ($k = 0; $k < $R; $k++) $rot_rounds[] = $rounds[($rot + $k) % $R];
        $sol = rr_local_improve(rr_seq_start($rot_rounds));
        $sc  = rr_score_seq($sol);
        if ($bestScore === null || $sc < $bestScore) { $bestScore = $sc; $best = $sol; }
    }

    $matches = []; $byes = []; $rno = 0;
    foreach ($best as $o) {
        $rno++;
        foreach ($o['matches'] as [$a, $b]) $matches[] = ['p1' => $a, 'p2' => $b, 'round' => $rno];
        $byes[$rno] = $o['bye'];
    }
    return ['matches' => $matches, 'byes' => $byes];
}

/**
 * Erzeugt eine gut verteilte Start-Sequenz (Liste von ['matches' => geordnet, 'bye' => …]) in zwei
 * schnellen, deterministischen Phasen:
 *  - Phase 1: je Runde das „erste Spiel" ausgeglichen wählen — die Paarung, deren beide Mannschaften
 *    bisher am seltensten das erste Spiel einer Runde hatten.
 *  - Phase 2: je Runde das „letzte Spiel" so wählen, dass es keine Mannschaft des bereits gewählten
 *    ersten Spiels der nächsten Runde enthält → kein Back-to-Back am Rundenübergang. Die mittleren
 *    Spiele sind für Back-to-Back irrelevant und bleiben in natürlicher Reihenfolge.
 */
function rr_seq_start(array $rounds): array {
    $R    = count($rounds);
    $conf = fn($x, $y) => $x[0] === $y[0] || $x[0] === $y[1] || $x[1] === $y[0] || $x[1] === $y[1];

    $firstIdx = []; $firstCnt = [];
    foreach ($rounds as $ri => $round) {
        $ms = $round['matches'];
        if (!$ms) { $firstIdx[$ri] = -1; continue; }
        $bi = 0; $bk = null;
        foreach ($ms as $i => [$a, $b]) {
            $k = [($firstCnt[$a] ?? 0) + ($firstCnt[$b] ?? 0), max($firstCnt[$a] ?? 0, $firstCnt[$b] ?? 0), $i];
            if ($bk === null || $k < $bk) { $bk = $k; $bi = $i; }
        }
        $firstIdx[$ri] = $bi;
        $firstCnt[$ms[$bi][0]] = ($firstCnt[$ms[$bi][0]] ?? 0) + 1;
        $firstCnt[$ms[$bi][1]] = ($firstCnt[$ms[$bi][1]] ?? 0) + 1;
    }

    $sol = [];
    foreach ($rounds as $ri => $round) {
        $ms = array_values($round['matches']);
        if (count($ms) > 1) {
            $fi    = $firstIdx[$ri];
            $first = $ms[$fi];
            array_splice($ms, $fi, 1);                       // $ms = restliche Spiele
            $nextFirst = null;
            if ($ri + 1 < $R && ($firstIdx[$ri + 1] ?? -1) >= 0) {
                $nm = $rounds[$ri + 1]['matches'];
                $nextFirst = $nm[$firstIdx[$ri + 1]];
            }
            $bj = 0; $bk = null;
            foreach ($ms as $j => $m) {
                $k = [($nextFirst && $conf($m, $nextFirst)) ? 1 : 0, $j];
                if ($bk === null || $k < $bk) { $bk = $k; $bj = $j; }
            }
            $last = $ms[$bj];
            array_splice($ms, $bj, 1);
            $ms = array_merge([$first], $ms, [$last]);
        }
        $sol[] = ['matches' => $ms, 'bye' => $round['bye']];
    }
    return $sol;
}

/**
 * Bewertet eine Sequenz (Liste von ['matches' => geordnete Paarliste, 'bye' => …]) lexikografisch:
 * [Anzahl Back-to-Back, Ausgeglichenheit „erstes Spiel der Runde"]. Kleiner = besser.
 * Für die Ausgeglichenheit wird die Summe der Quadrate der Erst-Zähler verwendet (nicht max−min):
 * sie liefert der lokalen Suche einen glatten Gradienten und drängt so aktiv zu einer gleichmäßigen
 * Verteilung, während max−min oft flach ist und keine Verbesserung anzeigt.
 */
function rr_score_seq(array $sol): array {
    $flat = [];
    foreach ($sol as $o) foreach ($o['matches'] as $m) $flat[] = $m;
    $b2b = 0;
    for ($i = 1, $n = count($flat); $i < $n; $i++) {
        $a = $flat[$i - 1]; $c = $flat[$i];
        if ($a[0] === $c[0] || $a[0] === $c[1] || $a[1] === $c[0] || $a[1] === $c[1]) $b2b++;
    }
    $fc = [];
    foreach ($sol as $o) {
        if (!$o['matches']) continue;
        $m = $o['matches'][0];
        $fc[$m[0]] = ($fc[$m[0]] ?? 0) + 1;
        $fc[$m[1]] = ($fc[$m[1]] ?? 0) + 1;
    }
    $sumSq = 0;
    foreach ($fc as $c) $sumSq += $c * $c;
    return [$b2b, $sumSq];
}

/**
 * Deterministische lokale Optimierung: probiert je Runde alle Zuordnungen von „erstem" und
 * „letztem" Spiel durch (die Reihenfolge der mittleren Spiele ist für Back-to-Back irrelevant) und
 * übernimmt jeweils den besten verbessernden Zug (nach rr_score_seq), bis keine Verbesserung mehr
 * möglich ist. Kein Zufall, feste Durchlaufreihenfolge → dasselbe Ergebnis für dieselbe Eingabe.
 */
function rr_local_improve(array $sol): array {
    $cur = rr_score_seq($sol);
    for ($guard = 0; $guard < 2000; $guard++) {
        $bestScore = $cur; $bestRi = -1; $bestLine = null;
        foreach ($sol as $ri => $round) {
            $ms = $round['matches']; $n = count($ms);
            if ($n < 2) continue;
            for ($f = 0; $f < $n; $f++) {
                for ($l = 0; $l < $n; $l++) {
                    if ($f === $l) continue;
                    $rest = [];
                    for ($k = 0; $k < $n; $k++) if ($k !== $f && $k !== $l) $rest[] = $ms[$k];
                    $line = array_merge([$ms[$f]], $rest, [$ms[$l]]);
                    if ($line === $ms) continue;                     // keine Änderung
                    $trial = $sol; $trial[$ri]['matches'] = $line;
                    $sc = rr_score_seq($trial);
                    if ($sc < $bestScore) { $bestScore = $sc; $bestRi = $ri; $bestLine = $line; }
                }
            }
        }
        if ($bestRi < 0) break;                                     // kein verbessernder Zug mehr
        $sol[$bestRi]['matches'] = $bestLine; $cur = $bestScore;
    }
    return $sol;
}

/**
 * Gleicht deterministisch aus, wie oft jede Mannschaft zuerst genannt wird (p1 vs p2): geht die
 * Spiele in Reihenfolge durch und stellt jeweils die Mannschaft nach vorne, die bisher seltener
 * p1 war (bzw. öfter p2). Bei Gleichstand bleibt die bestehende Reihenfolge (niedrigere Position
 * zuerst). Ändert nur die Nennungsseite, nicht die Paarungen oder die Spielreihenfolge.
 */
function rr_balance_sides(array $matches): array {
    $net = [];   // Team => (#p1 − #p2) bisher; hoher Wert = war öfter zuerst
    foreach ($matches as &$m) {
        $a = $m['p1']; $b = $m['p2'];
        // Team mit niedrigerem net-Wert (seltener zuerst) soll p1 werden.
        if (($net[$b] ?? 0) < ($net[$a] ?? 0)) { [$a, $b] = [$b, $a]; }
        $m['p1'] = $a; $m['p2'] = $b;
        $net[$a] = ($net[$a] ?? 0) + 1;
        $net[$b] = ($net[$b] ?? 0) - 1;
    }
    unset($m);
    return $matches;
}
