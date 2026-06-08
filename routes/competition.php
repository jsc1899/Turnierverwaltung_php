<?php
require_once __DIR__ . '/../lib/standings.php';
require_once __DIR__ . '/../lib/round_robin.php';
require_once __DIR__ . '/../lib/ko_bracket.php';

// _maybe_set_done() is defined in lib/ko_bracket.php (required above)

function _get_player_skill(int $pid, string $sport): float {
    return player_sport_skill($pid, $sport);
}

function new_competition(array $p): void {
    require_edit();
    csrf_verify();
    $name          = trim(post('name'));
    $group_size    = max(3, min(8, (int)post('group_size', 4)));
    $advance_count = max(0, min(2, (int)post('advance_count', 1)));
    $mode          = in_array(post('mode'), ['groups_ko', 'ko_only', 'double_ko']) ? post('mode') : 'groups_ko';
    $is_doubles    = post('is_doubles') ? 1 : 0;

    if (!$name) {
        flash('danger', 'Name erforderlich.');
        redirect('tournament/' . $p['tid']);
        return;
    }
    db_insert(
        "INSERT INTO competition (tournament_id, name, group_size, advance_count, mode, is_doubles) VALUES (?,?,?,?,?,?)",
        [$p['tid'], $name, $group_size, $advance_count, $mode, $is_doubles]
    );
    redirect('tournament/' . $p['tid']);
}

function show(array $p): void {
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT * FROM competition WHERE id = ?", [$cid]);
    if (!$c) { redirect(''); return; }
    $t = db_fetch("SELECT * FROM tournament WHERE id = ?", [$c['tournament_id']]);
    $is_doubles = !empty($c['is_doubles']);

    // Teilnehmer laden (Spieler oder Doppel je nach Bewerb-Typ)
    $assigned = $assigned_doubles = $unassigned = $unassigned_doubles = [];
    $unassigned_skills = [];

    if ($is_doubles) {
        $assigned_doubles = db_fetchall(
            "SELECT d.id, d.name, d.player1_id, d.player2_id, d.skill as double_skill,
             TRIM(CONCAT(COALESCE(p1.firstname,''), IF(COALESCE(p1.firstname,'')!='', ' ',''), p1.name)) as p1name,
             TRIM(CONCAT(COALESCE(p2.firstname,''), IF(COALESCE(p2.firstname,'')!='', ' ',''), p2.name)) as p2name,
             p1.club as p1club, p2.club as p2club,
             cd.created_at as reg_date, cd.skill
             FROM `double` d
             JOIN player p1 ON p1.id = d.player1_id
             JOIN player p2 ON p2.id = d.player2_id
             JOIN competition_double cd ON cd.double_id = d.id
             WHERE cd.competition_id = ? ORDER BY d.name",
            [$cid]
        );
        $assigned_dids = array_column($assigned_doubles, 'id');
        $unassigned_doubles = db_fetchall(
            "SELECT d.id, d.name, d.skill FROM `double` d
             WHERE d.id NOT IN
             (SELECT double_id FROM competition_double WHERE competition_id = ?)
             ORDER BY d.name",
            [$cid]
        );
        // Alle competition_player-Einträge ohne Doppelpartner (authoritative source, unabhängig von registration-Status)
        $confirmed_regs = ($c['phase'] === 'setup' && !empty($c['is_doubles'])) ? db_fetchall(
            "SELECT p.id as player_id, p.firstname, p.name as lastname, p.club, p.pass_nr,
                    COALESCE((
                      SELECT rc.partner_name
                      FROM registration_competition rc
                      JOIN registration r ON r.id = rc.registration_id
                      WHERE rc.competition_id = ?
                        AND ((r.pass_nr != '' AND r.pass_nr = p.pass_nr)
                             OR (r.lastname = p.name AND r.firstname = p.firstname))
                      ORDER BY r.created_at DESC LIMIT 1
                    ), (
                      SELECT rcc.partner_name
                      FROM registration_change_competition rcc
                      JOIN registration_change_request rcr ON rcr.id = rcc.change_request_id
                      JOIN registration r ON r.id = rcr.registration_id
                      WHERE rcc.competition_id = ? AND rcc.action = 'add' AND rcc.status = 'confirmed'
                        AND ((r.pass_nr != '' AND r.pass_nr = p.pass_nr)
                             OR (r.lastname = p.name AND r.firstname = p.firstname))
                      ORDER BY rcr.created_at DESC LIMIT 1
                    ), '') as partner_name
             FROM competition_player cp
             JOIN player p ON p.id = cp.player_id
             WHERE cp.competition_id = ?
               AND cp.player_id NOT IN (
                   SELECT d2.player1_id FROM `double` d2
                   JOIN competition_double cd2 ON cd2.double_id = d2.id
                   WHERE cd2.competition_id = ?
                   UNION
                   SELECT d2.player2_id FROM `double` d2
                   JOIN competition_double cd2 ON cd2.double_id = d2.id
                   WHERE cd2.competition_id = ?
               )
             ORDER BY p.name, p.firstname",
            [$cid, $cid, $cid, $cid, $cid]
        ) : [];
    } else {
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
        foreach ($unassigned as $pl) {
            $unassigned_skills[$pl['id']] = _get_player_skill($pl['id'], $sport);
        }
        $unassigned = array_values($unassigned);
    }

    $groups = [];
    if (in_array($c['phase'], ['group', 'ko', 'done'], true)) {
        $grps = db_fetchall("SELECT * FROM grp WHERE competition_id = ? ORDER BY name", [$cid]);
        foreach ($grps as $g) {
            if ($is_doubles) {
                require_once __DIR__ . '/../lib/standings.php';
                $standings = double_standings($g['id']);
                $matches   = db_fetchall(
                    "SELECT m.*,
                     COALESCE(CONCAT(dp1a.name,' / ',dp1b.name),'') as p1name,
                     COALESCE(CONCAT(dp2a.name,' / ',dp2b.name),'') as p2name
                     FROM `match` m
                     LEFT JOIN `double` d1 ON d1.id = m.double1_id
                     LEFT JOIN `double` d2 ON d2.id = m.double2_id
                     LEFT JOIN player dp1a ON dp1a.id = d1.player1_id
                     LEFT JOIN player dp1b ON dp1b.id = d1.player2_id
                     LEFT JOIN player dp2a ON dp2a.id = d2.player1_id
                     LEFT JOIN player dp2b ON dp2b.id = d2.player2_id
                     WHERE m.group_id = ? ORDER BY m.match_order, m.id",
                    [$g['id']]
                );
                // Normalisierung: double-IDs in player-Slots kopieren für Template-Kompatibilität
                $matches = array_map(function($m) {
                    $m['player1_id'] = $m['double1_id'];
                    $m['player2_id'] = $m['double2_id'];
                    return $m;
                }, $matches);
            } else {
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
            }
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
    $dko_wb            = [];
    $dko_lb            = [];
    $dko_gf            = null;

    if (in_array($c['phase'], ['ko', 'done'], true)) {
        if ($c['mode'] === 'double_ko') {
            require_once __DIR__ . '/../lib/double_ko_bracket.php';
            $cap = _dko_cap($cid);
            $k   = $cap > 0 ? (int)log($cap, 2) : 0;
            $lb_total = max(0, 2 * ($k - 1));
            $wb_names = [];
            for ($r = 1; $r <= $k; $r++) {
                $wb_names[$r] = match ($k - $r) {
                    0 => 'WB Finale', 1 => 'WB Halbfinale', 2 => 'WB Viertelfinale',
                    3 => 'WB Achtelfinale', default => 'WB Runde ' . $r,
                };
            }
            $lb_names = [];
            for ($r = 1; $r <= $lb_total; $r++) {
                $lb_names[$r] = $r === $lb_total ? 'LB Finale'
                    : ($r === $lb_total - 1 ? 'LB Halbfinale' : 'LB Runde ' . $r);
            }
            if ($is_doubles) {
                $sql = "SELECT m.*,
                        COALESCE(CONCAT(dp1a.name,' / ',dp1b.name),'') as p1name,
                        COALESCE(CONCAT(dp2a.name,' / ',dp2b.name),'') as p2name,
                        '' as p1club, '' as p2club
                        FROM `match` m
                        LEFT JOIN `double` d1 ON d1.id=m.double1_id
                        LEFT JOIN `double` d2 ON d2.id=m.double2_id
                        LEFT JOIN player dp1a ON dp1a.id=d1.player1_id
                        LEFT JOIN player dp1b ON dp1b.id=d1.player2_id
                        LEFT JOIN player dp2a ON dp2a.id=d2.player1_id
                        LEFT JOIN player dp2b ON dp2b.id=d2.player2_id
                        WHERE m.competition_id=? AND m.group_id IS NULL
                        ORDER BY bracket, ko_round, ko_position";
            } else {
                $sql = "SELECT m.*,
                        TRIM(CONCAT(p1.name,IF(COALESCE(p1.firstname,'')!='',CONCAT(' ',p1.firstname),''))) as p1name,
                        TRIM(CONCAT(p2.name,IF(COALESCE(p2.firstname,'')!='',CONCAT(' ',p2.firstname),''))) as p2name,
                        p1.club as p1club, p2.club as p2club
                        FROM `match` m
                        LEFT JOIN player p1 ON p1.id=m.player1_id
                        LEFT JOIN player p2 ON p2.id=m.player2_id
                        WHERE m.competition_id=? AND m.group_id IS NULL
                        ORDER BY bracket, ko_round, ko_position";
            }
            foreach (db_fetchall($sql, [$cid]) as $m) {
                if ($is_doubles) {
                    $m['player1_id'] = $m['double1_id'];
                    $m['player2_id'] = $m['double2_id'];
                }
                if ($m['bracket'] === 'W') {
                    $r = (int)$m['ko_round'];
                    $dko_wb[$r]['name']      = $wb_names[$r] ?? 'WB R' . $r;
                    $dko_wb[$r]['matches'][] = $m;
                } elseif ($m['bracket'] === 'L') {
                    $r = (int)$m['ko_round'];
                    $dko_lb[$r]['name']      = $lb_names[$r] ?? 'LB R' . $r;
                    $dko_lb[$r]['matches'][] = $m;
                } elseif ($m['bracket'] === 'GF') {
                    $dko_gf = $m;
                }
            }
            ksort($dko_wb);
            ksort($dko_lb);
            // Places
            if ($dko_gf && $dko_gf['played']) {
                if ($dko_gf['score1'] > $dko_gf['score2']) {
                    $places = [
                        ['rank'=>1,'name'=>$dko_gf['p1name'],'club'=>$dko_gf['p1club']],
                        ['rank'=>2,'name'=>$dko_gf['p2name'],'club'=>$dko_gf['p2club']],
                    ];
                } else {
                    $places = [
                        ['rank'=>1,'name'=>$dko_gf['p2name'],'club'=>$dko_gf['p2club']],
                        ['rank'=>2,'name'=>$dko_gf['p1name'],'club'=>$dko_gf['p1club']],
                    ];
                }
            }
        } else {
            if ($is_doubles) {
                $ko_matches = db_fetchall(
                    "SELECT m.*,
                     COALESCE(CONCAT(dp1a.name,' / ',dp1b.name),'') as p1name,
                     COALESCE(CONCAT(dp2a.name,' / ',dp2b.name),'') as p2name,
                     '' as p1club, '' as p2club
                     FROM `match` m
                     LEFT JOIN `double` d1 ON d1.id = m.double1_id
                     LEFT JOIN `double` d2 ON d2.id = m.double2_id
                     LEFT JOIN player dp1a ON dp1a.id = d1.player1_id
                     LEFT JOIN player dp1b ON dp1b.id = d1.player2_id
                     LEFT JOIN player dp2a ON dp2a.id = d2.player1_id
                     LEFT JOIN player dp2b ON dp2b.id = d2.player2_id
                     WHERE m.competition_id = ? AND m.group_id IS NULL
                     ORDER BY m.ko_round DESC, m.ko_position",
                    [$cid]
                );
                // Normalisierung für Template
                $ko_matches = array_map(function($m) {
                    $m['player1_id'] = $m['double1_id'];
                    $m['player2_id'] = $m['double2_id'];
                    return $m;
                }, $ko_matches);
            } else {
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
            }
            $rounds_dict = [];
            foreach ($ko_matches as $m) {
                $rounds_dict[$m['ko_round']][] = $m;
            }
            $round_names = [2=>'Finale', 4=>'Halbfinale', 8=>'Viertelfinale',
                            16=>'Achtelfinale', 32=>'Runde der 32', 64=>'Runde der 64'];
            krsort($rounds_dict);
            foreach ($rounds_dict as $r => $rmatches) {
                if ((int)$r === 3) {
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
                 WHERE m.competition_id=? AND m.ko_round=2 AND m.played=1 AND m.bracket IS NULL",
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
    }

    if ((int)$c['advance_count'] === 0 && (int)$unplayed_group === 0 && $groups) {
        $all_rows = [];
        foreach ($groups as $gi) {
            $all_rows = array_merge($all_rows, $gi['standings']);
        }
        usort($all_rows, fn($a,$b) => ($b['points']<=>$a['points']) ?: ($b['goal_diff']<=>$a['goal_diff']) ?: ($b['goals_for']<=>$a['goals_for']));
        foreach (array_slice($all_rows, 0, 4) as $i => $pl) {
            $places[] = ['rank' => $i+1, 'name' => $pl['name'], 'club' => $pl['club']];
        }
    }

    // Seedings (participant_id → label "1","2","3-4","5-8",...) for ko_only/double_ko
    $seed_order_sql = ($c['seeding_order'] ?? 'desc') === 'asc'
        ? "CASE WHEN COALESCE(skill,0)=0 THEN 1 ELSE 0 END, COALESCE(skill,0) ASC"
        : "COALESCE(skill,0) DESC";
    $ko_seedings = [];
    if (in_array($c['mode'], ['ko_only', 'double_ko']) && in_array($c['phase'], ['ko', 'done']) && !empty($c['show_seeding'])) {
        if ($is_doubles) {
            $seed_rows = db_fetchall(
                "SELECT double_id FROM competition_double WHERE competition_id=? ORDER BY $seed_order_sql, double_id",
                [$cid]
            );
            foreach ($seed_rows as $i => $sr) {
                $rank = $i + 1;
                $lbl  = $rank <= 2 ? (string)$rank : ((($hi = 1 << (int)ceil(log($rank, 2))) >> 1) + 1) . '-' . $hi;
                $ko_seedings[(int)$sr['double_id']] = $lbl;
            }
        } else {
            $seed_rows = db_fetchall(
                "SELECT player_id FROM competition_player WHERE competition_id=? ORDER BY $seed_order_sql, player_id",
                [$cid]
            );
            foreach ($seed_rows as $i => $sr) {
                $rank = $i + 1;
                $lbl  = $rank <= 2 ? (string)$rank : ((($hi = 1 << (int)ceil(log($rank, 2))) >> 1) + 1) . '-' . $hi;
                $ko_seedings[(int)$sr['player_id']] = $lbl;
            }
        }
    }

    $group_no_results = false;
    if ($c['phase'] === 'group') {
        $played_group = db_fetch(
            "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NOT NULL AND played=1",
            [$cid]
        )['n'];
        $group_no_results = ((int)$played_group === 0);
    }
    $ko_no_results = false;
    if ($c['phase'] === 'ko') {
        if ($is_doubles) {
            $played_ko = db_fetch(
                "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NULL AND ko_round != 3
                 AND double1_id IS NOT NULL AND double2_id IS NOT NULL AND played=1",
                [$cid]
            )['n'];
        } else {
            $played_ko = db_fetch(
                "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NULL AND ko_round != 3
                 AND player1_id IS NOT NULL AND player2_id IS NOT NULL AND played=1",
                [$cid]
            )['n'];
        }
        $ko_no_results = ((int)$played_ko === 0);
    }

    render('competition/show', [
        'page_title' => $c['name'],
        'c' => $c, 't' => $t,
        'is_doubles' => $is_doubles,
        'assigned' => $assigned, 'unassigned' => $unassigned,
        'unassigned_skills' => $unassigned_skills,
        'assigned_doubles' => $assigned_doubles,
        'unassigned_doubles' => $unassigned_doubles,
        'confirmed_regs' => $confirmed_regs ?? [],
        'groups' => $groups, 'ko_rounds' => $ko_rounds,
        'third_place_match' => $third_place_match,
        'unplayed_group' => $unplayed_group, 'places' => $places,
        'ko_no_results' => $ko_no_results, 'group_no_results' => $group_no_results,
        'dko_wb' => $dko_wb, 'dko_lb' => $dko_lb, 'dko_gf' => $dko_gf,
        'dko_cap' => $cap ?? 0, 'dko_lb_total' => $lb_total ?? 0,
        'ko_seedings' => $ko_seedings,
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


    $name              = trim(post('name', ''));
    $group_size        = max(3, min(8, (int)post('group_size', 4)));
    $advance_count     = max(0, min(2, (int)post('advance_count', 1)));
    $third_place       = post('third_place') ? 1 : 0;
    $registrations_open = post('registrations_open') ? 1 : 0;
    $max_players       = max(0, (int)post('max_players', 0));
    $show_seeding      = post('show_seeding') ? 1 : 0;
    $seeding_order     = post('seeding_order') === 'asc' ? 'asc' : 'desc';

    $c = db_fetch("SELECT phase, mode, is_doubles FROM competition WHERE id=?", [$cid]);
    if (!$c) { redirect('competition/' . $cid); return; }
    if ($name) db_execute("UPDATE competition SET name=? WHERE id=?", [$name, $cid]);

    // is_doubles nur ändern solange noch keine Teilnehmer eingetragen
    $has_participants = $c['is_doubles']
        ? (int)db_fetch("SELECT COUNT(*) as n FROM competition_double WHERE competition_id=?", [$cid])['n']
        : (int)db_fetch("SELECT COUNT(*) as n FROM competition_player WHERE competition_id=?", [$cid])['n'];
    if ($c['phase'] === 'setup' && $has_participants === 0) {
        $is_doubles = post('is_doubles') ? 1 : 0;
        db_execute("UPDATE competition SET is_doubles=? WHERE id=?", [$is_doubles, $cid]);
        $c['is_doubles'] = $is_doubles;
    }

    if (in_array($c['mode'], ['ko_only', 'double_ko'], true)) {
        db_execute("UPDATE competition SET third_place=?, registrations_open=?, max_players=?, show_seeding=?, seeding_order=? WHERE id=?",
            [$third_place, $registrations_open, $max_players, $show_seeding, $seeding_order, $cid]);
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
    $c    = db_fetch("SELECT tournament_id, max_players FROM competition WHERE id=?", [$cid]);
    $sport = '';
    $max   = (int)($c['max_players'] ?? 0);
    if ($c) {
        $t = db_fetch("SELECT sport FROM tournament WHERE id=?", [$c['tournament_id']]);
        $sport = $t ? ($t['sport'] ?? '') : '';
    }
    $skipped = 0;
    foreach ((array)$pids as $pid_str) {
        $pid = (int)$pid_str;
        if (!$pid) continue;
        if ($max > 0) {
            $count = (int)db_fetch("SELECT COUNT(*) as n FROM competition_player WHERE competition_id=?", [$cid])['n'];
            if ($count >= $max) { $skipped++; continue; }
        }
        $skill = _get_player_skill($pid, $sport);
        db_execute(
            "INSERT IGNORE INTO competition_player (competition_id, player_id, created_at, skill) VALUES (?,?,NOW(),?)",
            [$cid, $pid, $skill]
        );
    }
    if ($skipped > 0) {
        flash('warning', "Maximale Spieleranzahl ($max) erreicht — $skipped Spieler nicht hinzugefügt.");
    }
    redirect('competition/' . $cid . '#spieler');
}

function remove_player(array $p): void {
    require_edit();
    csrf_verify();
    db_execute("DELETE FROM competition_player WHERE competition_id=? AND player_id=?",
        [(int)$p['id'], (int)$p['pid']]);
    redirect('competition/' . $p['id'] . '#spieler');
}

function add_double(array $p): void {
    require_edit();
    csrf_verify();
    $cid  = (int)$p['id'];
    $dids = $_POST['double_ids'] ?? [];
    $c    = db_fetch("SELECT max_players, is_doubles, tournament_id FROM competition WHERE id=?", [$cid]);
    if (!$c || !$c['is_doubles']) { redirect('competition/' . $cid); return; }
    $max   = (int)($c['max_players'] ?? 0);
    $t     = db_fetch("SELECT sport FROM tournament WHERE id=?", [$c['tournament_id']]);
    $sport = $t['sport'] ?? '';
    $skipped = 0;
    foreach ((array)$dids as $did_str) {
        $did = (int)$did_str;
        if (!$did) continue;
        if ($max > 0) {
            $count = (int)db_fetch("SELECT COUNT(*) as n FROM competition_double WHERE competition_id=?", [$cid])['n'];
            if ($count >= $max) { $skipped++; continue; }
        }
        $d = db_fetch("SELECT player1_id, player2_id FROM `double` WHERE id=?", [$did]);
        if (!$d) continue;
        // Prüfen ob einer der Spieler bereits in einem anderen Doppel dieses Bewerbs ist
        $conflict = db_fetch(
            "SELECT d2.name FROM competition_double cd
             JOIN `double` d2 ON d2.id = cd.double_id
             WHERE cd.competition_id = ?
               AND cd.double_id != ?
               AND (d2.player1_id IN (?,?) OR d2.player2_id IN (?,?))",
            [$cid, $did, $d['player1_id'], $d['player2_id'], $d['player1_id'], $d['player2_id']]
        );
        if ($conflict) {
            flash('warning', 'Mindestens ein Spieler nimmt bereits im Doppel „' . $conflict['name'] . '" teil.');
            continue;
        }
        $skill = _get_player_skill((int)$d['player1_id'], $sport)
               + _get_player_skill((int)$d['player2_id'], $sport);
        db_execute(
            "INSERT IGNORE INTO competition_double (competition_id, double_id, created_at, skill) VALUES (?,?,NOW(),?)",
            [$cid, $did, $skill]
        );
    }
    if ($skipped > 0) {
        flash('warning', "Maximale Anzahl ($max) erreicht — $skipped Doppel nicht hinzugefügt.");
    }
    redirect('competition/' . $cid . '#spieler');
}

function pair_double_from_reg(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    $p1  = (int)post('player1_id');
    $p2  = (int)post('player2_id');
    if (!$p1 || !$p2 || $p1 === $p2) {
        flash('danger', 'Zwei verschiedene Spieler auswählen.');
        redirect('competition/' . $cid . '#spieler');
        return;
    }
    // Konfliktprüfung
    $conflict = db_fetch(
        "SELECT d2.name FROM competition_double cd
         JOIN `double` d2 ON d2.id = cd.double_id
         WHERE cd.competition_id = ?
           AND (d2.player1_id IN (?,?) OR d2.player2_id IN (?,?))",
        [$cid, $p1, $p2, $p1, $p2]
    );
    if ($conflict) {
        flash('warning', 'Mindestens ein Spieler nimmt bereits im Doppel „' . $conflict['name'] . '" teil.');
        redirect('competition/' . $cid . '#spieler');
        return;
    }
    // Bestehendes Doppel suchen oder neu anlegen
    $existing = db_fetch(
        "SELECT id FROM `double` WHERE (player1_id=? AND player2_id=?) OR (player1_id=? AND player2_id=?)",
        [$p1, $p2, $p2, $p1]
    );
    if ($existing) {
        $did = (int)$existing['id'];
    } else {
        $pl1 = db_fetch("SELECT name, firstname FROM player WHERE id=?", [$p1]);
        $pl2 = db_fetch("SELECT name, firstname FROM player WHERE id=?", [$p2]);
        if (!$pl1 || !$pl2) {
            flash('danger', 'Spieler nicht gefunden.');
            redirect('competition/' . $cid . '#spieler');
            return;
        }
        $name = $pl1['name'] . ' / ' . $pl2['name'];
        $did  = (int)db_insert(
            "INSERT INTO `double` (player1_id, player2_id, name, skill) VALUES (?,?,?,0)",
            [$p1, $p2, $name]
        );
    }
    $c     = db_fetch("SELECT tournament_id FROM competition WHERE id=?", [$cid]);
    $t     = db_fetch("SELECT sport FROM tournament WHERE id=?", [$c['tournament_id']]);
    $sport = $t['sport'] ?? '';
    $skill = player_sport_skill($p1, $sport) + player_sport_skill($p2, $sport);
    db_execute(
        "INSERT IGNORE INTO competition_double (competition_id, double_id, created_at, skill) VALUES (?,?,NOW(),?)",
        [$cid, $did, $skill]
    );
    flash('success', 'Doppel gebildet und dem Bewerb hinzugefügt.');
    redirect('competition/' . $cid . '#spieler');
}

function remove_double(array $p): void {
    require_edit();
    csrf_verify();
    db_execute("DELETE FROM competition_double WHERE competition_id=? AND double_id=?",
        [(int)$p['id'], (int)$p['did']]);
    redirect('competition/' . $p['id'] . '#spieler');
}

function draw_groups(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    $is_doubles = !empty($c['is_doubles']);

    if ($is_doubles) {
        $rows = db_fetchall(
            "SELECT cd.double_id as participant_id, COALESCE(cd.skill, 0) as skill
             FROM competition_double cd WHERE cd.competition_id=? ORDER BY cd.skill DESC",
            [$cid]
        );
    } else {
        $rows = db_fetchall(
            "SELECT cp.player_id as participant_id, COALESCE(cp.skill, 0) as skill
             FROM competition_player cp WHERE cp.competition_id=? ORDER BY cp.skill DESC",
            [$cid]
        );
    }
    $n = count($rows);
    if ($n < 3) { flash('danger', 'Mindestens 3 ' . ($is_doubles ? 'Doppel' : 'Spieler') . ' erforderlich.'); redirect('competition/'.$cid); return; }
    if ($n > 64) { flash('danger', 'Maximal 64 erlaubt.'); redirect('competition/'.$cid); return; }

    $group_size = (int)$c['group_size'];
    $num_groups = max(1, (int)round($n / $group_size));
    while ($num_groups > 1 && $n / $num_groups < 3) $num_groups--;
    while ($n / $num_groups > 8) $num_groups++;

    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        db_execute("DELETE FROM `match` WHERE competition_id=?", [$cid]);
        db_execute("DELETE FROM grp WHERE competition_id=?", [$cid]);

        $group_ids = [];
        for ($i = 0; $i < $num_groups; $i++) {
            $group_ids[] = db_insert(
                "INSERT INTO grp (competition_id, name) VALUES (?,?)",
                [$cid, 'Gruppe ' . chr(65 + $i)]
            );
        }

        $participant_ids = array_column($rows, 'participant_id');
        for ($start = 0; $start < $n; $start += $num_groups) {
            $bucket = array_slice($participant_ids, $start, $num_groups);
            shuffle($bucket);
            foreach ($bucket as $j => $pid) {
                if (isset($group_ids[$j])) {
                    if ($is_doubles) {
                        db_execute("INSERT INTO group_double (group_id, double_id) VALUES (?,?)",
                            [$group_ids[$j], $pid]);
                    } else {
                        db_execute("INSERT INTO group_player (group_id, player_id) VALUES (?,?)",
                            [$group_ids[$j], $pid]);
                    }
                }
            }
        }

        foreach ($group_ids as $gid) {
            if ($is_doubles) {
                $pids = array_column(
                    db_fetchall("SELECT double_id FROM group_double WHERE group_id=?", [$gid]),
                    'double_id'
                );
                $pairs = round_robin_schedule($pids);
                foreach ($pairs as $order => [$d1, $d2]) {
                    db_execute(
                        "INSERT INTO `match` (competition_id, group_id, double1_id, double2_id, match_order) VALUES (?,?,?,?,?)",
                        [$cid, $gid, $d1, $d2, $order + 1]
                    );
                }
            } else {
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
        }
        db_execute("UPDATE competition SET phase='group', registrations_open=0 WHERE id=?", [$cid]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        flash('danger', 'Fehler beim Auslosen der Gruppen.');
        redirect('competition/' . $cid); return;
    }
    flash('success', 'Gruppen wurden ausgelost!');
    redirect('competition/' . $cid);
}

function draw_ko(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    $is_doubles = !empty($c['is_doubles']);

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
        $st = $is_doubles ? double_standings($g['id']) : group_standings($g['id']);
        if ($st) $firsts[] = ['gid' => $g['id'], 'pid' => $st[0]['id']];
        if ($advance_count >= 2 && count($st) >= 2)
            $seconds[] = ['gid' => $g['id'], 'pid' => $st[1]['id']];
    }

    shuffle($firsts);
    if ($seconds) shuffle($seconds);

    $seedings = array_merge(array_column($firsts, 'pid'), array_column($seconds, 'pid'));
    $gids     = array_merge(array_column($firsts, 'gid'), array_column($seconds, 'gid'));
    $n        = count($seedings);

    if ($n < 2) {
        flash('danger', 'Nicht genug ' . ($is_doubles ? 'Doppel' : 'Spieler') . ' für KO-Phase.');
        redirect('competition/' . $cid);
        return;
    }

    $bracket_total = next_power_of_2($n);
    $num_byes      = $bracket_total - $n;
    $num_matches   = $bracket_total / 2;
    $match_order   = seeded_match_order($num_matches);
    $bracket       = array_fill(0, $bracket_total, null);

    for ($i = 0; $i < $num_byes; $i++) {
        $pos = $match_order[$i];
        $bracket[$pos * 2] = $seedings[$i];
    }

    $remaining      = array_slice($seedings, $num_byes);
    $remaining_gids = array_slice($gids, $num_byes);
    $real_positions = array_slice($match_order, $num_byes);
    $lo = 0; $hi = count($remaining) - 1;
    foreach ($real_positions as $pos) {
        if ($lo > $hi) break;
        $bracket[$pos * 2] = $remaining[$lo];
        if ($lo < $hi) {
            $best = $hi;
            for ($j = $hi; $j > $lo; $j--) {
                if ($remaining_gids[$j] !== $remaining_gids[$lo]) { $best = $j; break; }
            }
            if ($best !== $hi) {
                [$remaining[$best], $remaining[$hi]]         = [$remaining[$hi], $remaining[$best]];
                [$remaining_gids[$best], $remaining_gids[$hi]] = [$remaining_gids[$hi], $remaining_gids[$best]];
            }
            $bracket[$pos * 2 + 1] = $remaining[$hi];
        }
        $lo++; $hi--;
    }

    _build_ko_bracket($cid, $bracket, (bool)$c['third_place'], $is_doubles);
    flash('success', 'KO-Bracket wurde ausgelost!');
    redirect('competition/' . $cid);
}

function draw_ko_direct(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    $is_doubles = !empty($c['is_doubles']);

    if ($c['phase'] !== 'setup') {
        flash('warning', 'Auslosung nur in der Einrichtungsphase möglich.');
        redirect('competition/' . $cid);
        return;
    }
    $draw_order_asc = ($c['seeding_order'] ?? 'desc') === 'asc';
    if ($is_doubles) {
        $draw_order_sql = $draw_order_asc
            ? "CASE WHEN COALESCE(cd.skill,0)=0 THEN 1 ELSE 0 END, COALESCE(cd.skill,0) ASC, cd.double_id"
            : "COALESCE(cd.skill,0) DESC, cd.double_id";
        $rows = db_fetchall(
            "SELECT cd.double_id FROM competition_double cd WHERE cd.competition_id=? ORDER BY $draw_order_sql",
            [$cid]
        );
        $participants = array_column($rows, 'double_id');
    } else {
        $draw_order_sql = $draw_order_asc
            ? "CASE WHEN COALESCE(cp.skill,0)=0 THEN 1 ELSE 0 END, COALESCE(cp.skill,0) ASC, cp.player_id"
            : "COALESCE(cp.skill,0) DESC, cp.player_id";
        $rows = db_fetchall(
            "SELECT cp.player_id FROM competition_player cp WHERE cp.competition_id=? ORDER BY $draw_order_sql",
            [$cid]
        );
        $participants = array_column($rows, 'player_id');
    }
    $n = count($participants);
    if ($n < 2) { flash('danger', 'Mindestens 2 ' . ($is_doubles ? 'Doppel' : 'Spieler') . ' erforderlich.'); redirect('competition/'.$cid); return; }
    if ($n > 64) { flash('danger', 'Maximal 64 erlaubt.'); redirect('competition/'.$cid); return; }

    if ($c['mode'] === 'double_ko') {
        require_once __DIR__ . '/../lib/double_ko_bracket.php';
        draw_double_ko($cid, $participants, $is_doubles);
        flash('success', 'Doppel-KO-Bracket wurde ausgelost!');
        redirect('competition/' . $cid);
        return;
    }

    $bracket_total = next_power_of_2($n);
    $num_byes      = $bracket_total - $n;
    $seeded_pos    = array_slice(seeded_player_slots($bracket_total), 0, $bracket_total >> 1);
    $bracket       = array_fill(0, $bracket_total, null);

    $n_seeded = min($bracket_total >> 1, $n);
    for ($i = 0; $i < $n_seeded; $i++) {
        $bracket[$seeded_pos[$i]] = $participants[$i];
    }
    $opponents = array_slice($participants, $bracket_total >> 1);
    shuffle($opponents);
    foreach ($opponents as $j => $opp) {
        $bracket[$seeded_pos[$num_byes + $j] ^ 1] = $opp;
    }

    _build_ko_bracket($cid, $bracket, (bool)$c['third_place'], $is_doubles);
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
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT is_doubles FROM competition WHERE id=?", [$cid]);
    $is_doubles = $c && !empty($c['is_doubles']);

    $played = db_fetch(
        "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NOT NULL AND played=1",
        [$cid]
    )['n'];
    if ($played > 0) {
        flash('warning', 'Umstellen nicht möglich: bereits Ergebnisse eingetragen.');
        redirect('competition/' . $cid);
        return;
    }

    $valid_gids = array_column(db_fetchall("SELECT id FROM grp WHERE competition_id=?", [$cid]), 'id');
    if ($is_doubles) {
        $valid_ids = array_column(
            db_fetchall("SELECT double_id FROM competition_double WHERE competition_id=?", [$cid]),
            'double_id'
        );
    } else {
        $valid_ids = array_column(
            db_fetchall("SELECT player_id FROM competition_player WHERE competition_id=?", [$cid]),
            'player_id'
        );
    }

    $groups_post = $_POST['groups'] ?? [];
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        db_execute("DELETE FROM `match` WHERE competition_id=? AND group_id IS NOT NULL", [$cid]);
        if ($valid_gids) {
            $ph = implode(',', array_fill(0, count($valid_gids), '?'));
            if ($is_doubles) {
                db_execute("DELETE FROM group_double WHERE group_id IN ($ph)", $valid_gids);
            } else {
                db_execute("DELETE FROM group_player WHERE group_id IN ($ph)", $valid_gids);
            }
        }
        foreach ($groups_post as $gid_str => $pids) {
            $gid = (int)$gid_str;
            if (!in_array($gid, $valid_gids)) continue;
            foreach ((array)$pids as $pid) {
                $pid = (int)$pid;
                if (!in_array($pid, $valid_ids)) continue;
                if ($is_doubles) {
                    db_execute("INSERT IGNORE INTO group_double (group_id, double_id) VALUES (?,?)", [$gid, $pid]);
                } else {
                    db_execute("INSERT IGNORE INTO group_player (group_id, player_id) VALUES (?,?)", [$gid, $pid]);
                }
            }
            if ($is_doubles) {
                $ids_arr = array_column(
                    db_fetchall("SELECT double_id FROM group_double WHERE group_id=?", [$gid]),
                    'double_id'
                );
                $pairs = round_robin_schedule($ids_arr);
                foreach ($pairs as $order => [$d1, $d2]) {
                    db_execute(
                        "INSERT INTO `match` (competition_id, group_id, double1_id, double2_id, match_order) VALUES (?,?,?,?,?)",
                        [$cid, $gid, $d1, $d2, $order + 1]
                    );
                }
            } else {
                $ids_arr = array_column(
                    db_fetchall("SELECT player_id FROM group_player WHERE group_id=?", [$gid]),
                    'player_id'
                );
                $pairs = round_robin_schedule($ids_arr);
                foreach ($pairs as $order => [$p1, $p2]) {
                    db_execute(
                        "INSERT INTO `match` (competition_id, group_id, player1_id, player2_id, match_order) VALUES (?,?,?,?,?)",
                        [$cid, $gid, $p1, $p2, $order + 1]
                    );
                }
            }
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        flash('danger', 'Fehler beim Umstellen der Gruppen.');
        redirect('competition/' . $cid); return;
    }
    flash('success', 'Gruppen neu eingeteilt.');
    redirect('competition/' . $cid);
}

function ko_reorder(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT third_place, is_doubles FROM competition WHERE id=?", [$cid]);
    $is_doubles = $c && !empty($c['is_doubles']);

    $played = db_fetch(
        "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NULL AND ko_round != 3
         AND player1_id IS NOT NULL AND player2_id IS NOT NULL AND played=1",
        [$cid]
    )['n'];
    if ($played > 0) {
        flash('warning', 'Umstellen nicht möglich: bereits Ergebnisse eingetragen.');
        redirect('competition/' . $cid);
        return;
    }

    if ($is_doubles) {
        $valid_ids = array_column(
            db_fetchall("SELECT double_id FROM competition_double WHERE competition_id=?", [$cid]),
            'double_id'
        );
    } else {
        $valid_ids = array_column(
            db_fetchall("SELECT player_id FROM competition_player WHERE competition_id=?", [$cid]),
            'player_id'
        );
    }

    $raw     = array_map('intval', (array)($_POST['bracket'] ?? []));
    $bracket = array_map(fn($v) => ($v > 0 && in_array($v, $valid_ids)) ? $v : null, $raw);

    if (count(array_filter($bracket)) < 2) {
        flash('danger', 'Ungültige Bracket-Daten.');
        redirect('competition/' . $cid);
        return;
    }

    _build_ko_bracket($cid, $bracket, (bool)($c['third_place'] ?? false), $is_doubles);
    flash('success', 'KO-Bracket aktualisiert.');
    redirect('competition/' . $cid);
}

function seedings_save(array $p): void {
    require_edit();
    csrf_verify();
    $cid   = (int)$p['id'];
    $c     = db_fetch("SELECT third_place, is_doubles FROM competition WHERE id=?", [$cid]);
    $is_doubles = $c && !empty($c['is_doubles']);
    $order = post('player_order', '');
    $pids  = array_filter(array_map('intval', explode(',', $order)));

    if ($is_doubles) {
        $valid_ids = array_column(
            db_fetchall("SELECT double_id FROM competition_double WHERE competition_id=?", [$cid]),
            'double_id'
        );
    } else {
        $valid_ids = array_column(
            db_fetchall("SELECT player_id FROM competition_player WHERE competition_id=?", [$cid]),
            'player_id'
        );
    }
    $played = db_fetch(
        "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NULL AND ko_round != 3 AND played=1",
        [$cid]
    )['n'];
    if ($played > 0) {
        flash('warning', 'Auslosung kann nach Spielbeginn nicht mehr geändert werden.');
        redirect('competition/' . $cid);
        return;
    }
    $pids = array_values(array_filter($pids, fn($pid) => in_array($pid, $valid_ids)));

    $first_round = db_fetch(
        "SELECT MAX(ko_round) as r FROM `match` WHERE competition_id=? AND ko_round != 3 AND group_id IS NULL",
        [$cid]
    )['r'];
    if (!$first_round) { redirect('competition/' . $cid); return; }

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

    _build_ko_bracket($cid, $bracket, (bool)($c['third_place'] ?? false), $is_doubles);
    flash('success', 'Setzung gespeichert.');
    redirect('competition/' . $cid);
}

// ── Internal helpers ───────────────────────────────────────────────────────────

function _build_ko_bracket(int $cid, array $bracket, bool $third_place, bool $is_doubles = false): void {
    db_execute("DELETE FROM `match` WHERE competition_id=? AND group_id IS NULL", [$cid]);
    $bracket_total = count($bracket);
    if ($bracket_total < 2) return;

    $num_matches = $bracket_total / 2;
    $p1col = $is_doubles ? 'double1_id' : 'player1_id';
    $p2col = $is_doubles ? 'double2_id' : 'player2_id';

    for ($pos = 0; $pos < $num_matches; $pos++) {
        $p1 = $bracket[$pos * 2]     ?? null;
        $p2 = $bracket[$pos * 2 + 1] ?? null;
        db_execute(
            "INSERT INTO `match` (competition_id, ko_round, ko_position, `$p1col`, `$p2col`) VALUES (?,?,?,?,?)",
            [$cid, $bracket_total, $pos, $p1, $p2]
        );
    }

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

    for ($pos = 0; $pos < $num_matches; $pos++) {
        $m = db_fetch(
            "SELECT * FROM `match` WHERE competition_id=? AND ko_round=? AND ko_position=?",
            [$cid, $bracket_total, $pos]
        );
        if ($m) {
            $has_p1 = $is_doubles ? $m['double1_id'] : $m['player1_id'];
            $has_p2 = $is_doubles ? $m['double2_id'] : $m['player2_id'];
            if ($has_p1 && !$has_p2) {
                db_execute("UPDATE `match` SET played=1, score1=1, score2=0 WHERE id=?", [$m['id']]);
                advance_ko_winner(array_merge($m, ['score1'=>1,'score2'=>0]));
            } elseif ($has_p2 && !$has_p1) {
                db_execute("UPDATE `match` SET played=1, score1=0, score2=1 WHERE id=?", [$m['id']]);
                advance_ko_winner(array_merge($m, ['score1'=>0,'score2'=>1]));
            }
        }
    }
    db_execute("UPDATE competition SET phase='ko', registrations_open=0 WHERE id=?", [$cid]);
}
