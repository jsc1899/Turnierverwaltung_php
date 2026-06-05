<?php

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
    usort($result, function($a, $b) {
        if ($b['points']    !== $a['points'])    return $b['points']    - $a['points'];
        if ($b['goal_diff'] !== $a['goal_diff']) return $b['goal_diff'] - $a['goal_diff'];
        return $b['goals_for'] - $a['goals_for'];
    });
    return $result;
}
