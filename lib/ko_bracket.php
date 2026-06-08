<?php

function next_power_of_2(int $n): int {
    if ($n <= 1) return 2;
    return (int)(2 ** ceil(log($n, 2)));
}

// Returns cap player-slot indices in seeding priority order.
// Index 0 = S1 (slot 0, absolute top), index 1 = S2 (slot cap-1, absolute bottom).
// S3/S4 are randomly placed in the centre of each half; S5–S8 in quarter centres; etc.
function seeded_player_slots(int $cap): array {
    $result = [0, $cap - 1];
    $added  = [0 => true, $cap - 1 => true];
    $step   = $cap >> 1;
    while ($step >= 2) {
        $group = [];
        for ($i = $step - 1; $i < $cap - 1; $i += $step) {
            if (!isset($added[$i]))     { $group[] = $i;     $added[$i]     = true; }
            if (!isset($added[$i + 1])) { $group[] = $i + 1; $added[$i + 1] = true; }
        }
        shuffle($group);
        array_push($result, ...$group);
        $step >>= 1;
    }
    return $result;
}

function seeded_match_order(int $num_matches): array {
    if ($num_matches <= 1) return range(0, $num_matches - 1);
    if ($num_matches === 2) return [0, 1];
    $half      = (int)($num_matches / 2);
    $half_order = seeded_match_order($half);
    $result    = [];
    foreach ($half_order as $i => $pos) {
        $left_pos  = $pos;
        $right_pos = $num_matches - 1 - $pos;
        if ($i === 0) {
            $result[] = $left_pos;
            $result[] = $right_pos;
        } else {
            $pair = [$left_pos, $right_pos];
            shuffle($pair);
            foreach ($pair as $p) $result[] = $p;
        }
    }
    return $result;
}

function advance_ko_winner(array $match): void {
    if ($match['score1'] === $match['score2']) return;
    $is_doubles = !empty($match['double1_id']) || !empty($match['double2_id']);
    if ($is_doubles) {
        $winner_id = $match['score1'] > $match['score2'] ? $match['double1_id'] : $match['double2_id'];
        $p1col = 'double1_id'; $p2col = 'double2_id';
    } else {
        $winner_id = $match['score1'] > $match['score2'] ? $match['player1_id'] : $match['player2_id'];
        $p1col = 'player1_id'; $p2col = 'player2_id';
    }
    $next_round = (int)($match['ko_round'] / 2);
    $next_pos   = (int)($match['ko_position'] / 2);
    $slot       = $match['ko_position'] % 2 === 0 ? 1 : 2;
    $next_match = db_fetch(
        "SELECT * FROM `match` WHERE competition_id = ? AND ko_round = ? AND ko_position = ?",
        [$match['competition_id'], $next_round, $next_pos]
    );
    if ($next_match) {
        $col = $slot === 1 ? $p1col : $p2col;
        db_execute("UPDATE `match` SET `$col`=? WHERE id=?", [$winner_id, $next_match['id']]);
    }
}

function recompute_ko_from(int $cid, int $from_ko_round): void {
    db_execute(
        "UPDATE `match` SET player1_id=NULL, player2_id=NULL, double1_id=NULL, double2_id=NULL,
         score1=NULL, score2=NULL, played=0
         WHERE competition_id=? AND group_id IS NULL AND ko_round < ? AND ko_round != 3",
        [$cid, $from_ko_round]
    );
    if ($from_ko_round >= 4) {
        db_execute(
            "UPDATE `match` SET player1_id=NULL, player2_id=NULL, double1_id=NULL, double2_id=NULL,
             score1=NULL, score2=NULL, played=0
             WHERE competition_id=? AND ko_round=3",
            [$cid]
        );
    }
    $r = $from_ko_round;
    while ($r >= 2) {
        $matches = db_fetchall(
            "SELECT * FROM `match` WHERE competition_id=? AND ko_round=? AND played=1 AND group_id IS NULL",
            [$cid, $r]
        );
        foreach ($matches as $m) {
            advance_ko_winner($m);
            if ($r === 4) {
                $comp = db_fetch("SELECT third_place FROM competition WHERE id=?", [$cid]);
                if ($comp && $comp['third_place']) {
                    $is_doubles = !empty($m['double1_id']) || !empty($m['double2_id']);
                    if ($is_doubles) {
                        $loser_id = $m['score2'] > $m['score1'] ? $m['double1_id'] : $m['double2_id'];
                        $p1col = 'double1_id'; $p2col = 'double2_id';
                    } else {
                        $loser_id = $m['score2'] > $m['score1'] ? $m['player1_id'] : $m['player2_id'];
                        $p1col = 'player1_id'; $p2col = 'player2_id';
                    }
                    $third_m = db_fetch(
                        "SELECT * FROM `match` WHERE competition_id=? AND ko_round=3", [$cid]
                    );
                    if ($third_m) {
                        $col = $m['ko_position'] === 0 ? $p1col : $p2col;
                        db_execute("UPDATE `match` SET `$col`=? WHERE id=?", [$loser_id, $third_m['id']]);
                    }
                }
            }
        }
        $r = (int)($r / 2);
    }
}

function draw_ko_bracket(int $cid, array $seedings, bool $third_place): void {
    // Delete existing KO matches
    db_execute(
        "DELETE FROM `match` WHERE competition_id=? AND group_id IS NULL", [$cid]
    );
    if ($third_place) {
        db_execute(
            "UPDATE competition SET third_place=1 WHERE id=?", [$cid]
        );
    }

    $n      = count($seedings);
    $size   = next_power_of_2($n);
    $rounds = (int)log($size, 2);

    // Build first round slots
    $slots   = array_fill(0, $size, null);
    $order   = seeded_match_order($size / 2);
    $players = $seedings;
    foreach ($order as $slot_idx => $match_pos) {
        if (isset($players[$slot_idx])) {
            $slots[$match_pos * 2]     = $players[$slot_idx];
            $slots[$match_pos * 2 + 1] = $players[$slot_idx + count($players) - $size] ?? null;
        }
    }

    // Create matches for all rounds
    $ko_round = $size;
    while ($ko_round >= 2) {
        $n_matches = $ko_round / 2;
        for ($pos = 0; $pos < $n_matches; $pos++) {
            db_execute(
                "INSERT INTO `match` (competition_id, ko_round, ko_position) VALUES (?,?,?)",
                [$cid, $ko_round, $pos]
            );
        }
        $ko_round = (int)($ko_round / 2);
    }

    if ($third_place && $size >= 4) {
        db_execute(
            "INSERT INTO `match` (competition_id, ko_round, ko_position) VALUES (?,3,0)", [$cid]
        );
    }

    // Seed first round
    $first_matches = db_fetchall(
        "SELECT * FROM `match` WHERE competition_id=? AND ko_round=? ORDER BY ko_position",
        [$cid, $size]
    );
    foreach ($first_matches as $i => $m) {
        $p1 = $slots[$i * 2]     ?? null;
        $p2 = $slots[$i * 2 + 1] ?? null;
        if ($p1 !== null && $p2 === null) {
            // Bye: auto-advance
            db_execute(
                "UPDATE `match` SET player1_id=?, score1=1, score2=0, played=1 WHERE id=?",
                [$p1, $m['id']]
            );
            advance_ko_winner(array_merge($m, ['player1_id' => $p1, 'player2_id' => null, 'score1' => 1, 'score2' => 0]));
        } elseif ($p1 !== null && $p2 !== null) {
            db_execute(
                "UPDATE `match` SET player1_id=?, player2_id=? WHERE id=?",
                [$p1, $p2, $m['id']]
            );
        }
    }
    db_execute("UPDATE competition SET phase='ko' WHERE id=?", [$cid]);
}

function _maybe_set_done(int $cid): void {
    $c = db_fetch("SELECT phase, advance_count FROM competition WHERE id=?", [$cid]);
    if (!$c || !in_array($c['phase'], ['group', 'ko'], true)) return;
    if ($c['phase'] === 'ko') {
        $final = db_fetch(
            "SELECT id FROM `match` WHERE competition_id=? AND ko_round=2 AND played=1 AND bracket IS NULL",
            [$cid]
        );
        if ($final) db_execute("UPDATE competition SET phase='done' WHERE id=?", [$cid]);
    } elseif ($c['phase'] === 'group') {
        $unplayed = db_fetch(
            "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND played=0", [$cid]
        )['n'];
        if ((int)$unplayed === 0 && (int)$c['advance_count'] === 0) {
            db_execute("UPDATE competition SET phase='done' WHERE id=?", [$cid]);
        }
    }
}
