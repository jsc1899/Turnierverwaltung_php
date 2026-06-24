<?php

// Gibt IDs aller Teilnehmer zurück, die am offenen Gleichstand an der Aufstiegsgrenze beteiligt sind.
// Leeres Array = kein relevanter offener Gleichstand (auch wenn Direktvergleich bereits auflöst).
// $matches: gespielte Gruppenspiele; $p1_col/$p2_col: Spaltenname der Teilnehmer-ID im Match-Array.
// Prüft eine einzelne Grenzposition auf echten Gleichstand.
// Gibt IDs aller beteiligten Teilnehmer zurück, oder [].
function _tied_ids_at_pos(array $standings, int $boundary, array $matches, string $p1_col, string $p2_col, array $pts = [2, 1, 0]): array {
    [$pw, $pd, $plo] = $pts;
    if ($boundary < 0 || $boundary + 1 >= count($standings)) return [];
    $a = $standings[$boundary];
    $b = $standings[$boundary + 1];
    if ($a['points'] !== $b['points']
        || $a['goal_diff'] !== $b['goal_diff']
        || $a['goals_for'] !== $b['goals_for']
        || ($a['tiebreak_order'] ?? null) !== null
        || ($b['tiebreak_order'] ?? null) !== null) {
        return [];
    }
    $ref = ['points' => $a['points'], 'goal_diff' => $a['goal_diff'], 'goals_for' => $a['goals_for']];
    $above = [];
    $below = [];
    for ($i = $boundary; $i >= 0; $i--) {
        $p = $standings[$i];
        if ($p['points'] === $ref['points'] && $p['goal_diff'] === $ref['goal_diff']
            && $p['goals_for'] === $ref['goals_for'] && ($p['tiebreak_order'] ?? null) === null) {
            $above[] = $p;
        } else break;
    }
    for ($i = $boundary + 1; $i < count($standings); $i++) {
        $p = $standings[$i];
        if ($p['points'] === $ref['points'] && $p['goal_diff'] === $ref['goal_diff']
            && $p['goals_for'] === $ref['goals_for'] && ($p['tiebreak_order'] ?? null) === null) {
            $below[] = $p;
        } else break;
    }
    $all_ids = array_merge(array_column($above, 'id'), array_column($below, 'id'));
    if (empty($matches)) return $all_ids;

    $id_set = array_flip($all_ids);
    $mini   = array_fill_keys($all_ids, ['points' => 0, 'goal_diff' => 0, 'goals_for' => 0]);
    foreach ($matches as $m) {
        if (!(int)($m['played'] ?? 0)) continue;
        $p1 = $m[$p1_col] ?? null; $p2 = $m[$p2_col] ?? null;
        if (!isset($id_set[$p1]) || !isset($id_set[$p2])) continue;
        $s1 = (int)$m['score1']; $s2 = (int)$m['score2'];
        $mini[$p1]['goals_for'] += $s1; $mini[$p1]['goal_diff'] += $s1 - $s2;
        $mini[$p2]['goals_for'] += $s2; $mini[$p2]['goal_diff'] += $s2 - $s1;
        if      ($s1 > $s2) { $mini[$p1]['points'] += $pw;  $mini[$p2]['points'] += $plo; }
        elseif  ($s2 > $s1) { $mini[$p2]['points'] += $pw;  $mini[$p1]['points'] += $plo; }
        else                { $mini[$p1]['points'] += $pd;  $mini[$p2]['points'] += $pd; }
    }

    foreach ($above as $ap) {
        foreach ($below as $bp) {
            $ma = $mini[$ap['id']]; $mb = $mini[$bp['id']];
            if ($ma['points']    !== $mb['points'])    continue;
            if ($ma['goal_diff'] !== $mb['goal_diff']) continue;
            if ($ma['goals_for'] !== $mb['goals_for']) continue;
            if ($ap['goal_diff'] !== $bp['goal_diff']) continue;
            if ($ap['goals_for'] !== $bp['goals_for']) continue;
            if (($ap['einzel_diff'] ?? 0) !== ($bp['einzel_diff'] ?? 0)) continue;
            if (($ap['einzel_for']  ?? 0) !== ($bp['einzel_for']  ?? 0)) continue;
            return $all_ids;
        }
    }
    return [];
}

// Gibt IDs aller Teilnehmer zurück, die an einem relevanten offenen Gleichstand beteiligt sind.
// Prüft alle Grenzen innerhalb der Aufstiegszone (Setzung) sowie die Aufstiegsgrenze selbst.
function tied_ids_at_boundary(array $standings, int $advance_count, array $matches = [], string $p1_col = 'player1_id', string $p2_col = 'player2_id', array $pts = [2, 1, 0]): array {
    if ($advance_count <= 0 || $advance_count >= count($standings)) return [];
    $all_ids = [];
    // Alle relevanten Grenzen: 0..advance_count-2 (Setzung) + advance_count-1 (Aufstieg)
    for ($boundary = 0; $boundary < $advance_count; $boundary++) {
        $ids = _tied_ids_at_pos($standings, $boundary, $matches, $p1_col, $p2_col, $pts);
        foreach ($ids as $id) $all_ids[$id] = $id;
    }
    return array_values($all_ids);
}

function _has_open_tie(array $standings, int $advance_count = 0): bool {
    return !empty(tied_ids_at_boundary($standings, $advance_count));
}

// Tabellenreihungs-Modus des Bewerbs (Bewerbsoption): 'diff' oder 'h2h' (Default).
function _standings_order(int $group_id): string {
    $r = db_fetch("SELECT standings_order FROM competition WHERE id=(SELECT competition_id FROM grp WHERE id=?)", [$group_id]);
    return ($r && ($r['standings_order'] ?? '') === 'diff') ? 'diff' : 'h2h';
}

// Punktevergabe-Modus (Bewerbsoption) → [Sieg, Unentschieden, Niederlage].
// '2-1-0' (Default), '3-1-0', '3-2-1'.
function _parse_points_mode(?string $mode): array {
    return [
        '3-1-0' => [3, 1, 0],
        '3-2-1' => [3, 2, 1],
    ][$mode ?? ''] ?? [2, 1, 0];
}

// Punktevergabe einer Gruppe (über den Bewerb) → [Sieg, Unentschieden, Niederlage].
function _points_for(int $group_id): array {
    $r = db_fetch("SELECT points_mode FROM competition WHERE id=(SELECT competition_id FROM grp WHERE id=?)", [$group_id]);
    return _parse_points_mode($r['points_mode'] ?? null);
}

// $order_mode: 'h2h'  → Punkte → Direktvergleich → Gesamt-Differenz (Standard)
//              'diff' → Punkte → Gesamt-Differenz → Direktvergleich (Direktduell zuletzt)
function _apply_h2h_tiebreaker(array $standings, array $matches, string $p1_col, string $p2_col, array $duels = [], string $order_mode = 'h2h', array $pts = [2, 1, 0]): array {
    [$pw, $pd, $plo] = $pts;
    $by_points = [];
    foreach ($standings as $row) {
        $by_points[$row['points']][] = $row;
    }
    krsort($by_points);

    $final = [];
    foreach ($by_points as $group) {
        if (count($group) === 1) { $final[] = $group[0]; continue; }

        $ids    = array_column($group, 'id');
        $id_set = array_flip($ids);
        $mini   = array_fill_keys($ids, ['points' => 0, 'goal_diff' => 0, 'goals_for' => 0, 'einzel_diff' => 0, 'einzel_for' => 0]);

        foreach ($matches as $m) {
            $p1 = $m[$p1_col]; $p2 = $m[$p2_col];
            if (!isset($id_set[$p1]) || !isset($id_set[$p2])) continue;
            $s1 = (int)$m['score1']; $s2 = (int)$m['score2'];
            $mini[$p1]['goals_for'] += $s1;  $mini[$p1]['goal_diff'] += $s1 - $s2;
            $mini[$p2]['goals_for'] += $s2;  $mini[$p2]['goal_diff'] += $s2 - $s1;
            if      ($s1 > $s2) { $mini[$p1]['points'] += $pw;  $mini[$p2]['points'] += $plo; }
            elseif  ($s2 > $s1) { $mini[$p2]['points'] += $pw;  $mini[$p1]['points'] += $plo; }
            else                { $mini[$p1]['points'] += $pd;  $mini[$p2]['points'] += $pd; }
        }

        foreach ($duels as $d) {
            $p1 = $d[$p1_col] ?? null; $p2 = $d[$p2_col] ?? null;
            if (!isset($id_set[$p1]) || !isset($id_set[$p2])) continue;
            $s1 = (int)$d['score1']; $s2 = (int)$d['score2'];
            $mini[$p1]['einzel_for'] += $s1; $mini[$p1]['einzel_diff'] += $s1 - $s2;
            $mini[$p2]['einzel_for'] += $s2; $mini[$p2]['einzel_diff'] += $s2 - $s1;
        }

        // Zwei Kriterien-Blöcke: Direktvergleich (h2h, Mini-Tabelle der Gleichpunktigen) und
        // Gesamt-Differenz (alle Gruppenspiele). Reihenfolge je nach $order_mode; danach Los/manuell.
        $diff_first = ($order_mode === 'diff');
        usort($group, function($a, $b) use ($mini, $diff_first) {
            $ma = $mini[$a['id']]; $mb = $mini[$b['id']];
            // Direktvergleich: Punkte → Tordiff → Einzeldiff → Tore → Einzel
            $h2h = [
                $mb['points']      - $ma['points'],
                $mb['goal_diff']   - $ma['goal_diff'],
                $mb['einzel_diff'] - $ma['einzel_diff'],
                $mb['goals_for']   - $ma['goals_for'],
                $mb['einzel_for']  - $ma['einzel_for'],
            ];
            // Gesamt: Tordiff → Einzeldiff → Tore → Einzel
            $overall = [
                $b['goal_diff']          - $a['goal_diff'],
                ($b['einzel_diff'] ?? 0) - ($a['einzel_diff'] ?? 0),
                $b['goals_for']          - $a['goals_for'],
                ($b['einzel_for']  ?? 0) - ($a['einzel_for']  ?? 0),
            ];
            $cascade = $diff_first ? array_merge($overall, $h2h) : array_merge($h2h, $overall);
            foreach ($cascade as $cmp) if ($cmp !== 0) return $cmp;
            return ($a['tiebreak_order'] ?? 9999) <=> ($b['tiebreak_order'] ?? 9999);
        });

        foreach ($group as $row) $final[] = $row;
    }
    return $final;
}

function group_standings(int $group_id, string $seeding_order = 'desc', string $score_mode = 'match'): array {
    $players = db_fetchall(
        "SELECT p.id, TRIM(CONCAT(p.name, IF(p.firstname != '', CONCAT(' ', p.firstname), ''))) as name,
         p.club, COALESCE(cp.skill, p.skill, 0) as skill, gp.tiebreak_order
         FROM player p
         JOIN group_player gp ON gp.player_id = p.id AND gp.group_id = ?
         LEFT JOIN competition_player cp ON cp.player_id = p.id
           AND cp.competition_id = (SELECT competition_id FROM grp WHERE id = ?)",
        [$group_id, $group_id]
    );
    $matches = db_fetchall(
        "SELECT * FROM `match` WHERE group_id = ? AND played = 1", [$group_id]
    );
    $pts = _points_for($group_id);
    [$pw, $pd, $plo] = $pts;

    $stats = [];
    foreach ($players as $p) {
        $stats[$p['id']] = [
            'id' => $p['id'], 'name' => $p['name'], 'club' => $p['club'], 'skill' => $p['skill'],
            'tiebreak_order' => $p['tiebreak_order'],
            'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
            'goals_for' => 0, 'goals_against' => 0, 'points' => 0,
            'einzel_for' => 0, 'einzel_against' => 0, 'einzel_diff' => 0,
        ];
    }

    foreach ($matches as $m) {
        $p1 = $m['player1_id']; $p2 = $m['player2_id'];
        $s1 = $m['score1'];     $s2 = $m['score2'];
        if (!isset($stats[$p1]) || !isset($stats[$p2])) continue;

        $stats[$p1]['played']++;        $stats[$p2]['played']++;
        $stats[$p1]['goals_for'] += $s1; $stats[$p1]['goals_against'] += $s2;
        $stats[$p2]['goals_for'] += $s2; $stats[$p2]['goals_against'] += $s1;

        if ($s1 > $s2) {
            $stats[$p1]['wins']++;   $stats[$p1]['points'] += $pw;  $stats[$p2]['losses']++; $stats[$p2]['points'] += $plo;
        } elseif ($s2 > $s1) {
            $stats[$p2]['wins']++;   $stats[$p2]['points'] += $pw;  $stats[$p1]['losses']++; $stats[$p1]['points'] += $plo;
        } else {
            $stats[$p1]['draws']++;  $stats[$p1]['points'] += $pd;
            $stats[$p2]['draws']++;  $stats[$p2]['points'] += $pd;
        }
    }

    if ($score_mode === 'sets') {
        $set_rows = db_fetchall(
            "SELECT m.player1_id, m.player2_id, s.score1, s.score2
             FROM match_set s JOIN `match` m ON m.id = s.match_id
             WHERE m.group_id = ?",
            [$group_id]
        );
        foreach ($set_rows as $s) {
            $p1 = $s['player1_id']; $p2 = $s['player2_id'];
            if (!isset($stats[$p1]) || !isset($stats[$p2])) continue;
            $stats[$p1]['einzel_for']     += (int)$s['score1'];
            $stats[$p1]['einzel_against'] += (int)$s['score2'];
            $stats[$p2]['einzel_for']     += (int)$s['score2'];
            $stats[$p2]['einzel_against'] += (int)$s['score1'];
        }
    }

    $result = array_values($stats);
    foreach ($result as &$r) {
        $r['goal_diff']   = $r['goals_for'] - $r['goals_against'];
        $r['einzel_diff'] = $r['einzel_for'] - $r['einzel_against'];
    }
    usort($result, fn($a, $b) => $b['points'] - $a['points']);
    return _apply_h2h_tiebreaker($result, $matches, 'player1_id', 'player2_id', [], _standings_order($group_id), $pts);
}

function team_standings(int $group_id, string $seeding_order = 'desc', string $score_mode = 'match'): array {
    $teams = db_fetchall(
        "SELECT t.id, t.name, '' as club, COALESCE(ct.skill, 0) as skill, gt.tiebreak_order
         FROM `team` t
         JOIN group_team gt ON gt.team_id = t.id AND gt.group_id = ?
         LEFT JOIN competition_team ct ON ct.team_id = t.id
           AND ct.competition_id = (SELECT competition_id FROM grp WHERE id = ?)",
        [$group_id, $group_id]
    );
    $matches = db_fetchall(
        "SELECT * FROM `match` WHERE group_id = ? AND played = 1", [$group_id]
    );
    $pts = _points_for($group_id);
    [$pw, $pd, $plo] = $pts;

    $stats = [];
    foreach ($teams as $t) {
        $stats[$t['id']] = [
            'id' => $t['id'], 'name' => $t['name'], 'club' => $t['club'], 'skill' => $t['skill'],
            'tiebreak_order' => $t['tiebreak_order'],
            'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
            'goals_for' => 0, 'goals_against' => 0, 'points' => 0,
            'einzel_for' => 0, 'einzel_against' => 0, 'einzel_diff' => 0,
        ];
    }

    foreach ($matches as $m) {
        $t1 = $m['team1_id']; $t2 = $m['team2_id'];
        $s1 = $m['score1'];   $s2 = $m['score2'];
        if (!isset($stats[$t1]) || !isset($stats[$t2])) continue;

        $stats[$t1]['played']++;         $stats[$t2]['played']++;
        $stats[$t1]['goals_for'] += $s1; $stats[$t1]['goals_against'] += $s2;
        $stats[$t2]['goals_for'] += $s2; $stats[$t2]['goals_against'] += $s1;

        if ($s1 > $s2) {
            $stats[$t1]['wins']++;   $stats[$t1]['points'] += $pw;  $stats[$t2]['losses']++; $stats[$t2]['points'] += $plo;
        } elseif ($s2 > $s1) {
            $stats[$t2]['wins']++;   $stats[$t2]['points'] += $pw;  $stats[$t1]['losses']++; $stats[$t1]['points'] += $plo;
        } else {
            $stats[$t1]['draws']++;  $stats[$t1]['points'] += $pd;
            $stats[$t2]['draws']++;  $stats[$t2]['points'] += $pd;
        }
    }

    // Einzel-Differenz aus Duelergebnissen berechnen
    $duels = db_fetchall(
        "SELECT m.team1_id, m.team2_id, d.score1, d.score2
         FROM `match` m
         JOIN team_match_duel d ON d.match_id = m.id AND d.played = 1
         WHERE m.group_id = ?",
        [$group_id]
    );
    foreach ($duels as $d) {
        $t1 = $d['team1_id']; $t2 = $d['team2_id'];
        if (!isset($stats[$t1]) || !isset($stats[$t2])) continue;
        $s1 = (int)$d['score1']; $s2 = (int)$d['score2'];
        $stats[$t1]['einzel_for'] += $s1; $stats[$t1]['einzel_against'] += $s2;
        $stats[$t2]['einzel_for'] += $s2; $stats[$t2]['einzel_against'] += $s1;
    }

    $result = array_values($stats);
    foreach ($result as &$r) {
        $r['goal_diff']   = $r['goals_for'] - $r['goals_against'];
        $r['einzel_diff'] = $r['einzel_for'] - $r['einzel_against'];
    }
    usort($result, fn($a, $b) => $b['points'] - $a['points']);
    return _apply_h2h_tiebreaker($result, $matches, 'team1_id', 'team2_id', $duels, _standings_order($group_id), $pts);
}

/**
 * Vergibt jedem Team einer Gruppe eine Start-Nr. (1..N), sortiert nach Setzung/Spielstärke
 * (skill DESC, dann team_id). Rückgabe: [team_id => Nummer].
 */
function team_start_numbers(int $group_id): array {
    $rows = db_fetchall(
        "SELECT t.id
         FROM `team` t
         JOIN group_team gt ON gt.team_id = t.id AND gt.group_id = ?
         LEFT JOIN competition_team ct ON ct.team_id = t.id
           AND ct.competition_id = (SELECT competition_id FROM grp WHERE id = ?)
         ORDER BY COALESCE(ct.skill, 0) DESC, t.id",
        [$group_id, $group_id]
    );
    $map = [];
    $n = 0;
    foreach ($rows as $r) {
        $map[(int)$r['id']] = ++$n;
    }
    return $map;
}

function double_standings(int $group_id, string $seeding_order = 'desc', string $score_mode = 'match'): array {
    $doubles = db_fetchall(
        "SELECT d.id,
         TRIM(CONCAT(
           COALESCE(p1.firstname,''), IF(COALESCE(p1.firstname,'') != '', ' ', ''), p1.name,
           ' / ',
           COALESCE(p2.firstname,''), IF(COALESCE(p2.firstname,'') != '', ' ', ''), p2.name
         )) as name,
         COALESCE(p1.club,'') as p1club, COALESCE(p2.club,'') as p2club,
         COALESCE(cd.skill, 0) as skill, gd.tiebreak_order
         FROM `double` d
         JOIN player p1 ON p1.id = d.player1_id
         JOIN player p2 ON p2.id = d.player2_id
         JOIN group_double gd ON gd.double_id = d.id AND gd.group_id = ?
         LEFT JOIN competition_double cd ON cd.double_id = d.id
           AND cd.competition_id = (SELECT competition_id FROM grp WHERE id = ?)",
        [$group_id, $group_id]
    );
    $matches = db_fetchall(
        "SELECT * FROM `match` WHERE group_id = ? AND played = 1", [$group_id]
    );
    $pts = _points_for($group_id);
    [$pw, $pd, $plo] = $pts;

    foreach ($doubles as &$d) {
        $c1 = $d['p1club']; $c2 = $d['p2club'];
        if ($c1 === $c2) {
            $d['club'] = $c1;
        } elseif ($c1 !== '' && $c2 !== '') {
            $d['club'] = $c1 . ' / ' . $c2;
        } else {
            $d['club'] = $c1 . $c2;
        }
    }
    unset($d);

    $stats = [];
    foreach ($doubles as $d) {
        $stats[$d['id']] = [
            'id' => $d['id'], 'name' => $d['name'], 'club' => $d['club'], 'skill' => $d['skill'],
            'tiebreak_order' => $d['tiebreak_order'],
            'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
            'goals_for' => 0, 'goals_against' => 0, 'points' => 0,
            'einzel_for' => 0, 'einzel_against' => 0, 'einzel_diff' => 0,
        ];
    }

    foreach ($matches as $m) {
        $d1 = $m['double1_id']; $d2 = $m['double2_id'];
        $s1 = $m['score1'];     $s2 = $m['score2'];
        if (!isset($stats[$d1]) || !isset($stats[$d2])) continue;

        $stats[$d1]['played']++;         $stats[$d2]['played']++;
        $stats[$d1]['goals_for'] += $s1; $stats[$d1]['goals_against'] += $s2;
        $stats[$d2]['goals_for'] += $s2; $stats[$d2]['goals_against'] += $s1;

        if ($s1 > $s2) {
            $stats[$d1]['wins']++;   $stats[$d1]['points'] += $pw;  $stats[$d2]['losses']++; $stats[$d2]['points'] += $plo;
        } elseif ($s2 > $s1) {
            $stats[$d2]['wins']++;   $stats[$d2]['points'] += $pw;  $stats[$d1]['losses']++; $stats[$d1]['points'] += $plo;
        } else {
            $stats[$d1]['draws']++;  $stats[$d1]['points'] += $pd;
            $stats[$d2]['draws']++;  $stats[$d2]['points'] += $pd;
        }
    }

    if ($score_mode === 'sets') {
        $set_rows = db_fetchall(
            "SELECT m.double1_id, m.double2_id, s.score1, s.score2
             FROM match_set s JOIN `match` m ON m.id = s.match_id
             WHERE m.group_id = ?",
            [$group_id]
        );
        foreach ($set_rows as $s) {
            $d1 = $s['double1_id']; $d2 = $s['double2_id'];
            if (!isset($stats[$d1]) || !isset($stats[$d2])) continue;
            $stats[$d1]['einzel_for']     += (int)$s['score1'];
            $stats[$d1]['einzel_against'] += (int)$s['score2'];
            $stats[$d2]['einzel_for']     += (int)$s['score2'];
            $stats[$d2]['einzel_against'] += (int)$s['score1'];
        }
    }

    $result = array_values($stats);
    foreach ($result as &$r) {
        $r['goal_diff']   = $r['goals_for'] - $r['goals_against'];
        $r['einzel_diff'] = $r['einzel_for'] - $r['einzel_against'];
    }
    usort($result, fn($a, $b) => $b['points'] - $a['points']);
    return _apply_h2h_tiebreaker($result, $matches, 'double1_id', 'double2_id', [], _standings_order($group_id), $pts);
}
