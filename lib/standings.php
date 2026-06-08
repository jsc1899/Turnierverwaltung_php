<?php

function _apply_h2h_tiebreaker(array $standings, array $matches, string $p1_col, string $p2_col): array {
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
        $mini   = array_fill_keys($ids, ['points' => 0, 'goal_diff' => 0, 'goals_for' => 0]);

        foreach ($matches as $m) {
            $p1 = $m[$p1_col]; $p2 = $m[$p2_col];
            if (!isset($id_set[$p1]) || !isset($id_set[$p2])) continue;
            $s1 = (int)$m['score1']; $s2 = (int)$m['score2'];
            $mini[$p1]['goals_for'] += $s1;  $mini[$p1]['goal_diff'] += $s1 - $s2;
            $mini[$p2]['goals_for'] += $s2;  $mini[$p2]['goal_diff'] += $s2 - $s1;
            if      ($s1 > $s2) { $mini[$p1]['points'] += 2; }
            elseif  ($s2 > $s1) { $mini[$p2]['points'] += 2; }
            else                { $mini[$p1]['points']++; $mini[$p2]['points']++; }
        }

        // h2h-Punkte → h2h-Tordiff → h2h-Tore → Gesamt-Tordiff → Gesamt-Tore
        usort($group, function($a, $b) use ($mini) {
            $ma = $mini[$a['id']]; $mb = $mini[$b['id']];
            if ($mb['points']    !== $ma['points'])    return $mb['points']    - $ma['points'];
            if ($mb['goal_diff'] !== $ma['goal_diff']) return $mb['goal_diff'] - $ma['goal_diff'];
            if ($mb['goals_for'] !== $ma['goals_for']) return $mb['goals_for'] - $ma['goals_for'];
            if ($b['goal_diff']  !== $a['goal_diff'])  return $b['goal_diff']  - $a['goal_diff'];
            return $b['goals_for'] - $a['goals_for'];
        });

        foreach ($group as $row) $final[] = $row;
    }
    return $final;
}

function group_standings(int $group_id): array {
    $players = db_fetchall(
        "SELECT p.id, TRIM(CONCAT(p.name, IF(p.firstname != '', CONCAT(' ', p.firstname), ''))) as name,
         p.club, COALESCE(cp.skill, p.skill, 0) as skill
         FROM player p
         JOIN group_player gp ON gp.player_id = p.id AND gp.group_id = ?
         LEFT JOIN competition_player cp ON cp.player_id = p.id
           AND cp.competition_id = (SELECT competition_id FROM grp WHERE id = ?)",
        [$group_id, $group_id]
    );
    $matches = db_fetchall(
        "SELECT * FROM `match` WHERE group_id = ? AND played = 1", [$group_id]
    );

    $stats = [];
    foreach ($players as $p) {
        $stats[$p['id']] = [
            'id' => $p['id'], 'name' => $p['name'], 'club' => $p['club'], 'skill' => $p['skill'],
            'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
            'goals_for' => 0, 'goals_against' => 0, 'points' => 0,
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
            $stats[$p1]['wins']++;  $stats[$p1]['points'] += 2; $stats[$p2]['losses']++;
        } elseif ($s2 > $s1) {
            $stats[$p2]['wins']++;  $stats[$p2]['points'] += 2; $stats[$p1]['losses']++;
        } else {
            $stats[$p1]['draws']++; $stats[$p1]['points']++;
            $stats[$p2]['draws']++; $stats[$p2]['points']++;
        }
    }

    $result = array_values($stats);
    foreach ($result as &$r) {
        $r['goal_diff'] = $r['goals_for'] - $r['goals_against'];
    }
    usort($result, fn($a, $b) => $b['points'] - $a['points']);
    return _apply_h2h_tiebreaker($result, $matches, 'player1_id', 'player2_id');
}

function double_standings(int $group_id): array {
    $doubles = db_fetchall(
        "SELECT d.id,
         TRIM(CONCAT(
           COALESCE(p1.firstname,''), IF(COALESCE(p1.firstname,'') != '', ' ', ''), p1.name,
           ' / ',
           COALESCE(p2.firstname,''), IF(COALESCE(p2.firstname,'') != '', ' ', ''), p2.name
         )) as name,
         '' as club, COALESCE(cd.skill, 0) as skill
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

    $stats = [];
    foreach ($doubles as $d) {
        $stats[$d['id']] = [
            'id' => $d['id'], 'name' => $d['name'], 'club' => $d['club'], 'skill' => $d['skill'],
            'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
            'goals_for' => 0, 'goals_against' => 0, 'points' => 0,
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
            $stats[$d1]['wins']++;  $stats[$d1]['points'] += 2; $stats[$d2]['losses']++;
        } elseif ($s2 > $s1) {
            $stats[$d2]['wins']++;  $stats[$d2]['points'] += 2; $stats[$d1]['losses']++;
        } else {
            $stats[$d1]['draws']++; $stats[$d1]['points']++;
            $stats[$d2]['draws']++; $stats[$d2]['points']++;
        }
    }

    $result = array_values($stats);
    foreach ($result as &$r) {
        $r['goal_diff'] = $r['goals_for'] - $r['goals_against'];
    }
    usort($result, fn($a, $b) => $b['points'] - $a['points']);
    return _apply_h2h_tiebreaker($result, $matches, 'double1_id', 'double2_id');
}
