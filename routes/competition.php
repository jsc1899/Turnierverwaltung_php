<?php
require_once __DIR__ . '/../lib/standings.php';
require_once __DIR__ . '/../lib/round_robin.php';
require_once __DIR__ . '/../lib/ko_bracket.php';
require_once __DIR__ . '/../lib/courts.php';
require_once __DIR__ . '/../lib/kickoff.php';

// _maybe_set_done() is defined in lib/ko_bracket.php (required above)

// Liest die Kreuz/Getrennt-Konfiguration (Modus groups_cross) aus dem Formular:
// pro Tier (= ceil(group_size/2)) 'x' (Kreuz, Default) oder 's' (getrennt), als CSV.
function _cross_config_from_post(int $group_size): string {
    $tiers = max(1, (int)ceil($group_size / 2));
    $in    = (array)($_POST['cross_config'] ?? []);
    $in    = array_values($in);
    $parts = [];
    for ($i = 0; $i < $tiers; $i++) $parts[] = (($in[$i] ?? 'x') === 's') ? 's' : 'x';
    return implode(',', $parts);
}

// Leitet aus dem Formular (mode + finalrunde + advance_count) den gespeicherten Modus und die
// Aufsteigerzahl ab. UI-Mode 'groups_ko' + finalrunde 'cross' → Modus 'groups_cross'.
// Rückgabe: [string $mode, int $advance_count].
function _resolve_finalrunde(): array {
    $ui = post('mode', 'groups_ko');
    if (in_array($ui, ['ko_only', 'double_ko'], true)) return [$ui, 0];
    // Gruppenphase
    $fr = post('finalrunde', 'none');
    if ($fr === 'cross' || $ui === 'groups_cross') return ['groups_cross', 0];
    if ($fr === 'ko') return ['groups_ko', max(1, min(2, (int)post('advance_count', 1)))];
    return ['groups_ko', 0];
}

function _get_player_skill(int $pid, string $sport): float {
    return player_sport_skill($pid, $sport);
}

function new_competition(array $p): void {
    require_edit();
    csrf_verify();
    require_tournament_open((int)$p['tid']);
    $name          = trim(post('name'));
    $group_size    = max(3, min(20, (int)post('group_size', 4)));
    [$mode, $advance_count] = _resolve_finalrunde();
    $comp_type          = post('comp_type', 'single');
    $is_team            = $comp_type === 'team'    ? 1 : 0;
    $is_doubles         = $comp_type === 'doubles' ? 1 : 0;
    $third_place        = post('third_place')        ? 1 : 0;
    $show_seeding       = post('show_seeding')       ? 1 : 0;
    $show_skill         = post('show_skill')         ? 1 : 0;
    $registrations_open = post('registrations_open') ? 1 : 0;
    $max_players        = max(0, (int)post('max_players', 0));
    $seeding_order      = in_array(post('seeding_order'), ['asc', 'random'], true) ? post('seeding_order') : 'desc';
    $team_size          = $is_team ? max(0, min(20, (int)post('team_size', 0))) : 0;
    $score_mode         = in_array(post('score_mode'), ['sets', 'sets_grp']) ? post('score_mode') : 'match';
    $show_byes          = post('show_byes') ? 1 : 0;
    $force_byes         = post('force_byes') ? 1 : 0;
    $num_courts         = max(0, min(20, (int)post('num_courts', 0)));
    $team_result_mode   = in_array(post('team_result_mode'), ['sum', 'total'], true) ? post('team_result_mode') : 'wins';
    $standings_order    = post('standings_order') === 'diff' ? 'diff' : 'h2h';
    $cross_config       = _cross_config_from_post($group_size);

    if (!$name) {
        flash('danger', 'Name erforderlich.');
        redirect('tournament/' . $p['tid']);
        return;
    }
    db_insert(
        "INSERT INTO competition
         (tournament_id, name, group_size, advance_count, mode, is_doubles, is_team,
          third_place, show_seeding, show_skill, registrations_open, max_players, seeding_order, team_size, score_mode, show_byes, force_byes, num_courts, team_result_mode, standings_order, cross_config)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$p['tid'], $name, $group_size, $advance_count, $mode, $is_doubles, $is_team,
         $third_place, $show_seeding, $show_skill, $registrations_open, $max_players, $seeding_order, $team_size, $score_mode, $show_byes, $force_byes, $num_courts, $team_result_mode, $standings_order, $cross_config]
    );
    redirect('tournament/' . $p['tid']);
}

function show(array $p): void {
    $cid = (int)$p['id'];
    $c   = db_fetch("SELECT * FROM competition WHERE id = ?", [$cid]);
    if (!$c) { redirect(''); return; }
    $t = db_fetch("SELECT * FROM tournament WHERE id = ?", [$c['tournament_id']]);
    $is_doubles = !empty($c['is_doubles']);
    $is_team    = !empty($c['is_team']);

    // Teilnehmer laden (Spieler oder Doppel oder Teams je nach Bewerb-Typ)
    $assigned = $assigned_doubles = $unassigned = $unassigned_doubles = [];
    $assigned_teams = $unassigned_teams = [];
    $unassigned_skills = [];

    if ($is_team) {
        $assigned_teams = db_fetchall(
            "SELECT t.id, t.name, ct.skill, ct.created_at as reg_date
             FROM `team` t JOIN competition_team ct ON ct.team_id = t.id
             WHERE ct.competition_id = ? ORDER BY t.name",
            [$cid]
        );
        foreach ($assigned_teams as &$team) {
            $team['members'] = db_fetchall(
                "SELECT TRIM(CONCAT(COALESCE(p.firstname,''), IF(COALESCE(p.firstname,'')!='', ' ',''), p.name)) as fullname, p.club
                 FROM player p JOIN `team_player` tp ON tp.player_id = p.id WHERE tp.team_id = ? ORDER BY p.name",
                [$team['id']]
            );
        }
        unset($team);
        $unassigned_teams = db_fetchall(
            "SELECT t.id, t.name, t.skill FROM `team` t
             WHERE t.is_active=1 AND t.id NOT IN (SELECT team_id FROM competition_team WHERE competition_id=?)
             ORDER BY t.name",
            [$cid]
        );
        // Team-Mitglieder und bestehende Duelle für Duel-Modals (nur wenn team_size > 0)
        $team_members   = [];
        $existing_duels = [];
        if ((int)($c['team_size'] ?? 0) > 0) {
            foreach ($assigned_teams as $tm) {
                $team_members[$tm['id']] = db_fetchall(
                    "SELECT p.id,
                     TRIM(CONCAT(p.name, IF(COALESCE(p.firstname,'')!='',CONCAT(' ',p.firstname),''))) as fullname
                     FROM player p JOIN `team_player` tp ON tp.player_id=p.id
                     WHERE tp.team_id=? ORDER BY p.name",
                    [$tm['id']]
                );
            }
            foreach (db_fetchall(
                "SELECT d.*,
                 TRIM(CONCAT(COALESCE(p1.firstname,''), IF(COALESCE(p1.firstname,'')!='',' ',''), p1.name)) as player1_name,
                 TRIM(CONCAT(COALESCE(p2.firstname,''), IF(COALESCE(p2.firstname,'')!='',' ',''), p2.name)) as player2_name
                 FROM team_match_duel d
                 JOIN `match` m ON m.id=d.match_id
                 LEFT JOIN player p1 ON p1.id=d.player1_id
                 LEFT JOIN player p2 ON p2.id=d.player2_id
                 WHERE m.competition_id=? ORDER BY d.match_id, d.duel_order",
                [$cid]
            ) as $duel) {
                $existing_duels[$duel['match_id']][] = $duel;
            }
        }
    }

    // Bestehende Satzergebnisse laden (wenn score_mode='sets' oder 'sets_grp')
    $existing_sets = [];
    if (in_array($c['score_mode'] ?? 'match', ['sets', 'sets_grp'])) {
        foreach (db_fetchall(
            "SELECT s.* FROM match_set s
             JOIN `match` m ON m.id = s.match_id
             WHERE m.competition_id = ? ORDER BY s.match_id, s.set_order",
            [$cid]
        ) as $s) {
            $existing_sets[$s['match_id']][] = $s;
        }
    }

    if ($is_doubles) {
        $assigned_doubles = db_fetchall(
            "SELECT d.id, d.name, d.player1_id, d.player2_id,
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
        $d_sport = $t['sport'] ?? '';
        foreach ($assigned_doubles as &$d) {
            $d['registry_skill'] = _get_player_skill((int)$d['player1_id'], $d_sport)
                                 + _get_player_skill((int)$d['player2_id'], $d_sport);
        }
        unset($d);
        $unassigned_doubles = db_fetchall(
            "SELECT d.id, d.name, d.player1_id, d.player2_id FROM `double` d
             WHERE d.is_active=1 AND d.id NOT IN
             (SELECT double_id FROM competition_double WHERE competition_id = ?)
             ORDER BY d.name",
            [$cid]
        );
        foreach ($unassigned_doubles as &$ud) {
            $ud['skill'] = _get_player_skill((int)$ud['player1_id'], $d_sport)
                         + _get_player_skill((int)$ud['player2_id'], $d_sport);
        }
        unset($ud);
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
        $all_players  = db_fetchall("SELECT * FROM player WHERE is_active=1 ORDER BY name, firstname");
        $unassigned   = array_filter($all_players, fn($pl) => !in_array($pl['id'], $assigned_ids));
        $sport        = $t ? ($t['sport'] ?? '') : '';
        foreach ($assigned as &$pl) {
            $pl['registry_skill'] = _get_player_skill($pl['id'], $sport);
        }
        unset($pl);
        foreach ($unassigned as $pl) {
            $unassigned_skills[$pl['id']] = _get_player_skill($pl['id'], $sport);
        }
        $unassigned = array_values($unassigned);
    }

    $groups = [];
    if (in_array($c['phase'], ['group', 'ko', 'done'], true)) {
        $grps = db_fetchall("SELECT * FROM grp WHERE competition_id = ? ORDER BY name", [$cid]);
        require_once __DIR__ . '/../lib/standings.php';
        $grp_score_mode = in_array($c['score_mode'] ?? 'match', ['sets', 'sets_grp']) ? 'sets' : 'match';
        foreach ($grps as $g) {
            if ($is_team) {
                $standings = team_standings($g['id'], $c['seeding_order'] ?? 'desc', $grp_score_mode);
                $matches   = db_fetchall(
                    "SELECT m.*, COALESCE(t1.name,'') as p1name, COALESCE(t2.name,'') as p2name
                     FROM `match` m
                     LEFT JOIN `team` t1 ON t1.id = m.team1_id
                     LEFT JOIN `team` t2 ON t2.id = m.team2_id
                     WHERE m.group_id = ? ORDER BY m.match_order, m.id",
                    [$g['id']]
                );
                $matches = array_map(function($m) {
                    $m['player1_id'] = $m['team1_id'];
                    $m['player2_id'] = $m['team2_id'];
                    return $m;
                }, $matches);
            } elseif ($is_doubles) {
                $standings = double_standings($g['id'], $c['seeding_order'] ?? 'desc', $grp_score_mode);
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
                $standings = group_standings($g['id'], $c['seeding_order'] ?? 'desc', $grp_score_mode);
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
            $all_played = !empty($matches)
                && count(array_filter($matches, fn($m) => !(int)$m['played'])) === 0;
            $tie_ids = $all_played
                ? tied_ids_at_boundary($standings, (int)($c['advance_count'] ?? 0), $matches, 'player1_id', 'player2_id', _parse_points_mode($c['points_mode'] ?? null))
                : [];
            $groups[] = [
                'group'     => $g,
                'standings' => $standings,
                'matches'   => $matches,
                'tie_ids'   => $tie_ids,
            ];
        }
    }

    $has_open_tie = !empty(array_filter($groups, fn($gi) => !empty($gi['tie_ids'])));

    $unplayed_group = db_fetch(
        "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NOT NULL AND played=0",
        [$cid]
    )['n'];

    // Bewerb komplett: kein spielbares (beide Teilnehmer gesetzt), aber unbespieltes Spiel mehr
    // im gesamten Bewerb (Gruppen + KO/Kreuz/Doppel-KO). Steuert die Anzeige der Endplatzierung.
    $open_matches = (int)db_fetch(
        "SELECT COUNT(*) as n FROM `match`
         WHERE competition_id=? AND played=0
           AND ( (player1_id IS NOT NULL AND player2_id IS NOT NULL)
              OR (double1_id IS NOT NULL AND double2_id IS NOT NULL)
              OR (team1_id   IS NOT NULL AND team2_id   IS NOT NULL) )",
        [$cid]
    )['n'];
    $comp_complete = ($open_matches === 0);

    $ko_rounds         = [];
    $third_place_match = null;
    $places            = [];
    $dko_wb            = [];
    $dko_lb            = [];
    $dko_gf            = null;
    $cross_blocks      = [];

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
            if ($is_team) {
                $sql = "SELECT m.*, COALESCE(t1.name,'') as p1name, COALESCE(t2.name,'') as p2name,
                        '' as p1club, '' as p2club
                        FROM `match` m
                        LEFT JOIN `team` t1 ON t1.id=m.team1_id
                        LEFT JOIN `team` t2 ON t2.id=m.team2_id
                        WHERE m.competition_id=? AND m.group_id IS NULL
                        ORDER BY bracket, ko_round, ko_position";
            } elseif ($is_doubles) {
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
                if ($is_team) {
                    $m['player1_id'] = $m['team1_id'];
                    $m['player2_id'] = $m['team2_id'];
                } elseif ($is_doubles) {
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
        } elseif ($c['mode'] === 'groups_cross') {
            require_once __DIR__ . '/../lib/placement_bracket.php';
            if ($is_team) {
                $cm = db_fetchall(
                    "SELECT m.*, COALESCE(t1.name,'') as p1name, COALESCE(t2.name,'') as p2name, '' as p1club, '' as p2club
                     FROM `match` m LEFT JOIN `team` t1 ON t1.id=m.team1_id LEFT JOIN `team` t2 ON t2.id=m.team2_id
                     WHERE m.competition_id=? AND m.group_id IS NULL AND m.bracket LIKE 'C%'
                     ORDER BY m.bracket, m.ko_round, m.ko_position", [$cid]);
                $cm = array_map(function($m){ $m['player1_id']=$m['team1_id']; $m['player2_id']=$m['team2_id']; return $m; }, $cm);
            } elseif ($is_doubles) {
                $cm = db_fetchall(
                    "SELECT m.*, COALESCE(CONCAT(dp1a.name,' / ',dp1b.name),'') as p1name,
                     COALESCE(CONCAT(dp2a.name,' / ',dp2b.name),'') as p2name, '' as p1club, '' as p2club
                     FROM `match` m
                     LEFT JOIN `double` d1 ON d1.id=m.double1_id LEFT JOIN `double` d2 ON d2.id=m.double2_id
                     LEFT JOIN player dp1a ON dp1a.id=d1.player1_id LEFT JOIN player dp1b ON dp1b.id=d1.player2_id
                     LEFT JOIN player dp2a ON dp2a.id=d2.player1_id LEFT JOIN player dp2b ON dp2b.id=d2.player2_id
                     WHERE m.competition_id=? AND m.group_id IS NULL AND m.bracket LIKE 'C%'
                     ORDER BY m.bracket, m.ko_round, m.ko_position", [$cid]);
                $cm = array_map(function($m){ $m['player1_id']=$m['double1_id']; $m['player2_id']=$m['double2_id']; return $m; }, $cm);
            } else {
                $cm = db_fetchall(
                    "SELECT m.*, TRIM(CONCAT(p1.name, IF(COALESCE(p1.firstname,'')!='',CONCAT(' ',p1.firstname),''))) as p1name,
                     TRIM(CONCAT(p2.name, IF(COALESCE(p2.firstname,'')!='',CONCAT(' ',p2.firstname),''))) as p2name,
                     p1.club as p1club, p2.club as p2club
                     FROM `match` m LEFT JOIN player p1 ON p1.id=m.player1_id LEFT JOIN player p2 ON p2.id=m.player2_id
                     WHERE m.competition_id=? AND m.group_id IS NULL AND m.bracket LIKE 'C%'
                     ORDER BY m.bracket, m.ko_round, m.ko_position", [$cid]);
            }
            $byblock = [];
            foreach ($cm as $m) { $byblock[$m['bracket']][] = $m; }
            foreach ($byblock as $tag => $ms) {
                $r1 = array_values(array_filter($ms, fn($x) => (int)$x['ko_round'] === 1));
                $S  = 2 * count($r1);
                $blockLo = $r1 ? (int)$r1[0]['place_lo'] : 1;
                $real = 0;
                foreach ($r1 as $x) { if (!empty($x['player1_id'])) $real++; if (!empty($x['player2_id'])) $real++; }
                $hi = $blockLo + max(1, $real) - 1;
                $label = $real > 1 ? "Plätze {$blockLo}–{$hi}" : "Platz {$blockLo}";
                $rounds = [];
                foreach ($ms as $m) {
                    $r  = (int)$m['ko_round'];
                    $ps = $S > 0 ? ($S >> ($r - 1)) : 2;
                    $lo = (int)$m['place_lo']; $ph = $lo + $ps - 1;
                    $m['place_label'] = $ps <= 2 ? "Pl. {$lo}/{$ph}" : "Pl. {$lo}–{$ph}";
                    $rounds[$r]['matches'][] = $m;
                }
                ksort($rounds);
                $cross_blocks[] = ['tag' => $tag, 'label' => $label, 'rounds' => array_values($rounds)];
            }
            $nameById = [];
            foreach ($cm as $m) {
                if (!empty($m['player1_id'])) $nameById[(int)$m['player1_id']] = ['name' => $m['p1name'], 'club' => $m['p1club'] ?? ''];
                if (!empty($m['player2_id'])) $nameById[(int)$m['player2_id']] = ['name' => $m['p2name'], 'club' => $m['p2club'] ?? ''];
            }
            foreach (placement_final_places($cid) as $place => $pid) {
                $places[] = ['rank' => $place, 'name' => $nameById[$pid]['name'] ?? '?', 'club' => $nameById[$pid]['club'] ?? ''];
            }
        } else {
            if ($is_team) {
                $ko_matches = db_fetchall(
                    "SELECT m.*, COALESCE(t1.name,'') as p1name, COALESCE(t2.name,'') as p2name,
                     '' as p1club, '' as p2club
                     FROM `match` m
                     LEFT JOIN `team` t1 ON t1.id = m.team1_id
                     LEFT JOIN `team` t2 ON t2.id = m.team2_id
                     WHERE m.competition_id = ? AND m.group_id IS NULL
                     ORDER BY m.ko_round DESC, m.ko_position",
                    [$cid]
                );
                $ko_matches = array_map(function($m) {
                    $m['player1_id'] = $m['team1_id'];
                    $m['player2_id'] = $m['team2_id'];
                    return $m;
                }, $ko_matches);
            } elseif ($is_doubles) {
                $ko_matches = db_fetchall(
                    "SELECT m.*,
                     COALESCE(CONCAT(dp1a.name,' / ',dp1b.name),'') as p1name,
                     COALESCE(CONCAT(dp2a.name,' / ',dp2b.name),'') as p2name,
                     CASE WHEN COALESCE(dp1a.club,'')='' THEN COALESCE(dp1b.club,'') WHEN COALESCE(dp1b.club,'')='' THEN COALESCE(dp1a.club,'') WHEN dp1a.club=dp1b.club THEN dp1a.club ELSE CONCAT(dp1a.club,' / ',dp1b.club) END as p1club,
                     CASE WHEN COALESCE(dp2a.club,'')='' THEN COALESCE(dp2b.club,'') WHEN COALESCE(dp2b.club,'')='' THEN COALESCE(dp2a.club,'') WHEN dp2a.club=dp2b.club THEN dp2a.club ELSE CONCAT(dp2a.club,' / ',dp2b.club) END as p2club
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
            // Platzierungen aus $rounds_dict ermitteln — enthält korrekte Namen für Singles + Doppel
            $final = null;
            foreach ($rounds_dict[2] ?? [] as $m) {
                if ($m['played']) { $final = $m; break; }
            }
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
                $third = null;
                foreach ($rounds_dict[3] ?? [] as $m) {
                    if ($m['played']) { $third = $m; break; }
                }
                if ($third) {
                    if ($third['score1'] > $third['score2']) {
                        $places[] = ['rank'=>3,'name'=>$third['p1name'],'club'=>$third['p1club']];
                        $places[] = ['rank'=>4,'name'=>$third['p2name'],'club'=>$third['p2club']];
                    } else {
                        $places[] = ['rank'=>3,'name'=>$third['p2name'],'club'=>$third['p2club']];
                        $places[] = ['rank'=>4,'name'=>$third['p1name'],'club'=>$third['p1club']];
                    }
                } elseif (!$c['third_place']) {
                    // Kein Spiel um Platz 3 — Halbfinal-Verlierer teilen sich Platz 3
                    foreach ($rounds_dict[4] ?? [] as $s) {
                        if (!$s['played']) continue;
                        $loser_name = $s['score1'] > $s['score2'] ? $s['p2name'] : $s['p1name'];
                        $loser_club = $s['score1'] > $s['score2'] ? $s['p2club'] : $s['p1club'];
                        $places[] = ['rank' => 3, 'name' => $loser_name, 'club' => $loser_club];
                    }
                }
            }
        }
    }

    // Endplatzierung aus den Gruppentabellen nur bei reiner Gruppenphase (kein Finale).
    // Bei Kreuzspielen (advance_count=0, aber Platzierungsrunde folgt) NICHT vorab anzeigen.
    if ((int)$c['advance_count'] === 0 && $c['mode'] !== 'groups_cross' && (int)$unplayed_group === 0 && $groups) {
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
        if ($is_team) {
            $seed_rows = db_fetchall(
                "SELECT team_id FROM competition_team WHERE competition_id=? ORDER BY $seed_order_sql, team_id",
                [$cid]
            );
            foreach ($seed_rows as $i => $sr) {
                $rank = $i + 1;
                $lbl  = $rank <= 2 ? (string)$rank : ((($hi = 1 << (int)ceil(log($rank, 2))) >> 1) + 1) . '-' . $hi;
                $ko_seedings[(int)$sr['team_id']] = $lbl;
            }
        } elseif ($is_doubles) {
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
        if ($is_team) {
            $played_ko = db_fetch(
                "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NULL AND ko_round != 3
                 AND team1_id IS NOT NULL AND team2_id IS NOT NULL AND played=1",
                [$cid]
            )['n'];
        } elseif ($is_doubles) {
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
        'is_doubles' => $is_doubles, 'is_team' => $is_team,
        'assigned' => $assigned, 'unassigned' => $unassigned,
        'unassigned_skills' => $unassigned_skills,
        'assigned_doubles' => $assigned_doubles,
        'unassigned_doubles' => $unassigned_doubles,
        'assigned_teams' => $assigned_teams,
        'unassigned_teams' => $unassigned_teams,
        'team_members' => $team_members ?? [],
        'existing_duels' => $existing_duels ?? [],
        'existing_sets' => $existing_sets,
        'confirmed_regs' => $confirmed_regs ?? [],
        'groups' => $groups, 'ko_rounds' => $ko_rounds,
        'third_place_match' => $third_place_match,
        'unplayed_group' => $unplayed_group, 'has_open_tie' => $has_open_tie, 'places' => $places,
        'comp_complete' => $comp_complete,
        'ko_no_results' => $ko_no_results, 'group_no_results' => $group_no_results,
        'dko_wb' => $dko_wb, 'dko_lb' => $dko_lb, 'dko_gf' => $dko_gf,
        'dko_cap' => $cap ?? 0, 'dko_lb_total' => $lb_total ?? 0,
        'ko_seedings' => $ko_seedings,
        'cross_blocks' => $cross_blocks,
    ]);
}

function settings(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    if (!post('reopen')) {
        require_competition_open($cid);
    }

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
    $group_size        = max(3, min(20, (int)post('group_size', 4)));
    [$desired_mode, $advance_count] = _resolve_finalrunde();
    $third_place       = post('third_place') ? 1 : 0;
    $registrations_open = post('registrations_open') ? 1 : 0;
    $max_players       = max(0, (int)post('max_players', 0));
    $show_seeding      = post('show_seeding') ? 1 : 0;
    $show_skill        = post('show_skill')   ? 1 : 0;
    $seeding_order     = in_array(post('seeding_order'), ['asc', 'random'], true) ? post('seeding_order') : 'desc';
    $team_size         = max(0, (int)post('team_size', 0));
    $score_mode        = in_array(post('score_mode'), ['sets', 'sets_grp']) ? post('score_mode') : 'match';
    $show_byes         = post('show_byes') ? 1 : 0;
    $force_byes        = post('force_byes') ? 1 : 0;
    $num_courts        = max(0, min(20, (int)post('num_courts', 0)));
    $team_result_mode  = in_array(post('team_result_mode'), ['sum', 'total'], true) ? post('team_result_mode') : 'wins';
    $match_card_mode   = post('match_card_mode') === 'compact' ? 'compact' : 'fields';
    $kickoff_enabled   = post('kickoff_enabled') ? 1 : 0;
    $standings_order   = post('standings_order') === 'diff' ? 'diff' : 'h2h';
    $points_mode       = in_array(post('points_mode'), ['3-1-0', '3-2-1'], true) ? post('points_mode') : '2-1-0';
    $cross_config      = _cross_config_from_post($group_size);

    // Zeitplan: nur möglich bei Spielplätzen UND Spielrunden; Spieldauer/Startzeit dann Pflicht.
    $schedule_duration = max(0, min(600, (int)post('schedule_duration', 0)));
    $schedule_start    = preg_match('/^\d{2}:\d{2}$/', (string)post('schedule_start', '')) ? post('schedule_start') : '';
    $schedule_enabled  = (post('schedule_enabled') && $num_courts > 0 && $show_byes
                          && $schedule_duration > 0 && $schedule_start !== '') ? 1 : 0;
    if (!$schedule_enabled) { $schedule_duration = 0; $schedule_start = ''; }
    if (post('schedule_enabled') && !$schedule_enabled) {
        flash('warning', 'Zeitplan nicht aktiviert: Bitte Spielplätze und Spielrunden aktivieren sowie Spieldauer und Startzeit angeben.');
    }

    $c = db_fetch("SELECT phase, mode, is_doubles, is_team, team_size, kickoff_enabled FROM competition WHERE id=?", [$cid]);
    if (!$c) { redirect('competition/' . $cid); return; }
    $kickoff_was = (int)($c['kickoff_enabled'] ?? 0);
    if ($name) db_execute("UPDATE competition SET name=? WHERE id=?", [$name, $cid]);

    // "Spiele pro Team" nur in Setup-Phase oder in der Gruppenphase ändern, solange noch
    // kein Gruppenergebnis erfasst wurde — danach die Begegnungsstruktur sperren.
    $ts_editable = ($c['phase'] === 'setup');
    if (!$ts_editable && !empty($c['is_team']) && $c['phase'] === 'group') {
        $played_grp = (int)db_fetch(
            "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NOT NULL AND played=1",
            [$cid]
        )['n'];
        $ts_editable = ($played_grp === 0);
    }
    if (!$ts_editable) {
        $team_size = (int)$c['team_size'];   // unverändert lassen
    }

    // Modus (aus mode + finalrunde abgeleitet) speichern:
    //  - in Setup-Phase: beliebiger Moduswechsel
    //  - in Gruppenphase: nur Wechsel der Finalrunde (groups_ko ↔ groups_cross), solange die
    //    Finalrunde noch nicht ausgelost ist (struktureller Wechsel zu KO/Doppel-KO bleibt gesperrt).
    $des_ok  = in_array($desired_mode, ['groups_ko', 'groups_cross', 'ko_only', 'double_ko'], true);
    $cur_grp = in_array($c['mode'],     ['groups_ko', 'groups_cross'], true);
    $des_grp = in_array($desired_mode,  ['groups_ko', 'groups_cross'], true);
    if ($des_ok && ($c['phase'] === 'setup' || ($c['phase'] === 'group' && $cur_grp && $des_grp))) {
        db_execute("UPDATE competition SET mode=? WHERE id=?", [$desired_mode, $cid]);
        $c['mode'] = $desired_mode;
    }

    // is_doubles / is_team nur ändern solange noch keine Teilnehmer eingetragen
    $cur_is_doubles = !empty($c['is_doubles']);
    $cur_is_team    = !empty($c['is_team']);
    if ($cur_is_team) {
        $has_participants = (int)db_fetch("SELECT COUNT(*) as n FROM competition_team WHERE competition_id=?", [$cid])['n'];
    } elseif ($cur_is_doubles) {
        $has_participants = (int)db_fetch("SELECT COUNT(*) as n FROM competition_double WHERE competition_id=?", [$cid])['n'];
    } else {
        $has_participants = (int)db_fetch("SELECT COUNT(*) as n FROM competition_player WHERE competition_id=?", [$cid])['n'];
    }
    if ($c['phase'] === 'setup' && $has_participants === 0) {
        $comp_type  = post('comp_type', 'single');
        $is_team    = $comp_type === 'team'    ? 1 : 0;
        $is_doubles = $comp_type === 'doubles' ? 1 : 0;
        db_execute("UPDATE competition SET is_doubles=?, is_team=? WHERE id=?", [$is_doubles, $is_team, $cid]);
        $c['is_doubles'] = $is_doubles;
        $c['is_team']    = $is_team;
    }

    if (in_array($c['mode'], ['ko_only', 'double_ko'], true)) {
        db_execute("UPDATE competition SET third_place=?, registrations_open=?, max_players=?, show_seeding=?, show_skill=?, seeding_order=?, team_size=?, score_mode=?, num_courts=?, team_result_mode=?, match_card_mode=?, kickoff_enabled=? WHERE id=?",
            [$third_place, $registrations_open, $max_players, $show_seeding, $show_skill, $seeding_order, $team_size, $score_mode, $num_courts, $team_result_mode, $match_card_mode, $kickoff_enabled, $cid]);
    } elseif ($c && $c['phase'] === 'setup') {
        db_execute("UPDATE competition SET group_size=?, advance_count=?, third_place=?, registrations_open=?, max_players=?, show_skill=?, seeding_order=?, team_size=?, score_mode=?, show_byes=?, force_byes=?, num_courts=?, team_result_mode=?, match_card_mode=?, kickoff_enabled=?, standings_order=?, points_mode=?, cross_config=?, schedule_enabled=?, schedule_duration=?, schedule_start=? WHERE id=?",
            [$group_size, $advance_count, $third_place, $registrations_open, $max_players, $show_skill, $seeding_order, $team_size, $score_mode, $show_byes, $force_byes, $num_courts, $team_result_mode, $match_card_mode, $kickoff_enabled, $standings_order, $points_mode, $cross_config, $schedule_enabled, $schedule_duration, $schedule_start, $cid]);
    } else {
        db_execute("UPDATE competition SET advance_count=?, third_place=?, registrations_open=?, max_players=?, show_skill=?, seeding_order=?, team_size=?, score_mode=?, show_byes=?, force_byes=?, num_courts=?, team_result_mode=?, match_card_mode=?, kickoff_enabled=?, standings_order=?, points_mode=?, cross_config=?, schedule_enabled=?, schedule_duration=?, schedule_start=? WHERE id=?",
            [$advance_count, $third_place, $registrations_open, $max_players, $show_skill, $seeding_order, $team_size, $score_mode, $show_byes, $force_byes, $num_courts, $team_result_mode, $match_card_mode, $kickoff_enabled, $standings_order, $points_mode, $cross_config, $schedule_enabled, $schedule_duration, $schedule_start, $cid]);
    }
    // Bei verkleinertem team_size in der Gruppenphase verwaiste Aufstellungs-Zeilen entfernen.
    if ($ts_editable && $c['phase'] === 'group' && !empty($c['is_team'])) {
        db_execute(
            "DELETE d FROM team_match_duel d
             JOIN `match` m ON m.id = d.match_id
             WHERE m.competition_id=? AND d.duel_order >= ?",
            [$cid, $team_size]
        );
    }
    assign_courts($cid);
    // Anwurf nur bei einer kompletten Auslosung neu vergeben — hier daher nur, wenn die Option
    // selbst umgeschaltet wurde (an → erzeugen, aus → löschen). Bleibt der Schalter unverändert,
    // bleibt eine bestehende Anwurf-Auslosung erhalten.
    if ($kickoff_was !== $kickoff_enabled) {
        assign_kickoff($cid);
    }
    flash('success', 'Einstellungen gespeichert.');
    redirect('competition/' . $cid);
}

// Manuelle Platzzuordnung pro Gruppe speichern (Formularfeld courts[<group_id>] = "1,2").
function save_courts(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    $c = db_fetch("SELECT num_courts FROM competition WHERE id=?", [$cid]);
    if (!$c) { redirect('competition/' . $cid); return; }
    $N = (int)$c['num_courts'];
    $valid_gids = array_column(db_fetchall("SELECT id FROM grp WHERE competition_id=?", [$cid]), 'id');
    foreach ((array)($_POST['courts'] ?? []) as $gid_str => $val) {
        $gid = (int)$gid_str;
        if (!in_array($gid, $valid_gids)) continue;
        $courts = parse_courts((string)$val, $N);
        db_execute("UPDATE grp SET courts=? WHERE id=?", [implode(',', $courts), $gid]);
    }
    assign_courts($cid);
    flash('success', 'Platzzuordnung gespeichert.');
    redirect('competition/' . $cid);
}

function save_pauses(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    $c = db_fetch("SELECT schedule_enabled, show_byes FROM competition WHERE id=?", [$cid]);
    if (!$c) { redirect('competition/' . $cid); return; }
    // Pause nur sinnvoll bei aktivem Zeitplan + Spielrunden; sonst Eingaben verwerfen.
    $allow = !empty($c['schedule_enabled']) && !empty($c['show_byes']);
    $valid_gids = array_column(db_fetchall("SELECT id FROM grp WHERE competition_id=?", [$cid]), 'id');
    $starts = (array)($_POST['pause_start'] ?? []);
    $durs   = (array)($_POST['pause_dur'] ?? []);
    foreach ($valid_gids as $gid) {
        $start = preg_match('/^\d{2}:\d{2}$/', (string)($starts[$gid] ?? '')) ? $starts[$gid] : '';
        $dur   = max(0, min(600, (int)($durs[$gid] ?? 0)));
        if (!$allow || $start === '' || $dur <= 0) { $start = ''; $dur = 0; }
        db_execute("UPDATE grp SET pause_start=?, pause_duration=? WHERE id=?", [$start, $dur, $gid]);
    }
    flash('success', 'Pausen gespeichert.');
    redirect('competition/' . $cid);
}

function delete(array $p): void {
    require_edit();
    csrf_verify();
    require_competition_open((int)$p['id']);
    $c = db_fetch("SELECT tournament_id FROM competition WHERE id=?", [(int)$p['id']]);
    $tid = $c ? $c['tournament_id'] : null;
    db_execute("DELETE FROM competition WHERE id=?", [(int)$p['id']]);
    redirect($tid ? 'tournament/' . $tid : '');
}

function add_player(array $p): void {
    require_edit();
    csrf_verify();
    $cid  = (int)$p['id'];
    require_competition_open($cid);
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
    redirect('competition/' . $cid . '#tab-players');
}

function remove_player(array $p): void {
    require_edit();
    csrf_verify();
    require_competition_open((int)$p['id']);
    db_execute("DELETE FROM competition_player WHERE competition_id=? AND player_id=?",
        [(int)$p['id'], (int)$p['pid']]);
    redirect('competition/' . $p['id'] . '#tab-players');
}

function remove_all_players(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
    $c = db_fetch("SELECT phase FROM competition WHERE id=?", [$cid]);
    if (!$c || $c['phase'] !== 'setup') { redirect('competition/' . $cid); return; }
    db_execute("DELETE FROM competition_player WHERE competition_id=?", [$cid]);
    flash('success', 'Alle Spieler wurden vom Bewerb entfernt.');
    redirect('competition/' . $cid . '#tab-players');
}

function add_double(array $p): void {
    require_edit();
    csrf_verify();
    $cid  = (int)$p['id'];
    require_competition_open($cid);
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
    redirect('competition/' . $cid . '#tab-players');
}

function pair_double_from_reg(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
    $p1  = (int)post('player1_id');
    $p2  = (int)post('player2_id');
    if (!$p1 || !$p2 || $p1 === $p2) {
        flash('danger', 'Zwei verschiedene Spieler auswählen.');
        redirect('competition/' . $cid . '#tab-players');
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
        redirect('competition/' . $cid . '#tab-players');
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
            redirect('competition/' . $cid . '#tab-players');
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
    redirect('competition/' . $cid . '#tab-players');
}

function remove_double(array $p): void {
    require_edit();
    csrf_verify();
    require_competition_open((int)$p['id']);
    db_execute("DELETE FROM competition_double WHERE competition_id=? AND double_id=?",
        [(int)$p['id'], (int)$p['did']]);
    redirect('competition/' . $p['id'] . '#tab-players');
}

function remove_all_doubles(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
    $c = db_fetch("SELECT phase FROM competition WHERE id=?", [$cid]);
    if (!$c || $c['phase'] !== 'setup') { redirect('competition/' . $cid); return; }
    db_execute("DELETE FROM competition_double WHERE competition_id=?", [$cid]);
    flash('success', 'Alle Doppel wurden vom Bewerb entfernt.');
    redirect('competition/' . $cid . '#tab-players');
}

function update_player_skill(array $p): void {
    require_edit();
    csrf_verify();
    $cid   = (int)$p['id'];
    require_competition_open($cid);
    $pid   = (int)$p['pid'];
    $skill = max(0, (float)post('skill', 0));
    db_execute(
        "UPDATE competition_player SET skill=? WHERE competition_id=? AND player_id=?",
        [$skill, $cid, $pid]
    );
    redirect('competition/' . $cid . '#tab-players');
}

function update_double_skill(array $p): void {
    require_edit();
    csrf_verify();
    $cid   = (int)$p['id'];
    require_competition_open($cid);
    $did   = (int)$p['did'];
    $skill = max(0, (float)post('skill', 0));
    db_execute(
        "UPDATE competition_double SET skill=? WHERE competition_id=? AND double_id=?",
        [$skill, $cid, $did]
    );
    redirect('competition/' . $cid . '#tab-players');
}

function draw_groups(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
    $c   = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    $is_team    = !empty($c['is_team']);
    $is_doubles = !$is_team && !empty($c['is_doubles']);
    $force_bye  = !empty($c['force_byes']);

    $skill_order = ($c['seeding_order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    if ($is_team) {
        $rows = db_fetchall(
            "SELECT ct.team_id as participant_id, COALESCE(ct.skill, 0) as skill
             FROM competition_team ct WHERE ct.competition_id=? ORDER BY ct.skill $skill_order, ct.team_id",
            [$cid]
        );
    } elseif ($is_doubles) {
        $rows = db_fetchall(
            "SELECT cd.double_id as participant_id, COALESCE(cd.skill, 0) as skill
             FROM competition_double cd WHERE cd.competition_id=? ORDER BY cd.skill $skill_order, cd.double_id",
            [$cid]
        );
    } else {
        $rows = db_fetchall(
            "SELECT cp.player_id as participant_id, COALESCE(cp.skill, 0) as skill
             FROM competition_player cp WHERE cp.competition_id=? ORDER BY cp.skill $skill_order, cp.player_id",
            [$cid]
        );
    }
    $n = count($rows);
    $kind = $is_team ? 'Teams' : ($is_doubles ? 'Doppel' : 'Spieler');
    if ($n < 3) { flash('danger', "Mindestens 3 $kind erforderlich."); redirect('competition/'.$cid); return; }
    if ($n > 64) { flash('danger', 'Maximal 64 erlaubt.'); redirect('competition/'.$cid); return; }

    $group_size = (int)$c['group_size'];
    $num_groups = max(1, (int)round($n / $group_size));
    while ($num_groups > 1 && $n / $num_groups < 3) $num_groups--;
    while ($n / $num_groups > 20) $num_groups++;

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

        // Standard-Platzverteilung als Startwert (manuell überschreibbar)
        $court_blocks = default_group_courts((int)($c['num_courts'] ?? 0), $num_groups);
        foreach ($group_ids as $gi => $gid) {
            db_execute("UPDATE grp SET courts=? WHERE id=?", [implode(',', $court_blocks[$gi] ?? []), $gid]);
        }

        $participant_ids = array_column($rows, 'participant_id');
        // Setzungsreihenfolge "random" → komplett zufällige Gruppeneinteilung (keine
        // Stärke-Setzung): Teilnehmerliste UND Gruppenreihenfolge mischen. Das Mischen der
        // Gruppen sorgt dafür, dass bei nicht teilbarer Teilnehmerzahl der "Rest" in einer
        // zufälligen Gruppe landet (sonst bekäme immer Gruppe A einen Teilnehmer mehr).
        $dist_groups = $group_ids;
        if (($c['seeding_order'] ?? 'desc') === 'random') {
            shuffle($participant_ids);
            shuffle($dist_groups);
        }
        for ($start = 0; $start < $n; $start += $num_groups) {
            $bucket = array_slice($participant_ids, $start, $num_groups);
            shuffle($bucket);
            foreach ($bucket as $j => $pid) {
                if (isset($dist_groups[$j])) {
                    if ($is_team) {
                        db_execute("INSERT INTO group_team (group_id, team_id) VALUES (?,?)",
                            [$dist_groups[$j], $pid]);
                    } elseif ($is_doubles) {
                        db_execute("INSERT INTO group_double (group_id, double_id) VALUES (?,?)",
                            [$dist_groups[$j], $pid]);
                    } else {
                        db_execute("INSERT INTO group_player (group_id, player_id) VALUES (?,?)",
                            [$dist_groups[$j], $pid]);
                    }
                }
            }
        }

        foreach ($group_ids as $gid) {
            if ($is_team) {
                $pids = array_column(
                    db_fetchall("SELECT team_id FROM group_team WHERE group_id=?", [$gid]),
                    'team_id'
                );
                $sched = round_robin_schedule_rounds($pids, $force_bye);
                foreach ($sched['matches'] as $order => $mm) {
                    db_execute(
                        "INSERT INTO `match` (competition_id, group_id, team1_id, team2_id, match_order, round_no) VALUES (?,?,?,?,?,?)",
                        [$cid, $gid, $mm['p1'], $mm['p2'], $order + 1, $mm['round']]
                    );
                }
            } elseif ($is_doubles) {
                $pids = array_column(
                    db_fetchall("SELECT double_id FROM group_double WHERE group_id=?", [$gid]),
                    'double_id'
                );
                $sched = round_robin_schedule_rounds($pids, $force_bye);
                foreach ($sched['matches'] as $order => $mm) {
                    db_execute(
                        "INSERT INTO `match` (competition_id, group_id, double1_id, double2_id, match_order, round_no) VALUES (?,?,?,?,?,?)",
                        [$cid, $gid, $mm['p1'], $mm['p2'], $order + 1, $mm['round']]
                    );
                }
            } else {
                $pids = array_column(
                    db_fetchall("SELECT player_id FROM group_player WHERE group_id=?", [$gid]),
                    'player_id'
                );
                $sched = round_robin_schedule_rounds($pids, $force_bye);
                foreach ($sched['matches'] as $order => $mm) {
                    db_execute(
                        "INSERT INTO `match` (competition_id, group_id, player1_id, player2_id, match_order, round_no) VALUES (?,?,?,?,?,?)",
                        [$cid, $gid, $mm['p1'], $mm['p2'], $order + 1, $mm['round']]
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
    assign_courts($cid);
    assign_kickoff($cid);
    flash('success', 'Gruppen wurden ausgelost!');
    redirect('competition/' . $cid);
}

// Modus groups_cross: nach der Gruppenphase vollständige Platzierungs-Brackets auslosen.
function draw_cross(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c || $c['mode'] !== 'groups_cross') { redirect('competition/' . $cid); return; }
    $is_team    = !empty($c['is_team']);
    $is_doubles = !$is_team && !empty($c['is_doubles']);

    $unplayed = (int)db_fetch(
        "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NOT NULL AND played=0",
        [$cid]
    )['n'];
    if ($unplayed > 0) {
        flash('warning', "Noch $unplayed Gruppenspiele offen!");
        redirect('competition/' . $cid);
        return;
    }

    require_once __DIR__ . '/../lib/placement_bracket.php';
    $flags  = $c['cross_config'] !== '' ? explode(',', $c['cross_config']) : [];
    $blocks = build_placement_blocks($cid, $is_team, $is_doubles, $c['seeding_order'] ?? 'desc', $flags);
    if (array_sum(array_column($blocks, 'count')) < 2) {
        flash('danger', 'Nicht genug Teilnehmer für Kreuzspiele.');
        redirect('competition/' . $cid);
        return;
    }
    draw_placement($cid, $blocks, $is_doubles, $is_team);
    assign_courts($cid);
    assign_kickoff($cid);
    flash('success', 'Kreuzspiele wurden ausgelost!');
    redirect('competition/' . $cid);
}

function draw_ko(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
    $c   = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    $is_team    = !empty($c['is_team']);
    $is_doubles = !$is_team && !empty($c['is_doubles']);

    $unplayed = db_fetch(
        "SELECT COUNT(*) as n FROM `match` WHERE competition_id=? AND group_id IS NOT NULL AND played=0",
        [$cid]
    )['n'];
    if ($unplayed > 0) {
        flash('warning', "Noch $unplayed Gruppenspiele offen!");
        redirect('competition/' . $cid);
        return;
    }

    require_once __DIR__ . '/../lib/standings.php';
    $grps_check = db_fetchall("SELECT * FROM grp WHERE competition_id=? ORDER BY name", [$cid]);
    foreach ($grps_check as $g) {
        $st = $is_team
            ? team_standings($g['id'])
            : ($is_doubles ? double_standings($g['id']) : group_standings($g['id']));
        if ($is_team) {
            $p1c = 'team1_id'; $p2c = 'team2_id';
        } elseif ($is_doubles) {
            $p1c = 'double1_id'; $p2c = 'double2_id';
        } else {
            $p1c = 'player1_id'; $p2c = 'player2_id';
        }
        $grp_matches = db_fetchall("SELECT * FROM `match` WHERE group_id=? AND played=1", [$g['id']]);
        if (!empty(tied_ids_at_boundary($st, (int)$c['advance_count'], $grp_matches, $p1c, $p2c, _parse_points_mode($c['points_mode'] ?? null)))) {
            flash('warning', 'Bitte zuerst alle Tabellengleichstände auflösen, bevor die KO-Phase ausgelost wird.');
            redirect('competition/' . $cid);
            return;
        }
    }

    $grps         = db_fetchall("SELECT * FROM grp WHERE competition_id=? ORDER BY name", [$cid]);
    $advance_count = (int)$c['advance_count'];

    $firsts = []; $seconds = [];
    foreach ($grps as $g) {
        $st = $is_team
            ? team_standings($g['id'], $c['seeding_order'] ?? 'desc')
            : ($is_doubles ? double_standings($g['id'], $c['seeding_order'] ?? 'desc') : group_standings($g['id'], $c['seeding_order'] ?? 'desc'));
        if ($st) $firsts[] = ['gid' => $g['id'], 'pid' => $st[0]['id']];
        if ($advance_count >= 2 && count($st) >= 2)
            $seconds[] = ['gid' => $g['id'], 'pid' => $st[1]['id']];
    }

    shuffle($firsts);
    if ($seconds) shuffle($seconds);

    $seedings = array_merge(array_column($firsts, 'pid'), array_column($seconds, 'pid'));
    $gids     = array_merge(array_column($firsts, 'gid'), array_column($seconds, 'gid'));
    $n        = count($seedings);

    $kind = $is_team ? 'Teams' : ($is_doubles ? 'Doppel' : 'Spieler');
    if ($n < 2) {
        flash('danger', "Nicht genug $kind für KO-Phase.");
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

    _build_ko_bracket($cid, $bracket, (bool)$c['third_place'], $is_doubles, $is_team);
    assign_courts($cid);
    assign_kickoff($cid);
    flash('success', 'KO-Bracket wurde ausgelost!');
    redirect('competition/' . $cid);
}

function draw_ko_direct(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
    $c   = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    $is_team    = !empty($c['is_team']);
    $is_doubles = !$is_team && !empty($c['is_doubles']);

    if ($c['phase'] !== 'setup') {
        flash('warning', 'Auslosung nur in der Einrichtungsphase möglich.');
        redirect('competition/' . $cid);
        return;
    }
    $draw_order_asc = ($c['seeding_order'] ?? 'desc') === 'asc';
    if ($is_team) {
        $draw_order_sql = $draw_order_asc
            ? "CASE WHEN COALESCE(ct.skill,0)=0 THEN 1 ELSE 0 END, COALESCE(ct.skill,0) ASC, ct.team_id"
            : "COALESCE(ct.skill,0) DESC, ct.team_id";
        $rows = db_fetchall(
            "SELECT ct.team_id FROM competition_team ct WHERE ct.competition_id=? ORDER BY $draw_order_sql",
            [$cid]
        );
        $participants = array_column($rows, 'team_id');
    } elseif ($is_doubles) {
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
    // Setzungsreihenfolge "random" → KO-Bracket komplett zufällig setzen (keine Stärke-Setzung).
    if (($c['seeding_order'] ?? 'desc') === 'random') shuffle($participants);
    $n = count($participants);
    $kind = $is_team ? 'Teams' : ($is_doubles ? 'Doppel' : 'Spieler');
    if ($n < 2) { flash('danger', "Mindestens 2 $kind erforderlich."); redirect('competition/'.$cid); return; }
    if ($n > 64) { flash('danger', 'Maximal 64 erlaubt.'); redirect('competition/'.$cid); return; }

    if ($c['mode'] === 'double_ko') {
        require_once __DIR__ . '/../lib/double_ko_bracket.php';
        draw_double_ko($cid, $participants, $is_doubles, $is_team);
        assign_courts($cid);
        assign_kickoff($cid);
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

    _build_ko_bracket($cid, $bracket, (bool)$c['third_place'], $is_doubles, $is_team);
    assign_courts($cid);
    assign_kickoff($cid);
    flash('success', 'KO-Bracket wurde ausgelost!');
    redirect('competition/' . $cid);
}

function reset_groups(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
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
    require_competition_open($cid);
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
    require_competition_open($cid);
    $c   = db_fetch("SELECT is_doubles, is_team, force_byes FROM competition WHERE id=?", [$cid]);
    $is_team    = $c && !empty($c['is_team']);
    $is_doubles = $c && !$is_team && !empty($c['is_doubles']);
    $force_bye  = $c && !empty($c['force_byes']);

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
    if ($is_team) {
        $valid_ids = array_column(
            db_fetchall("SELECT team_id FROM competition_team WHERE competition_id=?", [$cid]),
            'team_id'
        );
    } elseif ($is_doubles) {
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
            if ($is_team) {
                db_execute("DELETE FROM group_team WHERE group_id IN ($ph)", $valid_gids);
            } elseif ($is_doubles) {
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
                if ($is_team) {
                    db_execute("INSERT IGNORE INTO group_team (group_id, team_id) VALUES (?,?)", [$gid, $pid]);
                } elseif ($is_doubles) {
                    db_execute("INSERT IGNORE INTO group_double (group_id, double_id) VALUES (?,?)", [$gid, $pid]);
                } else {
                    db_execute("INSERT IGNORE INTO group_player (group_id, player_id) VALUES (?,?)", [$gid, $pid]);
                }
            }
            if ($is_team) {
                $ids_arr = array_column(
                    db_fetchall("SELECT team_id FROM group_team WHERE group_id=?", [$gid]),
                    'team_id'
                );
                $sched = round_robin_schedule_rounds($ids_arr, $force_bye);
                foreach ($sched['matches'] as $order => $mm) {
                    db_execute(
                        "INSERT INTO `match` (competition_id, group_id, team1_id, team2_id, match_order, round_no) VALUES (?,?,?,?,?,?)",
                        [$cid, $gid, $mm['p1'], $mm['p2'], $order + 1, $mm['round']]
                    );
                }
            } elseif ($is_doubles) {
                $ids_arr = array_column(
                    db_fetchall("SELECT double_id FROM group_double WHERE group_id=?", [$gid]),
                    'double_id'
                );
                $sched = round_robin_schedule_rounds($ids_arr, $force_bye);
                foreach ($sched['matches'] as $order => $mm) {
                    db_execute(
                        "INSERT INTO `match` (competition_id, group_id, double1_id, double2_id, match_order, round_no) VALUES (?,?,?,?,?,?)",
                        [$cid, $gid, $mm['p1'], $mm['p2'], $order + 1, $mm['round']]
                    );
                }
            } else {
                $ids_arr = array_column(
                    db_fetchall("SELECT player_id FROM group_player WHERE group_id=?", [$gid]),
                    'player_id'
                );
                $sched = round_robin_schedule_rounds($ids_arr, $force_bye);
                foreach ($sched['matches'] as $order => $mm) {
                    db_execute(
                        "INSERT INTO `match` (competition_id, group_id, player1_id, player2_id, match_order, round_no) VALUES (?,?,?,?,?,?)",
                        [$cid, $gid, $mm['p1'], $mm['p2'], $order + 1, $mm['round']]
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
    assign_courts($cid);
    assign_kickoff($cid);
    flash('success', 'Gruppen neu eingeteilt.');
    redirect('competition/' . $cid);
}

function ko_reorder(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
    $c   = db_fetch("SELECT third_place, is_doubles, is_team FROM competition WHERE id=?", [$cid]);
    $is_team    = $c && !empty($c['is_team']);
    $is_doubles = $c && !$is_team && !empty($c['is_doubles']);

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

    if ($is_team) {
        $valid_ids = array_column(
            db_fetchall("SELECT team_id FROM competition_team WHERE competition_id=?", [$cid]),
            'team_id'
        );
    } elseif ($is_doubles) {
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

    _build_ko_bracket($cid, $bracket, (bool)($c['third_place'] ?? false), $is_doubles, $is_team);
    flash('success', 'KO-Bracket aktualisiert.');
    redirect('competition/' . $cid);
}

function seedings_save(array $p): void {
    require_edit();
    csrf_verify();
    $cid   = (int)$p['id'];
    require_competition_open($cid);
    $c     = db_fetch("SELECT third_place, is_doubles, is_team FROM competition WHERE id=?", [$cid]);
    $is_team    = $c && !empty($c['is_team']);
    $is_doubles = $c && !$is_team && !empty($c['is_doubles']);
    $order = post('player_order', '');
    $pids  = array_filter(array_map('intval', explode(',', $order)));

    if ($is_team) {
        $valid_ids = array_column(
            db_fetchall("SELECT team_id FROM competition_team WHERE competition_id=?", [$cid]),
            'team_id'
        );
    } elseif ($is_doubles) {
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

    _build_ko_bracket($cid, $bracket, (bool)($c['third_place'] ?? false), $is_doubles, $is_team);
    flash('success', 'Setzung gespeichert.');
    redirect('competition/' . $cid);
}

function save_group_tiebreak(array $p): void {
    require_admin();
    csrf_verify();
    $gid  = (int)$p['gid'];
    $grp  = db_fetch("SELECT competition_id FROM grp WHERE id=?", [$gid]);
    if (!$grp) { redirect('players'); return; }
    $cid  = (int)$grp['competition_id'];
    require_competition_open($cid);
    $c    = db_fetch("SELECT is_doubles, is_team FROM competition WHERE id=?", [$cid]);
    $type = post('type', 'player');
    $order = $_POST['order'] ?? [];

    if ($type === 'team') {
        $tbl = 'group_team';   $col = 'team_id';
    } elseif ($type === 'double') {
        $tbl = 'group_double'; $col = 'double_id';
    } else {
        $tbl = 'group_player'; $col = 'player_id';
    }
    foreach ($order as $eid => $rank) {
        db_execute(
            "UPDATE `$tbl` SET tiebreak_order=? WHERE group_id=? AND $col=?",
            [(int)$rank, $gid, (int)$eid]
        );
    }
    redirect('competition/' . $cid . '#tab-gruppen');
}

// ── Internal helpers ───────────────────────────────────────────────────────────

function add_team(array $p): void {
    require_edit();
    csrf_verify();
    $cid  = (int)$p['id'];
    require_competition_open($cid);
    $tids = $_POST['team_ids'] ?? [];
    $c    = db_fetch("SELECT max_players, is_team FROM competition WHERE id=?", [$cid]);
    if (!$c || !$c['is_team']) { redirect('competition/' . $cid); return; }
    $max     = (int)($c['max_players'] ?? 0);
    $skipped = 0;
    foreach ((array)$tids as $tid_str) {
        $tid = (int)$tid_str;
        if (!$tid) continue;
        if ($max > 0) {
            $count = (int)db_fetch("SELECT COUNT(*) as n FROM competition_team WHERE competition_id=?", [$cid])['n'];
            if ($count >= $max) { $skipped++; continue; }
        }
        $t = db_fetch("SELECT skill FROM `team` WHERE id=?", [$tid]);
        if (!$t) continue;
        db_execute(
            "INSERT IGNORE INTO competition_team (competition_id, team_id, created_at, skill) VALUES (?,?,NOW(),?)",
            [$cid, $tid, $t['skill']]
        );
    }
    if ($skipped > 0) flash('warning', "Maximale Anzahl ($max) erreicht — $skipped Teams nicht hinzugefügt.");
    redirect('competition/' . $cid . '#tab-players');
}

function remove_team(array $p): void {
    require_edit();
    csrf_verify();
    require_competition_open((int)$p['id']);
    db_execute("DELETE FROM competition_team WHERE competition_id=? AND team_id=?",
        [(int)$p['id'], (int)$p['tid']]);
    redirect('competition/' . $p['id'] . '#tab-players');
}

function remove_all_teams(array $p): void {
    require_edit();
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
    $c = db_fetch("SELECT phase FROM competition WHERE id=?", [$cid]);
    if (!$c || $c['phase'] !== 'setup') { redirect('competition/' . $cid); return; }
    db_execute("DELETE FROM competition_team WHERE competition_id=?", [$cid]);
    flash('success', 'Alle Teams wurden vom Bewerb entfernt.');
    redirect('competition/' . $cid . '#tab-players');
}

function update_team_skill(array $p): void {
    require_edit();
    csrf_verify();
    $cid   = (int)$p['id'];
    require_competition_open($cid);
    $tid   = (int)$p['tid'];
    $skill = max(0, (float)post('skill', 0));
    db_execute(
        "UPDATE competition_team SET skill=? WHERE competition_id=? AND team_id=?",
        [$skill, $cid, $tid]
    );
    redirect('competition/' . $cid . '#tab-players');
}

// ── Internal helpers ───────────────────────────────────────────────────────────

function _build_ko_bracket(int $cid, array $bracket, bool $third_place, bool $is_doubles = false, bool $is_team = false): void {
    db_execute("DELETE FROM `match` WHERE competition_id=? AND group_id IS NULL", [$cid]);
    $bracket_total = count($bracket);
    if ($bracket_total < 2) return;

    $num_matches = $bracket_total / 2;
    $p1col = $is_team ? 'team1_id' : ($is_doubles ? 'double1_id' : 'player1_id');
    $p2col = $is_team ? 'team2_id' : ($is_doubles ? 'double2_id' : 'player2_id');

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
            $has_p1 = $m[$p1col];
            $has_p2 = $m[$p2col];
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
