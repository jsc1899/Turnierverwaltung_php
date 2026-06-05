<?php
require_once __DIR__ . '/../lib/standings.php';
require_once __DIR__ . '/../lib/round_robin.php';
require_once __DIR__ . '/../lib/ko_bracket.php';

function _get_player_skill(int $pid, string $sport): float {
    if ($sport) {
        $row = db_fetch(
            "SELECT skill FROM player_skill WHERE player_id=? AND sport=?", [$pid, $sport]
        );
        if ($row) return (float)$row['skill'];
    }
    $row = db_fetch("SELECT skill FROM player WHERE id=?", [$pid]);
    return $row ? (float)$row['skill'] : 0.0;
}

function _maybe_set_done(int $cid): void {
    $c = db_fetch("SELECT phase FROM competition WHERE id=?", [$cid]);
    if (!$c || !in_array($c['phase'], ['group', 'ko'], true)) return;

    if ($c['phase'] === 'ko') {
        $final = db_fetch(
            "SELECT * FROM `match` WHERE competition_id=? AND ko_round=2 AND played=1", [$cid]
        );
        if ($final) {
            db_execute("UPDATE competition SET phase='done' WHERE id=?", [$cid]);
        }
    } elseif ($c['phase'] === 'group') {
        $unplayed = db_fetch(
            "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND played=0", [$cid]
        )['n'];
        if ($unplayed == 0) {
            $adv = db_fetch("SELECT advance_count FROM competition WHERE id=?", [$cid]);
            if ($adv && (int)$adv['advance_count'] === 0) {
                db_execute("UPDATE competition SET phase='done' WHERE id=?", [$cid]);
            }
        }
    }
}

function new_competition(array $p): void {
    require_edit();
    csrf_verify();
    $name          = trim(post('name'));
    $group_size    = max(3, min(6, (int)post('group_size', 4)));
    $advance_count = max(0, min(2, (int)post('advance_count', 1)));
    $mode          = in_array(post('mode'), ['groups_ko', 'ko_only']) ? post('mode') : 'groups_ko';

    if (!$name) {
        flash('danger', 'Name erforderlich.');
        redirect('tournament/' . $p['tid']);
        return;
    }
    db_insert(
        "INSERT INTO competition (tournament_id, name, group_size, advance_count, mode) VALUES (?,?,?,?,?)",
        [$p['tid'], $name, $group_size, $advance_count, $mode]
    );
    redirect('tournament/' . $p['tid']);
}

function show(array $p): void {
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT * FROM competition WHERE id = ?", [$cid]);
    if (!$c) { redirect(''); return; }
    $t = db_fetch("SELECT * FROM tournament WHERE id = ?", [$c['tournament_id']]);

    $assigned = db_fetchall(
        "SELECT p.id, p.name, p.firstname, p.club, p.gender, p.pass_nr, p.email,
         cp.created_at as reg_date, cp.skill
         FROM player p JOIN competition_player cp ON cp.player_id = p.id
         WHERE cp.competition_id = ? ORDER BY p.name, p.firstname",
        [$cid]
    );
    $assigned_ids = array_column($assigned, 'id');

    $all_players  = db_fetchall("SELECT * FROM player ORDER BY name, firstname");
    $unassigned   = array_filter($all_players, fn($pl) => !in_array($pl['id'], $assigned_ids));
    $sport        = $t ? ($t['sport'] ?? '') : '';
    $unassigned_skills = [];
    foreach ($unassigned as $pl) {
        $unassigned_skills[$pl['id']] = _get_player_skill($pl['id'], $sport);
    }
    $unassigned = array_values($unassigned);

    $groups = [];
    if (in_array($c['phase'], ['group', 'ko', 'done'], true)) {
        $grps = db_fetchall("SELECT * FROM grp WHERE competition_id = ? ORDER BY name", [$cid]);
        foreach ($grps as $g) {
            $standings = group_standings($g['id']);
            $matches   = db_fetchall(
                "SELECT m.*,
                 TRIM(CONCAT(p1.name, IF(COALESCE(p1.firstname,'') != '', CONCAT(' ', p1.firstname), ''))) as p1name,
                 TRIM(CONCAT(p2.name, IF(COALESCE(p2.firstname,'') != '', CONCAT(' ', p2.firstname), ''))) as p2name
                 FROM `match` m
                 LEFT JOIN player p1 ON p1.id = m.player1_id
                 LEFT JOIN player p2 ON p2.id = m.player2_id
                 WHERE m.group_id = ? ORDER BY m.match_order, m.id",
                [$g['id']]
            );
            $groups[] = ['group' => $g, 'standings' => $standings, 'matches' => $matches];
        }
    }

    $unplayed_group = db_fetch(
        "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NOT NULL AND played=0",
        [$cid]
    )['n'];

    $ko_rounds         = [];
    $third_place_match = null;
    $places            = [];

    if (in_array($c['phase'], ['ko', 'done'], true)) {
        $ko_matches = db_fetchall(
            "SELECT m.*,
             TRIM(CONCAT(p1.name, IF(COALESCE(p1.firstname,'') != '', CONCAT(' ', p1.firstname), ''))) as p1name,
             TRIM(CONCAT(p2.name, IF(COALESCE(p2.firstname,'') != '', CONCAT(' ', p2.firstname), ''))) as p2name,
             p1.club as p1club, p2.club as p2club
             FROM `match` m
             LEFT JOIN player p1 ON p1.id = m.player1_id
             LEFT JOIN player p2 ON p2.id = m.player2_id
             WHERE m.competition_id = ? AND m.group_id IS NULL
             ORDER BY m.ko_round DESC, m.ko_position",
            [$cid]
        );
        $rounds_dict = [];
        foreach ($ko_matches as $m) {
            $rounds_dict[$m['ko_round']][] = $m;
        }
        $round_names = [2=>'Finale', 4=>'Halbfinale', 8=>'Viertelfinale',
                        16=>'Achtelfinale', 32=>'Runde der 32', 64=>'Runde der 64'];
        krsort($rounds_dict);
        foreach ($rounds_dict as $r => $rmatches) {
            if ($r === 3 || $r === '3') {
                $third_place_match = ['round' => 3, 'name' => 'Spiel um Platz 3', 'matches' => $rmatches];
            } else {
                $ko_rounds[] = ['round' => (int)$r, 'name' => $round_names[(int)$r] ?? "Runde $r", 'matches' => $rmatches];
            }
        }

        $final = db_fetch(
            "SELECT m.*,
             TRIM(CONCAT(p1.name, IF(COALESCE(p1.firstname,'')!='',CONCAT(' ',p1.firstname),''))) as p1name, p1.club as p1club,
             TRIM(CONCAT(p2.name, IF(COALESCE(p2.firstname,'')!='',CONCAT(' ',p2.firstname),''))) as p2name, p2.club as p2club
             FROM `match` m
             LEFT JOIN player p1 ON p1.id=m.player1_id
             LEFT JOIN player p2 ON p2.id=m.player2_id
             WHERE m.competition_id=? AND m.ko_round=2 AND m.played=1",
            [$cid]
        );
        if ($final) {
            if ($final['score1'] > $final['score2']) {
                $places = [
                    ['rank'=>1,'name'=>$final['p1name'],'club'=>$final['p1club']],
                    ['rank'=>2,'name'=>$final['p2name'],'club'=>$final['p2club']],
                ];
            } else {
                $places = [
                    ['rank'=>1,'name'=>$final['p2name'],'club'=>$final['p2club']],
                    ['rank'=>2,'name'=>$final['p1name'],'club'=>$final['p1club']],
                ];
            }
            $third = db_fetch(
                "SELECT m.*,
                 TRIM(CONCAT(p1.name,IF(COALESCE(p1.firstname,'')!='',CONCAT(' ',p1.firstname),''))) as p1name, p1.club as p1club,
                 TRIM(CONCAT(p2.name,IF(COALESCE(p2.firstname,'')!='',CONCAT(' ',p2.firstname),''))) as p2name, p2.club as p2club
                 FROM `match` m
                 LEFT JOIN player p1 ON p1.id=m.player1_id
                 LEFT JOIN player p2 ON p2.id=m.player2_id
                 WHERE m.competition_id=? AND m.ko_round=3 AND m.played=1",
                [$cid]
            );
            if ($third) {
                if ($third['score1'] > $third['score2']) {
                    $places[] = ['rank'=>3,'name'=>$third['p1name'],'club'=>$third['p1club']];
                    $places[] = ['rank'=>4,'name'=>$third['p2name'],'club'=>$third['p2club']];
                } else {
                    $places[] = ['rank'=>3,'name'=>$third['p2name'],'club'=>$third['p2club']];
                    $places[] = ['rank'=>4,'name'=>$third['p1name'],'club'=>$third['p1club']];
                }
            }
        }
    }

    if ((int)$c['advance_count'] === 0 && $unplayed_group == 0 && $groups) {
        $all_rows = [];
        foreach ($groups as $gi) {
            $all_rows = array_merge($all_rows, $gi['standings']);
        }
        usort($all_rows, fn($a,$b) => ($b['points']<=>$a['points']) ?: ($b['goal_diff']<=>$a['goal_diff']) ?: ($b['goals_for']<=>$a['goals_for']));
        foreach (array_slice($all_rows, 0, 4) as $i => $pl) {
            $places[] = ['rank' => $i+1, 'name' => $pl['name'], 'club' => $pl['club']];
        }
    }

    $ko_no_results = false;
    if ($c['phase'] === 'ko') {
        $played_ko = db_fetch(
            "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NULL AND ko_round != 3 AND played=1",
            [$cid]
        )['n'];
        $ko_no_results = ($played_ko == 0);
    }

    render('competition/show', [
        'page_title' => $c['name'],
        'c' => $c, 't' => $t,
        'assigned' => $assigned, 'unassigned' => $unassigned,
        'unassigned_skills' => $unassigned_skills,
        'groups' => $groups, 'ko_rounds' => $ko_rounds,
        'third_place_match' => $third_place_match,
        'unplayed_group' => $unplayed_group, 'places' => $places,
        'ko_no_results' => $ko_no_results,
    ]);
}

function settings(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];

    // Sub-actions via hidden fields
    if (post('mark_done')) {
        $c = db_fetch("SELECT phase FROM competition WHERE id=?", [$cid]);
        if ($c && in_array($c['phase'], ['group','ko'], true)) {
            db_execute("UPDATE competition SET phase='done' WHERE id=?", [$cid]);
            flash('success', 'Bewerb als beendet markiert.');
        }
        redirect('competition/' . $cid);
        return;
    }
    if (post('reopen')) {
        $ko_count    = db_fetch("SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NULL", [$cid])['n'];
        $group_count = db_fetch("SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NOT NULL", [$cid])['n'];
        $phase = $ko_count ? 'ko' : ($group_count ? 'group' : 'setup');
        db_execute("UPDATE competition SET phase=? WHERE id=?", [$phase, $cid]);
        flash('info', 'Bewerb wieder geöffnet.');
        redirect('competition/' . $cid);
        return;
    }


    $group_size        = max(3, min(6, (int)post('group_size', 4)));
    $advance_count     = max(0, min(2, (int)post('advance_count', 1)));
    $third_place       = post('third_place') ? 1 : 0;
    $registrations_open = post('registrations_open') ? 1 : 0;
    $max_players       = max(0, (int)post('max_players', 0));

    $c = db_fetch("SELECT phase, mode FROM competition WHERE id=?", [$cid]);
    if ($c['mode'] === 'ko_only') {
        db_execute("UPDATE competition SET third_place=?, registrations_open=?, max_players=? WHERE id=?",
            [$third_place, $registrations_open, $max_players, $cid]);
    } elseif ($c && $c['phase'] === 'setup') {
        db_execute("UPDATE competition SET group_size=?, advance_count=?, third_place=?, registrations_open=?, max_players=? WHERE id=?",
            [$group_size, $advance_count, $third_place, $registrations_open, $max_players, $cid]);
    } else {
        db_execute("UPDATE competition SET advance_count=?, third_place=?, registrations_open=?, max_players=? WHERE id=?",
            [$advance_count, $third_place, $registrations_open, $max_players, $cid]);
    }
    flash('success', 'Einstellungen gespeichert.');
    redirect('competition/' . $cid);
}

function delete(array $p): void {
    require_edit();
    csrf_verify();
    $c = db_fetch("SELECT tournament_id FROM competition WHERE id=?", [(int)$p['id']]);
    $tid = $c ? $c['tournament_id'] : null;
    db_execute("DELETE FROM competition WHERE id=?", [(int)$p['id']]);
    redirect($tid ? 'tournament/' . $tid : '');
}

function add_player(array $p): void {
    require_edit();
    csrf_verify();
    $cid  = (int)$p['id'];
    $pids = $_POST['player_ids'] ?? [];
    $c    = db_fetch("SELECT tournament_id FROM competition WHERE id=?", [$cid]);
    $sport = '';
    if ($c) {
        $t = db_fetch("SELECT sport FROM tournament WHERE id=?", [$c['tournament_id']]);
        $sport = $t ? ($t['sport'] ?? '') : '';
    }
    foreach ((array)$pids as $pid_str) {
        $pid = (int)$pid_str;
        if (!$pid) continue;
        $skill = _get_player_skill($pid, $sport);
        db_execute(
            "INSERT IGNORE INTO competition_player (competition_id, player_id, created_at, skill) VALUES (?,?,NOW(),?)",
            [$cid, $pid, $skill]
        );
    }
    redirect('competition/' . $cid);
}

function remove_player(array $p): void {
    require_edit();
    csrf_verify();
    db_execute("DELETE FROM competition_player WHERE competition_id=? AND player_id=?",
        [(int)$p['id'], (int)$p['pid']]);
    redirect('competition/' . $p['id']);
}

function draw_groups(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);

    $rows = db_fetchall(
        "SELECT cp.player_id, COALESCE(cp.skill, 0) as skill
         FROM competition_player cp WHERE cp.competition_id=? ORDER BY cp.skill DESC",
        [$cid]
    );
    $n = count($rows);
    if ($n < 3) { flash('danger', 'Mindestens 3 Spieler erforderlich.'); redirect('competition/'.$cid); return; }
    if ($n > 64) { flash('danger', 'Maximal 64 Spieler erlaubt.'); redirect('competition/'.$cid); return; }

    db_execute("DELETE FROM `match` WHERE competition_id=?", [$cid]);
    db_execute("DELETE FROM grp WHERE competition_id=?", [$cid]);

    $group_size = (int)$c['group_size'];
    $num_groups = max(1, (int)round($n / $group_size));
    while ($num_groups > 1 && $n / $num_groups < 3) $num_groups--;
    while ($n / $num_groups > 6) $num_groups++;

    $group_ids = [];
    for ($i = 0; $i < $num_groups; $i++) {
        $group_ids[] = db_insert(
            "INSERT INTO grp (competition_id, name) VALUES (?,?)",
            [$cid, 'Gruppe ' . chr(65 + $i)]
        );
    }

    // Seeded draw: distribute players in buckets of num_groups (randomized within bucket)
    $player_ids = array_column($rows, 'player_id');
    for ($start = 0; $start < $n; $start += $num_groups) {
        $bucket = array_slice($player_ids, $start, $num_groups);
        shuffle($bucket);
        foreach ($bucket as $j => $pid) {
            if (isset($group_ids[$j])) {
                db_execute("INSERT INTO group_player (group_id, player_id) VALUES (?,?)",
                    [$group_ids[$j], $pid]);
            }
        }
    }

    foreach ($group_ids as $gid) {
        $pids = array_column(
            db_fetchall("SELECT player_id FROM group_player WHERE group_id=?", [$gid]),
            'player_id'
        );
        $pairs = round_robin_schedule($pids);
        foreach ($pairs as $order => [$p1, $p2]) {
            db_execute(
                "INSERT INTO `match` (competition_id, group_id, player1_id, player2_id, match_order) VALUES (?,?,?,?,?)",
                [$cid, $gid, $p1, $p2, $order + 1]
            );
        }
    }
    db_execute("UPDATE competition SET phase='group', registrations_open=0 WHERE id=?", [$cid]);
    flash('success', 'Gruppen wurden ausgelost!');
    redirect('competition/' . $cid);
}

function draw_ko(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);

    $unplayed = db_fetch(
        "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NOT NULL AND played=0",
        [$cid]
    )['n'];
    if ($unplayed > 0) {
        flash('warning', "Noch $unplayed Gruppenspiele offen!");
        redirect('competition/' . $cid);
        return;
    }

    $grps         = db_fetchall("SELECT * FROM grp WHERE competition_id=? ORDER BY name", [$cid]);
    $advance_count = (int)$c['advance_count'];

    $firsts = []; $seconds = [];
    foreach ($grps as $g) {
        $st = group_standings($g['id']);
        if ($st) $firsts[] = ['gid' => $g['id'], 'pid' => $st[0]['id']];
        if ($advance_count >= 2 && count($st) >= 2)
            $seconds[] = ['gid' => $g['id'], 'pid' => $st[1]['id']];
    }

    if ($advance_count >= 2 && count($firsts) === count($seconds)) {
        shuffle($firsts); shuffle($seconds);
        $avail = $seconds; $pairs = [];
        foreach ($firsts as $f) {
            $placed = false;
            foreach ($avail as $i => $s) {
                if ($s['gid'] !== $f['gid']) {
                    $pairs[] = [$f['pid'], $s['pid']];
                    array_splice($avail, $i, 1);
                    $placed = true; break;
                }
            }
            if (!$placed && $avail) { $pairs[] = [$f['pid'], array_shift($avail)['pid']]; }
        }
        $bracket = [];
        foreach ($pairs as [$p1, $p2]) { $bracket[] = $p1; $bracket[] = $p2; }
    } else {
        $advancing = array_merge(array_column($firsts, 'pid'), array_column($seconds, 'pid'));
        shuffle($advancing);
        $bracket = $advancing;
    }

    $real = array_filter($bracket);
    if (count($real) < 2) {
        flash('danger', 'Nicht genug Spieler für KO-Phase.');
        redirect('competition/' . $cid);
        return;
    }

    _build_ko_bracket($cid, $bracket, (bool)$c['third_place']);
    flash('success', 'KO-Bracket wurde ausgelost!');
    redirect('competition/' . $cid);
}

function draw_ko_direct(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);

    if ($c['phase'] !== 'setup') {
        flash('warning', 'Auslosung nur in der Einrichtungsphase möglich.');
        redirect('competition/' . $cid);
        return;
    }
    $rows = db_fetchall(
        "SELECT cp.player_id FROM competition_player cp WHERE cp.competition_id=? ORDER BY cp.skill DESC",
        [$cid]
    );
    $n = count($rows);
    if ($n < 2) { flash('danger', 'Mindestens 2 Spieler erforderlich.'); redirect('competition/'.$cid); return; }
    if ($n > 64) { flash('danger', 'Maximal 64 Spieler erlaubt.'); redirect('competition/'.$cid); return; }

    $players       = array_column($rows, 'player_id');
    $bracket_total = next_power_of_2($n);
    $num_byes      = $bracket_total - $n;
    $num_matches   = $bracket_total / 2;
    $match_order   = seeded_match_order($num_matches);
    $bracket       = array_fill(0, $bracket_total, null);

    for ($i = 0; $i < $num_byes; $i++) {
        $pos = $match_order[$i];
        $bracket[$pos * 2] = $players[$i];
    }
    $remaining      = array_slice($players, $num_byes);
    $real_positions = array_slice($match_order, $num_byes);
    $lo = 0; $hi = count($remaining) - 1;
    foreach ($real_positions as $pos) {
        if ($lo >= $hi) break;
        $bracket[$pos * 2]     = $remaining[$lo++];
        $bracket[$pos * 2 + 1] = $remaining[$hi--];
    }

    _build_ko_bracket($cid, $bracket, (bool)$c['third_place']);
    flash('success', 'KO-Bracket wurde ausgelost!');
    redirect('competition/' . $cid);
}

function reset_groups(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    db_execute("DELETE FROM `match` WHERE competition_id=?", [$cid]);
    db_execute("DELETE FROM grp WHERE competition_id=?", [$cid]);
    db_execute("UPDATE competition SET phase='setup', registrations_open=1 WHERE id=?", [$cid]);
    flash('info', 'Gruppenphase zurückgesetzt.');
    redirect('competition/' . $cid);
}

function reset_ko(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    db_execute("DELETE FROM `match` WHERE competition_id=? AND group_id IS NULL", [$cid]);
    $has_groups = db_fetch("SELECT COUNT(*) as n FROM grp WHERE competition_id=?", [$cid])['n'];
    db_execute("UPDATE competition SET phase=? WHERE id=?",
        [$has_groups ? 'group' : 'setup', $cid]);
    flash('info', 'KO-Phase zurückgesetzt.');
    redirect('competition/' . $cid);
}

function groups_reorder(array $p): void {
    require_edit();
    csrf_verify();
    $cid    = (int)$p['id'];
    $groups = $_POST['groups'] ?? [];
    // groups = array of {group_id: [player_id, ...]}
    // Delete existing group assignments and matches, then rebuild
    db_execute("DELETE FROM `match` WHERE competition_id=? AND group_id IS NOT NULL", [$cid]);
    db_execute("DELETE FROM group_player gp WHERE gp.group_id IN (SELECT id FROM grp WHERE competition_id=?)", [$cid]);

    foreach ($groups as $gid_str => $pids) {
        $gid = (int)$gid_str;
        foreach ((array)$pids as $pid) {
            db_execute("INSERT IGNORE INTO group_player (group_id, player_id) VALUES (?,?)", [$gid, (int)$pid]);
        }
        $pids_arr = array_column(
            db_fetchall("SELECT player_id FROM group_player WHERE group_id=?", [$gid]),
            'player_id'
        );
        $pairs = round_robin_schedule($pids_arr);
        foreach ($pairs as $order => [$p1, $p2]) {
            db_execute(
                "INSERT INTO `match` (competition_id, group_id, player1_id, player2_id, match_order) VALUES (?,?,?,?,?)",
                [$cid, $gid, $p1, $p2, $order + 1]
            );
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

function seedings_save(array $p): void {
    require_edit();
    csrf_verify();
    $cid   = (int)$p['id'];
    $order = post('player_order', '');
    $pids  = array_filter(array_map('intval', explode(',', $order)));

    $valid_pids = array_column(
        db_fetchall("SELECT player_id FROM competition_player WHERE competition_id=?", [$cid]),
        'player_id'
    );
    $played = db_fetch(
        "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NULL AND ko_round != 3 AND played=1",
        [$cid]
    )['n'];
    if ($played > 0) {
        flash('warning', 'Auslosung kann nach Spielbeginn nicht mehr geändert werden.');
        redirect('competition/' . $cid);
        return;
    }
    // Filter to valid players only
    $pids = array_values(array_filter($pids, fn($pid) => in_array($pid, $valid_pids)));

    $first_round = db_fetch(
        "SELECT MAX(ko_round) as r FROM `match` WHERE competition_id=? AND ko_round != 3 AND group_id IS NULL",
        [$cid]
    )['r'];
    if (!$first_round) { redirect('competition/' . $cid); return; }

    // Rebuild KO bracket with new seedings
    $c = db_fetch("SELECT third_place FROM competition WHERE id=?", [$cid]);
    db_execute("DELETE FROM `match` WHERE competition_id=? AND group_id IS NULL", [$cid]);

    $bracket_total = next_power_of_2(count($pids));
    $num_byes      = $bracket_total - count($pids);
    $num_matches   = $bracket_total / 2;
    $match_order   = seeded_match_order($num_matches);
    $bracket       = array_fill(0, $bracket_total, null);

    for ($i = 0; $i < $num_byes; $i++) {
        $pos = $match_order[$i];
        $bracket[$pos * 2] = $pids[$i] ?? null;
    }
    $remaining      = array_slice($pids, $num_byes);
    $real_positions = array_slice($match_order, $num_byes);
    $lo = 0; $hi = count($remaining) - 1;
    foreach ($real_positions as $pos) {
        if ($lo >= $hi) break;
        $bracket[$pos * 2]     = $remaining[$lo++] ?? null;
        $bracket[$pos * 2 + 1] = $remaining[$hi--] ?? null;
    }

    _build_ko_bracket($cid, $bracket, (bool)($c['third_place'] ?? false));
    flash('success', 'Setzung gespeichert.');
    redirect('competition/' . $cid);
}

// ── Internal helpers ───────────────────────────────────────────────────────────

function _build_ko_bracket(int $cid, array $bracket, bool $third_place): void {
    db_execute("DELETE FROM `match` WHERE competition_id=? AND group_id IS NULL", [$cid]);
    $bracket_total = count($bracket);
    if ($bracket_total < 2) return;

    $num_matches = $bracket_total / 2;

    // Create first round
    for ($pos = 0; $pos < $num_matches; $pos++) {
        $p1 = $bracket[$pos * 2]     ?? null;
        $p2 = $bracket[$pos * 2 + 1] ?? null;
        db_execute(
            "INSERT INTO `match` (competition_id, ko_round, ko_position, player1_id, player2_id) VALUES (?,?,?,?,?)",
            [$cid, $bracket_total, $pos, $p1, $p2]
        );
    }

    // Create subsequent rounds
    $r = $bracket_total / 2;
    while ($r >= 2) {
        for ($pos = 0; $pos < $r / 2; $pos++) {
            db_execute("INSERT INTO `match` (competition_id, ko_round, ko_position) VALUES (?,?,?)",
                [$cid, $r, $pos]);
        }
        $r = (int)($r / 2);
    }

    if ($third_place && $bracket_total >= 4) {
        db_execute("INSERT INTO `match` (competition_id, ko_round, ko_position) VALUES (?,3,0)", [$cid]);
    }

    // Auto-advance byes
    for ($pos = 0; $pos < $num_matches; $pos++) {
        $m = db_fetch(
            "SELECT * FROM `match` WHERE competition_id=? AND ko_round=? AND ko_position=?",
            [$cid, $bracket_total, $pos]
        );
        if ($m) {
            if ($m['player1_id'] && !$m['player2_id']) {
                db_execute("UPDATE `match` SET played=1, score1=1, score2=0 WHERE id=?", [$m['id']]);
                advance_ko_winner(array_merge($m, ['score1'=>1,'score2'=>0]));
            } elseif ($m['player2_id'] && !$m['player1_id']) {
                db_execute("UPDATE `match` SET played=1, score1=0, score2=1 WHERE id=?", [$m['id']]);
                advance_ko_winner(array_merge($m, ['score1'=>0,'score2'=>1]));
            }
        }
    }
    db_execute("UPDATE competition SET phase='ko', registrations_open=0 WHERE id=?", [$cid]);
}
