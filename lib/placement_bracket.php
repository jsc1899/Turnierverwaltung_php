<?php
// Platzierungs-Brackets für den Modus "Gruppen + Kreuzspiele" (groups_cross).
//
// Jeder Platzblock spielt JEDEN Platz aus (vollständige Platzierungsrunde): in jeder Runde
// spielen alle noch aktiven Teilnehmer, Sieger steigen in den oberen Sub-Pool, Verlierer in den
// unteren. Nach log2(S) Runden hat jeder Teilnehmer eine eindeutige Platzierung.
//
// Speicherung in `match`:
//   bracket     = Block-Tag ('C0','C1',…)  (unterscheidet von Einzel-KO=NULL und Doppel-KO=W/L/GF)
//   ko_round    = Rundenindex 1..k (1 = erste Runde)
//   ko_position = globaler Index 0..S/2-1 innerhalb der Runde
//   place_lo    = unterster Platz des Sub-Pools dieses Spiels
// Teilnehmer in player/double/team-Spalten (wie KO).

require_once __DIR__ . '/ko_bracket.php';

function _pl_cols(bool $is_team, bool $is_doubles): array {
    return $is_team ? ['team1_id', 'team2_id']
        : ($is_doubles ? ['double1_id', 'double2_id'] : ['player1_id', 'player2_id']);
}

// Pool-Größe in Runde r eines Blocks der Größe S.
function _pl_pool_size(int $S, int $r): int {
    return intdiv($S, 1 << ($r - 1));
}

// Sieger/Verlierer eines Spiels bestimmen. Rückgabe [winner_id|null, loser_id|null].
// Ein Bye (ein Slot real, einer NULL) liefert sofort den realen Spieler als Sieger.
function _pl_resolve(array $m, string $c1, string $c2): array {
    $p1 = !empty($m[$c1]) ? (int)$m[$c1] : null;
    $p2 = !empty($m[$c2]) ? (int)$m[$c2] : null;
    if ($p1 !== null && $p2 === null) return [$p1, null];
    if ($p2 !== null && $p1 === null) return [$p2, null];
    if ($p1 !== null && $p2 !== null) {
        if (empty($m['played'])) return [null, null];
        $tb = (int)($m['tiebreak_winner'] ?? 0);
        if ((int)$m['score1'] === (int)$m['score2'] && !$tb) return [null, null];
        $w = $tb ? ($tb === 1 ? $p1 : $p2) : ((int)$m['score1'] > (int)$m['score2'] ? $p1 : $p2);
        return [$w, $w === $p1 ? $p2 : $p1];
    }
    return [null, null];
}

function _pl_set(int $cid, string $tag, int $r, int $gp, int $slot, ?int $pid, string $c1, string $c2): void {
    $col = $slot === 1 ? $c1 : $c2;
    db_execute(
        "UPDATE `match` SET `$col`=? WHERE competition_id=? AND bracket=? AND ko_round=? AND ko_position=?",
        [$pid, $cid, $tag, $r, $gp]
    );
}

// ── Seeding-Helfer ────────────────────────────────────────────────────────────

// Byes-sicheres Seeding (Logik aus draw_ko): Byes an die Top-Seed-Spiele (Gegner NULL),
// restliche Paarungen lo×hi unter Vermeidung gleicher Gruppe. seedings/gids parallel.
function _pl_seed_slots(array $seedings, array $gids): array {
    $n = count($seedings);
    $S = $n < 2 ? 2 : next_power_of_2($n);
    $num_byes    = $S - $n;
    $num_matches = intdiv($S, 2);
    $match_order = seeded_match_order($num_matches);
    $slots = array_fill(0, $S, null);

    for ($i = 0; $i < $num_byes; $i++) $slots[$match_order[$i] * 2] = $seedings[$i];

    $remaining = array_slice($seedings, $num_byes);
    $rgids     = array_slice($gids, $num_byes);
    $positions = array_slice($match_order, $num_byes);
    $lo = 0; $hi = count($remaining) - 1;
    foreach ($positions as $pos) {
        if ($lo > $hi) break;
        $slots[$pos * 2] = $remaining[$lo];
        if ($lo < $hi) {
            $best = $hi;
            for ($j = $hi; $j > $lo; $j--) { if ($rgids[$j] !== $rgids[$lo]) { $best = $j; break; } }
            if ($best !== $hi) {
                [$remaining[$best], $remaining[$hi]] = [$remaining[$hi], $remaining[$best]];
                [$rgids[$best], $rgids[$hi]]         = [$rgids[$hi], $rgids[$best]];
            }
            $slots[$pos * 2 + 1] = $remaining[$hi];
        }
        $lo++; $hi--;
    }
    return $slots;
}

// Block aus einer einzigen Rangstufe (getrennt): Teilnehmer gemischt, alle aus versch. Gruppen.
function _pl_straight_slots(array $parts): array {
    $parts = array_values($parts);
    $idx = range(0, count($parts) - 1); shuffle($idx);
    $p = array_map(fn($i) => $parts[$i], $idx);
    return _pl_seed_slots($p, range(1, max(1, count($p))));
}

// Kreuz-Block: Erste (Ra) als Top-Seeds (bekommen Byes), Zweite (Rb) über Kreuz als Gegner.
function _pl_cross_slots(array $Ra, array $RaG, array $Rb, array $RbG): array {
    $ai = range(0, count($Ra) - 1); shuffle($ai);
    $Ra = array_map(fn($i) => $Ra[$i], $ai); $RaG = array_map(fn($i) => $RaG[$i], $ai);
    $bi = range(0, count($Rb) - 1); shuffle($bi);
    $Rb = array_map(fn($i) => $Rb[$i], $bi); $RbG = array_map(fn($i) => $RbG[$i], $bi);
    return _pl_seed_slots(array_merge($Ra, $Rb), array_merge($RaG, $RbG));
}

// ── Blöcke aus Gruppenplatzierungen bilden ─────────────────────────────────────

// cross_flags: pro Tier 'x' (Kreuz) oder 's' (getrennt). Liefert Liste von Blöcken:
//   ['tag','place_lo','count','slots','label'].
function build_placement_blocks(int $cid, bool $is_team, bool $is_doubles, string $seeding_order, array $cross_flags): array {
    $grps = db_fetchall("SELECT id FROM grp WHERE competition_id=? ORDER BY name", [$cid]);
    $standings = []; $maxRank = 0;
    foreach ($grps as $g) {
        $st = $is_team ? team_standings($g['id'], $seeding_order)
            : ($is_doubles ? double_standings($g['id'], $seeding_order) : group_standings($g['id'], $seeding_order));
        $standings[$g['id']] = array_column($st, 'id');
        $maxRank = max($maxRank, count($st));
    }
    $tiers = (int)ceil($maxRank / 2);

    $blocks = []; $cur = 1; $bi = 0;
    for ($t = 1; $t <= $tiers; $t++) {
        $a = 2 * $t - 2; $b = 2 * $t - 1; // 0-basierte Rangindizes für Ränge (2t-1) und (2t)
        $Ra = []; $RaG = []; $Rb = []; $RbG = [];
        foreach ($grps as $g) {
            $ids = $standings[$g['id']];
            if (isset($ids[$a])) { $Ra[] = (int)$ids[$a]; $RaG[] = (int)$g['id']; }
            if (isset($ids[$b])) { $Rb[] = (int)$ids[$b]; $RbG[] = (int)$g['id']; }
        }
        $cross = (($cross_flags[$t - 1] ?? 'x') !== 's');
        if ($cross) {
            $M = count($Ra) + count($Rb);
            if ($M < 1) continue;
            $slots = _pl_cross_slots($Ra, $RaG, $Rb, $RbG);
            $blocks[] = _pl_make_block($bi++, $cur, $M, $slots);
            $cur += $M;
        } else {
            if ($Ra) { $blocks[] = _pl_make_block($bi++, $cur, count($Ra), _pl_straight_slots($Ra)); $cur += count($Ra); }
            if ($Rb) { $blocks[] = _pl_make_block($bi++, $cur, count($Rb), _pl_straight_slots($Rb)); $cur += count($Rb); }
        }
    }
    return $blocks;
}

function _pl_make_block(int $index, int $place_lo, int $count, array $slots): array {
    $hi    = $place_lo + $count - 1;
    $label = $count > 1 ? "Plätze {$place_lo}–{$hi}" : "Platz {$place_lo}";
    return ['tag' => 'C' . $index, 'place_lo' => $place_lo, 'count' => $count, 'slots' => $slots, 'label' => $label];
}

// ── Aufbau & Propagation ───────────────────────────────────────────────────────

function draw_placement(int $cid, array $blocks, bool $is_doubles = false, bool $is_team = false): void {
    db_execute("DELETE FROM `match` WHERE competition_id=? AND group_id IS NULL", [$cid]);
    [$c1, $c2] = _pl_cols($is_team, $is_doubles);

    foreach ($blocks as $b) {
        $tag = $b['tag'];
        $S   = count($b['slots']);
        if ($S < 2) continue;
        $k   = (int)round(log($S, 2));
        $nmatch = intdiv($S, 2);
        for ($r = 1; $r <= $k; $r++) {
            $ps  = _pl_pool_size($S, $r);
            $mpp = intdiv($ps, 2);
            for ($gp = 0; $gp < $nmatch; $gp++) {
                $place_lo = $b['place_lo'] + intdiv($gp, $mpp) * $ps;
                db_execute(
                    "INSERT INTO `match` (competition_id, ko_round, ko_position, bracket, place_lo) VALUES (?,?,?,?,?)",
                    [$cid, $r, $gp, $tag, $place_lo]
                );
            }
        }
        for ($gp = 0; $gp < $nmatch; $gp++) {
            $p1 = $b['slots'][2 * $gp]     ?? null;
            $p2 = $b['slots'][2 * $gp + 1] ?? null;
            db_execute(
                "UPDATE `match` SET `$c1`=?, `$c2`=? WHERE competition_id=? AND bracket=? AND ko_round=1 AND ko_position=?",
                [$p1, $p2, $cid, $tag, $gp]
            );
        }
    }
    db_execute("UPDATE competition SET phase='ko', registrations_open=0 WHERE id=?", [$cid]);
    recompute_placement($cid);
}

function _pl_block_size(int $cid, string $tag): int {
    $n = (int)(db_fetch(
        "SELECT COUNT(*) n FROM `match` WHERE competition_id=? AND bracket=? AND ko_round=1", [$cid, $tag]
    )['n'] ?? 0);
    return $n > 0 ? $n * 2 : 0;
}

function _pl_tags(int $cid): array {
    return array_column(db_fetchall(
        "SELECT DISTINCT bracket FROM `match` WHERE competition_id=? AND group_id IS NULL AND bracket LIKE 'C%' ORDER BY bracket",
        [$cid]
    ), 'bracket');
}

// Leitet alle nachgelagerten Slots aus den gespielten Ergebnissen neu ab.
function recompute_placement(int $cid): void {
    $c = db_fetch("SELECT is_doubles, is_team FROM competition WHERE id=?", [$cid]);
    $is_team    = $c && !empty($c['is_team']);
    $is_doubles = $c && !$is_team && !empty($c['is_doubles']);
    [$c1, $c2] = _pl_cols($is_team, $is_doubles);

    foreach (_pl_tags($cid) as $tag) {
        $S = _pl_block_size($cid, $tag);
        if ($S < 2) continue;
        $k = (int)round(log($S, 2));

        db_execute("UPDATE `match` SET player1_id=NULL, player2_id=NULL, double1_id=NULL, double2_id=NULL,
                    team1_id=NULL, team2_id=NULL
                    WHERE competition_id=? AND bracket=? AND ko_round>1", [$cid, $tag]);

        for ($r = 1; $r < $k; $r++) {
            $ps        = _pl_pool_size($S, $r);
            $mpp       = intdiv($ps, 2);
            $mpp_child = intdiv($ps, 4);
            $ms = db_fetchall(
                "SELECT * FROM `match` WHERE competition_id=? AND bracket=? AND ko_round=? ORDER BY ko_position",
                [$cid, $tag, $r]
            );
            foreach ($ms as $m) {
                $gp = (int)$m['ko_position'];
                $p  = intdiv($gp, $mpp);
                $j  = $gp % $mpp;
                [$w, $l] = _pl_resolve($m, $c1, $c2);
                if ($w === null) continue;
                $clocal = intdiv($j, 2);
                $slot   = ($j % 2 === 0) ? 1 : 2;
                _pl_set($cid, $tag, $r + 1, (2 * $p) * $mpp_child + $clocal, $slot, $w, $c1, $c2);
                if ($l !== null) {
                    _pl_set($cid, $tag, $r + 1, (2 * $p + 1) * $mpp_child + $clocal, $slot, $l, $c1, $c2);
                }
            }
        }
    }
}

// Endplatzierung [platz => teilnehmer_id] aus den letzten Runden (ps=2) je Block.
function placement_final_places(int $cid): array {
    $c = db_fetch("SELECT is_doubles, is_team FROM competition WHERE id=?", [$cid]);
    $is_team    = $c && !empty($c['is_team']);
    $is_doubles = $c && !$is_team && !empty($c['is_doubles']);
    [$c1, $c2] = _pl_cols($is_team, $is_doubles);

    $res = [];
    foreach (_pl_tags($cid) as $tag) {
        $S = _pl_block_size($cid, $tag);
        if ($S < 2) continue;
        $k = (int)round(log($S, 2));
        $ms = db_fetchall(
            "SELECT * FROM `match` WHERE competition_id=? AND bracket=? AND ko_round=?",
            [$cid, $tag, $k]
        );
        foreach ($ms as $m) {
            $pl = (int)$m['place_lo'];
            [$w, $l] = _pl_resolve($m, $c1, $c2);
            if ($w !== null) $res[$pl] = $w;
            if ($l !== null) $res[$pl + 1] = $l;
        }
    }
    ksort($res);
    return $res;
}

function _maybe_set_done_placement(int $cid): void {
    $c = db_fetch("SELECT is_doubles, is_team FROM competition WHERE id=?", [$cid]);
    $is_team    = $c && !empty($c['is_team']);
    $is_doubles = $c && !$is_team && !empty($c['is_doubles']);
    [$c1, $c2] = _pl_cols($is_team, $is_doubles);
    $open = (int)db_fetch(
        "SELECT COUNT(*) n FROM `match`
         WHERE competition_id=? AND group_id IS NULL AND bracket LIKE 'C%'
           AND played=0 AND `$c1` IS NOT NULL AND `$c2` IS NOT NULL", [$cid]
    )['n'];
    if ($open === 0) db_execute("UPDATE competition SET phase='done' WHERE id=?", [$cid]);
}
