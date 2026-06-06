<?php
// Double-Elimination (Doppel-KO) bracket logic.
//
// Convention for bracket column values:
//   'W'  = Winners Bracket  — ko_round 1..k (ascending, R1 = first round)
//   'L'  = Losers Bracket   — ko_round 1..2*(k-1) (ascending)
//   'GF' = Grand Final      — ko_round 1, position 0
//
// Single-KO matches retain bracket=NULL (unchanged legacy convention).

require_once __DIR__ . '/ko_bracket.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function dko_lb_round_count(int $cap, int $lb_round): int {
    // Rounds pair up: (R1,R2) → cap/4 matches, (R3,R4) → cap/8, etc.
    return $cap >> ((int)ceil($lb_round / 2) + 1);
}

function dko_set_player(int $cid, string $bracket, int $round, int $pos, int $slot, int $pid): void {
    $col = $slot === 1 ? 'player1_id' : 'player2_id';
    db_execute(
        "UPDATE `match` SET `$col`=? WHERE competition_id=? AND bracket=? AND ko_round=? AND ko_position=?",
        [$pid, $cid, $bracket, $round, $pos]
    );
}

// Advance winner (and optionally drop loser to LB) after a match result.
// $is_bye = true when player2 was null (bye match in WB R1 — no LB drop).
function dko_advance(int $cid, int $cap, string $bracket, int $round, int $pos,
                     int $winner_id, ?int $loser_id, bool $is_bye): void
{
    $k        = (int)log($cap, 2);
    $lb_total = 2 * ($k - 1);

    if ($bracket === 'W') {
        // Advance winner in WB
        if ($round < $k) {
            dko_set_player($cid, 'W', $round + 1, $pos >> 1, ($pos % 2 === 0) ? 1 : 2, $winner_id);
        } else {
            dko_set_player($cid, 'GF', 1, 0, 1, $winner_id);
        }
        // Drop loser to LB
        if (!$is_bye && $loser_id !== null) {
            $n_wb1 = $cap >> 1;
            if ($round === 1) {
                // Fold: top-half losers → player1, bottom-half → player2 (mirrored pos)
                if ($pos < $n_wb1 / 2) {
                    dko_set_player($cid, 'L', 1, $pos, 1, $loser_id);
                } else {
                    dko_set_player($cid, 'L', 1, $n_wb1 - 1 - $pos, 2, $loser_id);
                }
            } else {
                // WB Rr (r≥2) → LB R(2r-2), reversed position, player2
                $lb_round   = 2 * ($round - 1);
                $wb_r_count = $cap >> $round;
                dko_set_player($cid, 'L', $lb_round, $wb_r_count - 1 - $pos, 2, $loser_id);
            }
        }
    } elseif ($bracket === 'L') {
        if ($round < $lb_total) {
            $next_r = $round + 1;
            if ($round % 2 === 1) {
                // Minor → Major: same position, player1
                dko_set_player($cid, 'L', $next_r, $pos, 1, $winner_id);
            } else {
                // Major → Minor: halve position, player slot by parity
                dko_set_player($cid, 'L', $next_r, $pos >> 1, ($pos % 2 === 0) ? 1 : 2, $winner_id);
            }
        } else {
            // LB Final winner → GF player2
            dko_set_player($cid, 'GF', 1, 0, 2, $winner_id);
        }
    }
    // GF: no further advancement
}

// ── Draw ──────────────────────────────────────────────────────────────────────

function draw_double_ko(int $cid, array $seedings): void {
    $n   = count($seedings);
    $cap = next_power_of_2($n);
    $k   = (int)log($cap, 2);
    $lb_total = 2 * ($k - 1);

    db_execute("DELETE FROM `match` WHERE competition_id=? AND group_id IS NULL", [$cid]);

    // Pre-create WB slots (round 1..k, ascending)
    for ($r = 1; $r <= $k; $r++) {
        $count = $cap >> $r;
        for ($pos = 0; $pos < $count; $pos++) {
            db_execute(
                "INSERT INTO `match` (competition_id, ko_round, ko_position, bracket) VALUES (?,?,?,'W')",
                [$cid, $r, $pos]
            );
        }
    }

    // Pre-create LB slots (round 1..lb_total)
    for ($r = 1; $r <= $lb_total; $r++) {
        $cnt = dko_lb_round_count($cap, $r);
        for ($pos = 0; $pos < $cnt; $pos++) {
            db_execute(
                "INSERT INTO `match` (competition_id, ko_round, ko_position, bracket) VALUES (?,?,?,'L')",
                [$cid, $r, $pos]
            );
        }
    }

    // Grand Final
    db_execute(
        "INSERT INTO `match` (competition_id, ko_round, ko_position, bracket) VALUES (?,1,0,'GF')",
        [$cid]
    );

    // Seed WB R1 (same lo/hi approach as draw_ko_direct)
    $num_byes     = $cap - $n;
    $num_matches  = $cap >> 1;
    $match_order  = seeded_match_order($num_matches);
    $bracket_arr  = array_fill(0, $cap, null);

    for ($i = 0; $i < $num_byes; $i++) {
        $pos = $match_order[$i];
        $bracket_arr[$pos * 2] = $seedings[$i];
    }
    $remaining      = array_slice($seedings, $num_byes);
    $real_positions = array_slice($match_order, $num_byes);
    $lo = 0; $hi = count($remaining) - 1;
    foreach ($real_positions as $pos) {
        if ($lo >= $hi) break;
        $bracket_arr[$pos * 2]     = $remaining[$lo++];
        $bracket_arr[$pos * 2 + 1] = $remaining[$hi--];
    }

    $wb1 = db_fetchall(
        "SELECT * FROM `match` WHERE competition_id=? AND bracket='W' AND ko_round=1 ORDER BY ko_position",
        [$cid]
    );
    foreach ($wb1 as $i => $m) {
        $p1 = $bracket_arr[$i * 2]     ?? null;
        $p2 = $bracket_arr[$i * 2 + 1] ?? null;
        db_execute("UPDATE `match` SET player1_id=?, player2_id=? WHERE id=?", [$p1, $p2, $m['id']]);
        if ($p1 !== null && $p2 === null) {
            db_execute("UPDATE `match` SET played=1, score1=1, score2=0 WHERE id=?", [$m['id']]);
            dko_advance($cid, $cap, 'W', 1, $i, (int)$p1, null, true);
        }
    }

    db_execute("UPDATE competition SET phase='ko', registrations_open=0 WHERE id=?", [$cid]);
}

// ── Recompute ─────────────────────────────────────────────────────────────────

function _dko_cap(int $cid): int {
    $n = (int)(db_fetch(
        "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND bracket='W' AND ko_round=1",
        [$cid]
    )['n'] ?? 0);
    return $n > 0 ? $n * 2 : 0;
}

// Rebuild all derived player slots from scratch (called after any DKO result change).
function recompute_double_ko(int $cid): void {
    $cap = _dko_cap($cid);
    if (!$cap) return;
    $k        = (int)log($cap, 2);
    $lb_total = 2 * ($k - 1);

    // Clear all non-WB-R1 player slots
    db_execute("UPDATE `match` SET player1_id=NULL, player2_id=NULL
                WHERE competition_id=? AND bracket='W' AND ko_round > 1", [$cid]);
    db_execute("UPDATE `match` SET player1_id=NULL, player2_id=NULL
                WHERE competition_id=? AND bracket='L'", [$cid]);
    db_execute("UPDATE `match` SET player1_id=NULL, player2_id=NULL
                WHERE competition_id=? AND bracket='GF'", [$cid]);

    // Re-propagate WB rounds 1..k
    for ($r = 1; $r <= $k; $r++) {
        $matches = db_fetchall(
            "SELECT * FROM `match` WHERE competition_id=? AND bracket='W' AND ko_round=? AND played=1 ORDER BY ko_position",
            [$cid, $r]
        );
        foreach ($matches as $m) {
            $is_bye = ($m['player2_id'] === null);
            if ($r === 1 && $is_bye) {
                if ($m['player1_id']) {
                    dko_advance($cid, $cap, 'W', 1, (int)$m['ko_position'], (int)$m['player1_id'], null, true);
                }
                continue;
            }
            if (!$m['player1_id'] || !$m['player2_id']) continue;
            $winner = (int)($m['score1'] > $m['score2'] ? $m['player1_id'] : $m['player2_id']);
            $loser  = (int)($m['score1'] > $m['score2'] ? $m['player2_id'] : $m['player1_id']);
            dko_advance($cid, $cap, 'W', $r, (int)$m['ko_position'], $winner, $loser, false);
        }
    }

    // Re-propagate LB rounds 1..lb_total
    for ($r = 1; $r <= $lb_total; $r++) {
        $matches = db_fetchall(
            "SELECT * FROM `match` WHERE competition_id=? AND bracket='L' AND ko_round=? AND played=1 ORDER BY ko_position",
            [$cid, $r]
        );
        foreach ($matches as $m) {
            if (!$m['player1_id'] || !$m['player2_id']) continue;
            $winner = (int)($m['score1'] > $m['score2'] ? $m['player1_id'] : $m['player2_id']);
            dko_advance($cid, $cap, 'L', $r, (int)$m['ko_position'], $winner, null, false);
        }
    }
}

function _maybe_set_done_dko(int $cid): void {
    $gf = db_fetch(
        "SELECT id FROM `match` WHERE competition_id=? AND bracket='GF' AND played=1", [$cid]
    );
    if ($gf) {
        db_execute("UPDATE competition SET phase='done' WHERE id=?", [$cid]);
    }
}
