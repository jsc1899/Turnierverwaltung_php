<?php

// Spielplatz-Zuordnung (Courts).
// competition.num_courts = Anzahl Plätze (0 = aus). Plätze sind an Gruppen gebunden
// (grp.courts = komma-separierte Liste); Begegnungen rotieren über die Plätze ihrer Gruppe.
// KO-Spiele werden aus dem gesamten Pool 1..N zugewiesen (court = ko_position % N + 1).

/**
 * Gleichmäßige Standard-Verteilung der Plätze auf die Gruppen (zusammenhängende Blöcke).
 * @return array Liste je Gruppenindex mit Platznummern, z.B. (6,3) → [[1,2],[3,4],[5,6]].
 */
function default_group_courts(int $num_courts, int $num_groups, int $start = 1): array {
    if ($num_groups <= 0) return [];
    if ($num_courts <= 0) return array_fill(0, $num_groups, []);
    if ($start < 1) $start = 1;

    $res = [];
    if ($num_courts >= $num_groups) {
        $base = intdiv($num_courts, $num_groups);
        $rem  = $num_courts % $num_groups;   // Rest auf die ersten Gruppen
        $c = $start;
        for ($i = 0; $i < $num_groups; $i++) {
            $cnt = $base + ($i < $rem ? 1 : 0);
            $block = [];
            for ($j = 0; $j < $cnt; $j++) $block[] = $c++;
            $res[] = $block;
        }
    } else {
        // Weniger Plätze als Gruppen → Gruppen teilen sich Plätze zyklisch.
        for ($i = 0; $i < $num_groups; $i++) $res[] = [($i % $num_courts) + $start];
    }
    return $res;
}

/**
 * Parst eine Platzliste (komma/whitespace-getrennt), filtert auf den gültigen Bereich
 * start .. start+num_courts-1, entfernt Duplikate und sortiert aufsteigend.
 */
function parse_courts(string $s, int $num_courts, int $start = 1): array {
    if ($start < 1) $start = 1;
    $hi = $start + $num_courts - 1;
    $parts = preg_split('/[^0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $set = [];
    foreach ($parts as $p) {
        $n = (int)$p;
        if ($n >= $start && $n <= $hi) $set[$n] = true;
    }
    $out = array_keys($set);
    sort($out);
    return $out;
}

/**
 * Weist allen Begegnungen eines Bewerbs ihre Platznummer zu (idempotent).
 * Nach jedem (Neu-)Aufbau der Matches und nach Einstellungsänderungen aufrufen.
 */
function assign_courts(int $cid): void {
    $c = db_fetch("SELECT num_courts, court_start FROM competition WHERE id=?", [$cid]);
    $N = $c ? (int)$c['num_courts'] : 0;
    $S = $c ? max(1, (int)($c['court_start'] ?? 1)) : 1;   // Start-Platznummer

    if ($N <= 0) {
        db_execute("UPDATE `match` SET court_no=NULL WHERE competition_id=?", [$cid]);
        return;
    }

    // Gruppenspiele: je Gruppe Plätze (manuell oder Default), Begegnungen rotierend.
    $groups   = db_fetchall("SELECT id, courts FROM grp WHERE competition_id=? ORDER BY name", [$cid]);
    $defaults = default_group_courts($N, count($groups), $S);
    foreach ($groups as $idx => $g) {
        $courts = parse_courts((string)($g['courts'] ?? ''), $N, $S);
        if (!$courts) $courts = $defaults[$idx] ?? [];
        $matches = db_fetchall("SELECT id FROM `match` WHERE group_id=? ORDER BY match_order, id", [$g['id']]);
        $cnt = count($courts);
        foreach ($matches as $k => $m) {
            $court = $cnt ? $courts[$k % $cnt] : null;
            db_execute("UPDATE `match` SET court_no=? WHERE id=?", [$court, $m['id']]);
        }
    }

    // KO-Spiele (group_id IS NULL): Pool S..S+N-1, je Runde ab Start-Platz (Finale = Start-Platz).
    // Einzel-KO und Doppel-KO (bracket NULL/W/L/GF): jedes Bracket eigenständig.
    db_execute(
        "UPDATE `match` SET court_no = (ko_position % ?) + ?
         WHERE competition_id=? AND group_id IS NULL AND ko_position IS NOT NULL
           AND (bracket IS NULL OR bracket NOT LIKE 'C%')",
        [$N, $S, $cid]
    );

    // Platzierungs-Brackets / Kreuzspiele (bracket 'C%'): Alle Blöcke einer Runde spielen
    // gleichzeitig → Spielplätze über die Blöcke hinweg FORTLAUFEND nummerieren, nicht je
    // Block wieder bei 1 (z.B. Block „Plätze 1–8" Runde 1 → Plätze 1–4, Block „Plätze 9–16"
    // Runde 1 → Plätze 5–8 …). Reihenfolge: pro Runde nach numerischem Block-Index, dann Position.
    $pl = db_fetchall(
        "SELECT id, ko_round FROM `match`
         WHERE competition_id=? AND group_id IS NULL AND bracket LIKE 'C%' AND ko_position IS NOT NULL
         ORDER BY ko_round, CAST(SUBSTRING(bracket, 2) AS UNSIGNED), ko_position",
        [$cid]
    );
    $cur_round = null; $i = 0;
    foreach ($pl as $m) {
        if ((int)$m['ko_round'] !== $cur_round) { $cur_round = (int)$m['ko_round']; $i = 0; }
        db_execute("UPDATE `match` SET court_no=? WHERE id=?", [($i % $N) + $S, $m['id']]);
        $i++;
    }
}
