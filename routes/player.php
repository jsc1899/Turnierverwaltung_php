<?php

const SPORTS_LIST = [
    ['tischtennis', 'Tischtennis', '🏓'],
    ['tennis',      'Tennis',      '🎾'],
    ['fussball',    'Fußball',     '⚽'],
    ['cornhole',    'Cornhole',    null],
];

function index(array $p): void {
    require_edit();
    $all_players   = db_fetchall("SELECT * FROM player ORDER BY name, firstname");
    $player_comps  = [];
    $player_skills = [];
    foreach ($all_players as $pl) {
        $player_comps[$pl['id']] = db_fetchall(
            "SELECT c.name, cp.created_at FROM competition_player cp
             JOIN competition c ON c.id=cp.competition_id
             WHERE cp.player_id=? ORDER BY c.name",
            [$pl['id']]
        );
        $skills = db_fetchall(
            "SELECT sport, skill FROM player_skill WHERE player_id=? ORDER BY sport", [$pl['id']]
        );
        $player_skills[$pl['id']] = array_column($skills, 'skill', 'sport');
    }
    $all_doubles = db_fetchall(
        "SELECT d.*,
         TRIM(CONCAT(p1.name, IF(COALESCE(p1.firstname,'')!='', CONCAT(' ', p1.firstname),''))) as p1name,
         TRIM(CONCAT(p2.name, IF(COALESCE(p2.firstname,'')!='', CONCAT(' ', p2.firstname),''))) as p2name,
         p1.club as p1club, p2.club as p2club
         FROM `double` d
         JOIN player p1 ON p1.id = d.player1_id
         JOIN player p2 ON p2.id = d.player2_id
         ORDER BY d.name",
        []
    );
    // Per-Sport-Summen für jedes Doppel vorberechnen (für Edit-Modal)
    $double_sport_skills = [];
    foreach ($all_doubles as $d) {
        $s1 = $player_skills[$d['player1_id']] ?? [];
        $s2 = $player_skills[$d['player2_id']] ?? [];
        $sports = array_column(SPORTS_LIST, 0);
        $sums = [];
        foreach ($sports as $sport) {
            $v1 = isset($s1[$sport]) ? (float)$s1[$sport] : ($sport === 'tennis' ? 10.0 : 0.0);
            $v2 = isset($s2[$sport]) ? (float)$s2[$sport] : ($sport === 'tennis' ? 10.0 : 0.0);
            $sums[$sport] = round($v1 + $v2, 1);
        }
        $double_sport_skills[$d['id']] = $sums;
    }
    $double_competitions = [];
    foreach ($all_doubles as $d) {
        $double_competitions[$d['id']] = db_fetchall(
            "SELECT c.id, c.name, t.name as tournament_name FROM competition c
             JOIN competition_double cd ON cd.competition_id = c.id
             JOIN tournament t ON t.id = c.tournament_id
             WHERE cd.double_id = ? ORDER BY t.name, c.name",
            [$d['id']]
        );
    }
    $all_teams = db_fetchall(
        "SELECT t.*, COUNT(tp.player_id) as member_count
         FROM `team` t LEFT JOIN `team_player` tp ON tp.team_id = t.id
         GROUP BY t.id ORDER BY t.name",
        []
    );
    foreach ($all_teams as &$team) {
        $team['members'] = db_fetchall(
            "SELECT p.id, TRIM(CONCAT(p.name, IF(COALESCE(p.firstname,'')!='', CONCAT(' ', p.firstname),''))) as fullname, p.club
             FROM player p JOIN `team_player` tp ON tp.player_id = p.id
             WHERE tp.team_id = ? ORDER BY p.name",
            [$team['id']]
        );
        $team['competitions'] = db_fetchall(
            "SELECT c.id, c.name, t.name as tournament_name FROM competition c
             JOIN competition_team ct ON ct.competition_id = c.id
             JOIN tournament t ON t.id = c.tournament_id
             WHERE ct.team_id = ? ORDER BY t.name, c.name",
            [$team['id']]
        );
    }
    unset($team);
    render('player/index', [
        'page_title'          => 'Spielerregister',
        'players'             => $all_players,
        'player_comps'        => $player_comps,
        'player_skills'       => $player_skills,
        'sports_list'         => SPORTS_LIST,
        'all_doubles'         => $all_doubles,
        'double_sport_skills' => $double_sport_skills,
        'double_competitions' => $double_competitions,
        'all_teams'           => $all_teams,
    ]);
}

function create_double_global(array $p): void {
    require_edit();
    csrf_verify();
    $p1 = (int)post('player1_id');
    $p2 = (int)post('player2_id');

    if (!$p1 || !$p2) {
        flash('danger', 'Beide Spieler müssen ausgewählt werden.');
        redirect('players#tab-doppel');
        return;
    }
    if ($p1 === $p2) {
        flash('danger', 'Ein Spieler kann nicht mit sich selbst ein Doppel bilden.');
        redirect('players#tab-doppel');
        return;
    }
    $existing = db_fetch(
        "SELECT id FROM `double` WHERE
         (player1_id=? AND player2_id=?) OR (player1_id=? AND player2_id=?)",
        [$p1, $p2, $p2, $p1]
    );
    if ($existing) {
        flash('warning', 'Dieses Doppel existiert bereits.');
        redirect('players#tab-doppel');
        return;
    }
    $pl1 = db_fetch("SELECT name, firstname, skill FROM player WHERE id=?", [$p1]);
    $pl2 = db_fetch("SELECT name, firstname, skill FROM player WHERE id=?", [$p2]);
    if (!$pl1 || !$pl2) {
        flash('danger', 'Spieler nicht gefunden.');
        redirect('players#tab-doppel');
        return;
    }
    $n1   = trim(($pl1['firstname'] ? $pl1['firstname'] . ' ' : '') . $pl1['name']);
    $n2   = trim(($pl2['firstname'] ? $pl2['firstname'] . ' ' : '') . $pl2['name']);
    $name = $n1 . ' / ' . $n2;
    $skill_post = post('skill', '');
    $skill = $skill_post !== '' ? (float)$skill_post : round((float)$pl1['skill'] + (float)$pl2['skill'], 1);

    db_insert(
        "INSERT INTO `double` (player1_id, player2_id, name, skill) VALUES (?,?,?,?)",
        [$p1, $p2, $name, $skill]
    );
    flash('success', 'Doppel „' . $name . '" erstellt.');
    redirect('players#tab-doppel');
}

function edit_double_global(array $p): void {
    require_edit();
    csrf_verify();
    $did  = (int)$p['did'];
    $name = trim(post('name'));
    if (!$name) {
        flash('danger', 'Name darf nicht leer sein.');
        redirect('players#tab-doppel');
        return;
    }
    $skill_raw = post('skill', '');
    if ($skill_raw !== '') {
        db_execute("UPDATE `double` SET name=?, skill=? WHERE id=?", [$name, (float)$skill_raw, $did]);
    } else {
        db_execute("UPDATE `double` SET name=? WHERE id=?", [$name, $did]);
    }
    flash('success', 'Doppel gespeichert.');
    redirect('players#tab-doppel');
}

function delete_double_global(array $p): void {
    require_admin();
    csrf_verify();
    $did = (int)$p['did'];
    $active = db_fetch(
        "SELECT c.id FROM competition c
         JOIN competition_double cd ON cd.competition_id = c.id
         WHERE cd.double_id = ? AND c.phase != 'setup'",
        [$did]
    );
    if ($active) {
        flash('danger', 'Doppel kann nicht gelöscht werden: Bewerb läuft bereits.');
        redirect('players#tab-doppel');
        return;
    }
    $d = db_fetch("SELECT name FROM `double` WHERE id=?", [$did]);
    if (!$d) { redirect('players#tab-doppel'); return; }

    db_execute("DELETE FROM `double` WHERE id=?", [$did]);
    flash('info', 'Doppel „' . $d['name'] . '" gelöscht.');
    redirect('players#tab-doppel');
}

function create_team_global(array $p): void {
    require_edit();
    csrf_verify();
    $name = trim(post('name'));
    if (!$name) {
        flash('danger', 'Name erforderlich.');
        redirect('players#tab-teams');
        return;
    }
    $skill = (float)post('skill', 0);
    $tid = (int)db_insert("INSERT INTO `team` (name, skill) VALUES (?,?)", [$name, $skill]);
    foreach ((array)($_POST['player_ids'] ?? []) as $pid_str) {
        $pid = (int)$pid_str;
        if ($pid) db_execute("INSERT IGNORE INTO `team_player` (team_id, player_id) VALUES (?,?)", [$tid, $pid]);
    }
    flash('success', 'Team „' . $name . '" erstellt.');
    redirect('players#tab-teams');
}

function edit_team_global(array $p): void {
    require_edit();
    csrf_verify();
    $tid  = (int)$p['tid'];
    $name = trim(post('name'));
    if (!$name) {
        flash('danger', 'Name darf nicht leer sein.');
        redirect('players#tab-teams');
        return;
    }
    $skill = (float)post('skill', 0);
    db_execute("UPDATE `team` SET name=?, skill=? WHERE id=?", [$name, $skill, $tid]);
    flash('success', 'Team gespeichert.');
    redirect('players#tab-teams');
}

function delete_team_global(array $p): void {
    require_admin();
    csrf_verify();
    $tid    = (int)$p['tid'];
    $active = db_fetch(
        "SELECT c.id FROM competition c
         JOIN competition_team ct ON ct.competition_id = c.id
         WHERE ct.team_id = ? AND c.phase != 'setup'",
        [$tid]
    );
    if ($active) {
        flash('danger', 'Team kann nicht gelöscht werden: Bewerb läuft bereits.');
        redirect('players#tab-teams');
        return;
    }
    $t = db_fetch("SELECT name FROM `team` WHERE id=?", [$tid]);
    if (!$t) { redirect('players#tab-teams'); return; }
    db_execute("DELETE FROM `team` WHERE id=?", [$tid]);
    flash('info', 'Team „' . $t['name'] . '" gelöscht.');
    redirect('players#tab-teams');
}

function add_team_player(array $p): void {
    require_edit();
    csrf_verify();
    $tid = (int)$p['tid'];
    $pid = (int)post('player_id');
    if ($pid) {
        db_execute("INSERT IGNORE INTO `team_player` (team_id, player_id) VALUES (?,?)", [$tid, $pid]);
    }
    redirect('players#tab-teams');
}

function remove_team_player(array $p): void {
    require_edit();
    csrf_verify();
    $tid = (int)$p['tid'];
    $pid = (int)$p['pid'];
    $duel_used = db_fetch(
        "SELECT d.id FROM team_match_duel d
         JOIN `match` m ON m.id = d.match_id
         JOIN competition_team ct ON ct.competition_id = m.competition_id AND ct.team_id = ?
         WHERE (d.player1_id = ? OR d.player2_id = ?)
         LIMIT 1",
        [$tid, $pid, $pid]
    );
    if ($duel_used) {
        flash('danger', 'Spieler kann nicht entfernt werden: Er ist in einem gespeicherten Spielergebnis eingetragen.');
        redirect('players#tab-teams');
        return;
    }
    db_execute("DELETE FROM `team_player` WHERE team_id=? AND player_id=?", [$tid, $pid]);
    redirect('players#tab-teams');
}

function new_player(array $p): void {
    require_edit();
    csrf_verify();
    $name      = trim(post('name'));
    $firstname = trim(post('firstname'));
    $club      = trim(post('club'));
    $gender    = trim(post('gender'));
    $pass_nr   = trim(post('pass_nr'));
    $email     = trim(post('email'));

    if (!$name || !$firstname) {
        flash('danger', 'Nachname und Vorname sind Pflichtfelder.');
        redirect('players#tab-spieler');
        return;
    }
    $ratingscentral_id = trim(post('ratingscentral_id', '')) ?: null;
    $oetv_nr           = trim(post('oetv_nr', '')) ?: null;
    $pid = db_insert(
        "INSERT INTO player (name, firstname, club, gender, pass_nr, email, ratingscentral_id, oetv_nr) VALUES (?,?,?,?,?,?,?,?)",
        [$name, $firstname, $club, $gender, $pass_nr, $email, $ratingscentral_id, $oetv_nr]
    );
    _save_player_skills((int)$pid);
    redirect('players#tab-spieler');
}

function edit(array $p): void {
    require_edit();
    csrf_verify();
    $pid       = (int)$p['id'];
    $name      = trim(post('name'));
    $firstname = trim(post('firstname'));
    $club      = trim(post('club'));
    $gender    = trim(post('gender'));
    $pass_nr   = trim(post('pass_nr'));
    $email     = trim(post('email'));

    $ratingscentral_id = trim(post('ratingscentral_id', '')) ?: null;
    $oetv_nr           = trim(post('oetv_nr', '')) ?: null;

    if (!$name || !$firstname) {
        flash('danger', 'Nachname und Vorname sind Pflichtfelder.');
        redirect('players#tab-spieler');
        return;
    }
    db_execute(
        "UPDATE player SET name=?, firstname=?, club=?, gender=?, pass_nr=?, email=?, ratingscentral_id=?, oetv_nr=? WHERE id=?",
        [$name, $firstname, $club, $gender, $pass_nr, $email, $ratingscentral_id, $oetv_nr, $pid]
    );
    foreach (SPORTS_LIST as [$sport_key]) {
        $raw = post("skill_$sport_key", '');
        $s   = (float)str_replace(',', '.', $raw);
        if ($s > 0) {
            db_execute(
                "INSERT INTO player_skill (player_id, sport, skill, updated_at)
                 VALUES (?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE skill=VALUES(skill), updated_at=NOW()",
                [$pid, $sport_key, $s]
            );
        } elseif (trim($raw) === '0' || trim($raw) === '0.0' || trim($raw) === '0,0') {
            db_execute("DELETE FROM player_skill WHERE player_id=? AND sport=?", [$pid, $sport_key]);
        }
    }
    redirect('players#tab-spieler');
}

function delete(array $p): void {
    require_admin();
    csrf_verify();
    $pid = (int)$p['id'];

    $active_comp = db_fetch(
        "SELECT c.name FROM competition c
         JOIN competition_player cp ON cp.competition_id=c.id
         WHERE cp.player_id=? AND c.phase != 'setup'
         LIMIT 1",
        [$pid]
    );
    if ($active_comp) {
        flash('danger', 'Spieler kann nicht gelöscht werden: läuft in Bewerb „' . $active_comp['name'] . '".');
        redirect('players#tab-spieler');
        return;
    }

    $in_double = db_fetch(
        "SELECT d.name FROM `double` d WHERE d.player1_id=? OR d.player2_id=? LIMIT 1",
        [$pid, $pid]
    );
    if ($in_double) {
        flash('danger', 'Spieler kann nicht gelöscht werden: ist Teil von Doppel „' . $in_double['name'] . '".');
        redirect('players#tab-spieler');
        return;
    }

    db_execute("DELETE FROM player WHERE id=?", [$pid]);
    redirect('players#tab-spieler');
}

function toggle_active_player(array $p): void {
    require_edit();
    csrf_verify();
    $pid    = (int)$p['id'];
    $player = db_fetch("SELECT is_active FROM player WHERE id=?", [$pid]);
    if (!$player) { redirect('players#tab-spieler'); return; }
    db_execute("UPDATE player SET is_active=? WHERE id=?", [$player['is_active'] ? 0 : 1, $pid]);
    redirect('players#tab-spieler');
}

function toggle_active_double(array $p): void {
    require_edit();
    csrf_verify();
    $did    = (int)$p['did'];
    $double = db_fetch("SELECT is_active FROM `double` WHERE id=?", [$did]);
    if (!$double) { redirect('players#tab-doppel'); return; }
    db_execute("UPDATE `double` SET is_active=? WHERE id=?", [$double['is_active'] ? 0 : 1, $did]);
    redirect('players#tab-doppel');
}

function toggle_active_team(array $p): void {
    require_edit();
    csrf_verify();
    $tid  = (int)$p['tid'];
    $team = db_fetch("SELECT is_active FROM `team` WHERE id=?", [$tid]);
    if (!$team) { redirect('players#tab-teams'); return; }
    db_execute("UPDATE `team` SET is_active=? WHERE id=?", [$team['is_active'] ? 0 : 1, $tid]);
    redirect('players#tab-teams');
}

function player_profile_json(array $p): void {
    require_edit();
    $pid = (int)$p['id'];
    $player = db_fetch("SELECT * FROM player WHERE id=?", [$pid]);
    if (!$player) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo '{"error":"not found"}';
        exit;
    }
    $skills_rows = db_fetchall("SELECT sport, skill FROM player_skill WHERE player_id=?", [$pid]);
    $skills = array_column($skills_rows, 'skill', 'sport');

    $comps = db_fetchall(
        "SELECT t.id as tid, t.name as tname, c.id as cid, c.name as cname, c.phase
         FROM competition_player cp
         JOIN competition c ON c.id=cp.competition_id
         JOIN tournament t ON t.id=c.tournament_id
         WHERE cp.player_id=?
         ORDER BY t.name, c.name",
        [$pid]
    );

    $doubles = db_fetchall(
        "SELECT d.id, d.name,
         TRIM(CONCAT(COALESCE(pp.firstname,''), IF(COALESCE(pp.firstname,'')!='', ' ',''), pp.name)) as partner_name
         FROM `double` d
         JOIN player pp ON pp.id = IF(d.player1_id=?, d.player2_id, d.player1_id)
         WHERE d.player1_id=? OR d.player2_id=?
         ORDER BY d.name",
        [$pid, $pid, $pid]
    );
    foreach ($doubles as &$dbl) {
        $dbl['competitions'] = db_fetchall(
            "SELECT t.id as tid, t.name as tname, c.id as cid, c.name as cname, c.phase
             FROM competition_double cd
             JOIN competition c ON c.id=cd.competition_id
             JOIN tournament t ON t.id=c.tournament_id
             WHERE cd.double_id=?
             ORDER BY t.name, c.name",
            [$dbl['id']]
        );
    }
    unset($dbl);

    $teams_raw = db_fetchall(
        "SELECT tm.id, tm.name FROM `team` tm
         JOIN team_player tp ON tp.team_id = tm.id
         WHERE tp.player_id = ? ORDER BY tm.name",
        [$pid]
    );
    $teams = [];
    foreach ($teams_raw as $tm) {
        $tm['competitions'] = db_fetchall(
            "SELECT t.id as tid, t.name as tname, c.id as cid, c.name as cname, c.phase
             FROM competition_team ct
             JOIN competition c ON c.id = ct.competition_id
             JOIN tournament t ON t.id = c.tournament_id
             WHERE ct.team_id = ? ORDER BY t.name, c.name",
            [$tm['id']]
        );
        $teams[] = $tm;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['player' => $player, 'skills' => $skills, 'comps' => $comps, 'doubles' => $doubles, 'teams' => $teams]);
    exit;
}

function import_template(array $p): void {
    require_edit();
    $headers = [
        'Nachname', 'Vorname', 'Geschlecht', 'Verein', 'Pass-Nr.', 'E-Mail',
        'Spielstärke Tischtennis', 'Spielstärke Tennis', 'Spielstärke Fußball', 'Spielstärke Cornhole',
        'RatingsCentral-ID', 'ÖTV-ID',
    ];
    $example = ['Mustermann', 'Max', 'm', 'Muster SC', 'TT123', 'max@example.com', '1000', '4.5', '0', '0', '', ''];
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="spieler_vorlage.xlsx"');
    header('Cache-Control: max-age=0');
    echo _xlsx_build_template($headers, $example, 'Spieler');
    exit;
}

function import_players(array $p): void {
    require_edit();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render('player/import', ['page_title' => 'Spieler importieren']);
        return;
    }
    csrf_verify();

    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        flash('danger', 'Keine gültige Datei hochgeladen.');
        redirect('players/import');
        return;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'xlsx') {
        $rows = _xlsx_parse($file['tmp_name']);
    } elseif ($ext === 'csv') {
        $rows = _csv_parse($file['tmp_name']);
    } else {
        flash('danger', 'Nur .xlsx oder .csv Dateien erlaubt.');
        redirect('players/import');
        return;
    }

    if (count($rows) < 2) {
        flash('warning', 'Keine Daten gefunden (nur Kopfzeile oder leer).');
        redirect('players/import');
        return;
    }

    array_shift($rows); // Kopfzeile entfernen

    $imported = 0; $skipped = 0; $errors = [];

    foreach ($rows as $i => $row) {
        $row              = array_pad(array_values($row), 11, '');
        $name             = trim($row[0]);
        $firstname        = trim($row[1]);
        $gender           = trim($row[2]);
        $club             = trim($row[3]);
        $pass_nr          = trim($row[4]);
        $email            = trim($row[5]);
        $skills           = [
            'tischtennis' => (float)str_replace(',', '.', $row[6]),
            'tennis'      => (float)str_replace(',', '.', $row[7]),
            'fussball'    => (float)str_replace(',', '.', $row[8]),
            'cornhole'    => (float)str_replace(',', '.', $row[9]),
        ];
        $ratingscentral_id = trim($row[10] ?? '') ?: null;
        $oetv_nr           = trim($row[11] ?? '') ?: null;

        if (!$name || !$firstname) {
            $errors[] = 'Zeile ' . ($i + 2) . ': Nachname oder Vorname fehlt — übersprungen.';
            continue;
        }

        // Duplikatsprüfung: Pass-Nr. ODER Nachname+Vorname
        $dup = false;
        if ($pass_nr) {
            $dup = (bool)db_fetch("SELECT id FROM player WHERE pass_nr=?", [$pass_nr]);
        }
        if (!$dup) {
            $dup = (bool)db_fetch("SELECT id FROM player WHERE name=? AND firstname=?", [$name, $firstname]);
        }
        if ($dup) { $skipped++; continue; }

        $pid = db_insert(
            "INSERT INTO player (name, firstname, club, gender, pass_nr, email, ratingscentral_id, oetv_nr) VALUES (?,?,?,?,?,?,?,?)",
            [$name, $firstname, $club ?: null, $gender ?: null, $pass_nr ?: null, $email ?: null, $ratingscentral_id, $oetv_nr]
        );
        foreach ($skills as $sport => $sk) {
            if ($sk > 0) {
                db_execute(
                    "INSERT INTO player_skill (player_id, sport, skill, updated_at) VALUES (?,?,?,NOW())",
                    [(int)$pid, $sport, $sk]
                );
            }
        }
        $imported++;
    }

    render('player/import', [
        'page_title' => 'Spieler importieren',
        'imported'   => $imported,
        'skipped'    => $skipped,
        'errors'     => $errors,
        'done'       => true,
    ]);
}

// ── Doppel-Import ─────────────────────────────────────────────────────────────

function import_doubles_template(array $p): void {
    require_edit();
    $headers = [
        'Spieler 1 Nachname', 'Spieler 1 Vorname', 'Spieler 1 Pass-Nr.',
        'Spieler 2 Nachname', 'Spieler 2 Vorname', 'Spieler 2 Pass-Nr.',
    ];
    $example = ['Mustermann', 'Max', 'TT123', 'Müller', 'Anna', ''];
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="doppel_vorlage.xlsx"');
    header('Cache-Control: max-age=0');
    echo _xlsx_build_template($headers, $example, 'Doppel');
    exit;
}

function import_doubles(array $p): void {
    require_edit();
    $info = 'Mitglieder werden über die <strong>Pass-Nr.</strong> (sonst <strong>Nachname + Vorname</strong>) '
          . 'einem Registerspieler zugeordnet. Unbekannte Spieler werden automatisch im Register angelegt. '
          . 'Ein Doppel wird übersprungen, wenn die Paarung bereits existiert.';
    $base = [
        'page_title'   => 'Doppel importieren',
        'template_url' => 'players/doubles/import/template',
        'import_url'   => 'players/doubles/import',
        'info_html'    => $info,
    ];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { render('player/import', $base); return; }
    csrf_verify();

    $rows = _import_rows_from_upload($_FILES['file'] ?? null, 'players/doubles/import');
    if ($rows === null) return;

    $imported = 0; $skipped = 0; $created = 0; $errors = [];
    foreach ($rows as $i => $row) {
        $row  = array_pad(array_values($row), 6, '');
        $line = $i + 2;
        $m1 = _resolve_or_create_player($row[0], $row[1], $row[2]);
        $m2 = _resolve_or_create_player($row[3], $row[4], $row[5]);
        if (isset($m1['error'])) { $errors[] = "Zeile $line: Spieler 1 " . _member_err($m1['error']); continue; }
        if (isset($m2['error'])) { $errors[] = "Zeile $line: Spieler 2 " . _member_err($m2['error']); continue; }
        if ($m1['created']) $created++;
        if ($m2['created']) $created++;
        if ($m1['id'] === $m2['id']) { $errors[] = "Zeile $line: identische Spieler — übersprungen."; continue; }

        $exist = db_fetch(
            "SELECT id FROM `double` WHERE (player1_id=? AND player2_id=?) OR (player1_id=? AND player2_id=?)",
            [$m1['id'], $m2['id'], $m2['id'], $m1['id']]
        );
        if ($exist) { $skipped++; continue; }

        $pl1 = db_fetch("SELECT name, firstname, skill FROM player WHERE id=?", [$m1['id']]);
        $pl2 = db_fetch("SELECT name, firstname, skill FROM player WHERE id=?", [$m2['id']]);
        $n1   = trim(($pl1['firstname'] ? $pl1['firstname'] . ' ' : '') . $pl1['name']);
        $n2   = trim(($pl2['firstname'] ? $pl2['firstname'] . ' ' : '') . $pl2['name']);
        $name = $n1 . ' / ' . $n2;
        $skill = round((float)$pl1['skill'] + (float)$pl2['skill'], 1);
        db_insert("INSERT INTO `double` (player1_id, player2_id, name, skill) VALUES (?,?,?,?)",
            [$m1['id'], $m2['id'], $name, $skill]);
        $imported++;
    }

    render('player/import', $base + [
        'imported' => $imported, 'skipped' => $skipped, 'created' => $created,
        'errors' => $errors, 'done' => true,
    ]);
}

// ── Team-Import ───────────────────────────────────────────────────────────────

function import_teams_template(array $p): void {
    require_edit();
    $headers = ['Teamname', 'Nachname', 'Vorname', 'Pass-Nr.'];
    $example = ['Team Alpha', 'Mustermann', 'Max', 'TT123'];
    $extra   = [
        ['Team Alpha', 'Müller', 'Anna', ''],   // weiteres Mitglied desselben Teams
        ['Team Beta', '', '', ''],              // Team ohne Spieler (nur Teamname)
    ];
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="team_vorlage.xlsx"');
    header('Cache-Control: max-age=0');
    echo _xlsx_build_template($headers, $example, 'Teams', $extra);
    exit;
}

function import_teams(array $p): void {
    require_edit();
    $info = 'Langformat: <strong>eine Zeile je Mitglied</strong>, gruppiert nach <strong>Teamname</strong> '
          . '(beliebige Teamgröße). Mitglieder werden über die <strong>Pass-Nr.</strong> (sonst '
          . '<strong>Nachname + Vorname</strong>) zugeordnet; unbekannte Spieler werden automatisch angelegt. '
          . 'Ein <strong>Team ohne Mitglieder</strong> ist möglich — dann nur die Spalte <strong>Teamname</strong> '
          . 'ausfüllen. Ein Team wird übersprungen, wenn bereits ein Team mit gleichem Namen existiert.';
    $base = [
        'page_title'   => 'Teams importieren',
        'template_url' => 'players/teams/import/template',
        'import_url'   => 'players/teams/import',
        'info_html'    => $info,
    ];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { render('player/import', $base); return; }
    csrf_verify();

    $rows = _import_rows_from_upload($_FILES['file'] ?? null, 'players/teams/import');
    if ($rows === null) return;

    // Zeilen nach Teamname gruppieren (Reihenfolge erhalten)
    $teams = []; $order = [];
    foreach ($rows as $i => $row) {
        $row = array_pad(array_values($row), 4, '');
        $tn  = trim($row[0]);
        if ($tn === '') continue;
        $key = mb_strtolower($tn);
        if (!isset($teams[$key])) { $teams[$key] = ['name' => $tn, 'members' => []]; $order[] = $key; }
        // Zeile nur mit Teamname (keine Mitgliedsdaten) → Team ohne Spieler, keine Mitgliedszeile
        if (trim($row[1]) === '' && trim($row[2]) === '' && trim($row[3]) === '') continue;
        $teams[$key]['members'][] = ['name' => $row[1], 'firstname' => $row[2], 'pass' => $row[3], 'line' => $i + 2];
    }

    $imported = 0; $skipped = 0; $created = 0; $errors = [];
    foreach ($order as $key) {
        $g = $teams[$key];
        if (db_fetch("SELECT id FROM `team` WHERE name=?", [$g['name']])) { $skipped++; continue; }

        $ids = []; $teamSkill = 0.0;
        foreach ($g['members'] as $mem) {
            $r = _resolve_or_create_player($mem['name'], $mem['firstname'], $mem['pass']);
            if (isset($r['error'])) {
                $errors[] = 'Team ' . $g['name'] . ' (Zeile ' . $mem['line'] . '): Mitglied ' . _member_err($r['error']);
                continue;
            }
            if ($r['created']) $created++;
            $ids[$r['id']] = true;
        }
        // Teams ohne (gültige) Mitglieder werden trotzdem angelegt.

        foreach (array_keys($ids) as $pid) {
            $sk = db_fetch("SELECT skill FROM player WHERE id=?", [$pid]);
            $teamSkill += (float)($sk['skill'] ?? 0);
        }
        $tid = (int)db_insert("INSERT INTO `team` (name, skill) VALUES (?,?)", [$g['name'], round($teamSkill, 1)]);
        foreach (array_keys($ids) as $pid) {
            db_execute("INSERT IGNORE INTO `team_player` (team_id, player_id) VALUES (?,?)", [$tid, $pid]);
        }
        $imported++;
    }

    render('player/import', $base + [
        'imported' => $imported, 'skipped' => $skipped, 'created' => $created,
        'errors' => $errors, 'done' => true,
    ]);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

// Liest die hochgeladene Datei (.xlsx/.csv), entfernt die Kopfzeile und liefert die
// Datenzeilen — oder null (mit Flash + Redirect) bei Fehler/leerer Datei.
function _import_rows_from_upload(?array $file, string $redirect): ?array {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        flash('danger', 'Keine gültige Datei hochgeladen.');
        redirect($redirect); return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'xlsx') {
        $rows = _xlsx_parse($file['tmp_name']);
    } elseif ($ext === 'csv') {
        $rows = _csv_parse($file['tmp_name']);
    } else {
        flash('danger', 'Nur .xlsx oder .csv Dateien erlaubt.');
        redirect($redirect); return null;
    }
    if (count($rows) < 2) {
        flash('warning', 'Keine Daten gefunden (nur Kopfzeile oder leer).');
        redirect($redirect); return null;
    }
    array_shift($rows); // Kopfzeile entfernen
    return array_values($rows);
}

// Ordnet ein Importmitglied einem Registerspieler zu (Pass-Nr. vor Name) oder legt es neu an.
// Rückgabe: ['id'=>int,'created'=>bool] oder ['error'=>'mehrdeutig'|'leer'].
function _resolve_or_create_player(string $name, string $firstname, string $passnr): array {
    $name = trim($name); $firstname = trim($firstname); $passnr = trim($passnr);
    if ($name === '' && $passnr === '') return ['error' => 'leer'];

    if ($passnr !== '') {
        $hit = db_fetchall("SELECT id FROM player WHERE pass_nr=?", [$passnr]);
        if (count($hit) === 1) return ['id' => (int)$hit[0]['id'], 'created' => false];
        if (count($hit) > 1)   return ['error' => 'mehrdeutig'];
    }
    if ($name === '') return ['error' => 'leer']; // nur Pass-Nr., aber nicht gefunden → kein Anlegen möglich

    $hit = db_fetchall("SELECT id FROM player WHERE name=? AND firstname=?", [$name, $firstname]);
    if (count($hit) === 1) return ['id' => (int)$hit[0]['id'], 'created' => false];
    if (count($hit) > 1)   return ['error' => 'mehrdeutig'];

    $pid = (int)db_insert(
        "INSERT INTO player (name, firstname, pass_nr) VALUES (?,?,?)",
        [$name, $firstname, $passnr ?: null]
    );
    return ['id' => $pid, 'created' => true];
}

function _member_err(string $code): string {
    return $code === 'mehrdeutig'
        ? 'mehrdeutig (mehrere Treffer im Register) — übersprungen.'
        : 'unvollständig (Nachname fehlt) — übersprungen.';
}

function _save_player_skills(int $pid): void {
    foreach (SPORTS_LIST as [$sport_key]) {
        $s = (float)str_replace(',', '.', post("skill_$sport_key", '0'));
        if ($s > 0) {
            db_execute(
                "INSERT INTO player_skill (player_id, sport, skill, updated_at)
                 VALUES (?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE skill=VALUES(skill), updated_at=NOW()",
                [$pid, $sport_key, $s]
            );
        }
    }
}

// ── XLSX/CSV Import-Hilfsfunktionen ──────────────────────────────────────────

function _xlsx_col_letter(int $idx): string {
    $s = ''; $idx++;
    while ($idx > 0) { $m = ($idx - 1) % 26; $s = chr(65 + $m) . $s; $idx = intdiv($idx - 1, 26); }
    return $s;
}

function _xlsx_build_template(array $headers, array $example, string $sheetName = 'Tabelle1', array $extraRows = []): string {
    $dataRows = array_merge([$example], $extraRows);

    // Shared strings: headers + alle nicht-numerischen Zellenwerte aller Datenzeilen
    $strVals = $headers;
    foreach ($dataRows as $dr) {
        foreach ($dr as $v) { if (!is_numeric($v) || $v === '') $strVals[] = $v; }
    }
    $strings = array_values(array_unique($strVals));
    $strIdx  = array_flip($strings);

    $cols = count($headers);
    foreach ($dataRows as $dr) $cols = max($cols, count($dr));
    $colLetters = [];
    for ($i = 0; $i < $cols; $i++) $colLetters[] = _xlsx_col_letter($i);

    // Row 1: bold headers (style s="1")
    $row1 = '';
    foreach ($headers as $ci => $h) {
        $si   = $strIdx[$h];
        $row1 .= "<c r=\"{$colLetters[$ci]}1\" t=\"s\" s=\"1\"><v>$si</v></c>";
    }

    // Datenzeilen ab Zeile 2
    $rowsXml = '';
    foreach ($dataRows as $d => $dr) {
        $rn = $d + 2;
        $cells = '';
        foreach ($dr as $ci => $v) {
            $col = $colLetters[$ci];
            if (is_numeric($v) && $v !== '') {
                $cells .= "<c r=\"{$col}{$rn}\"><v>$v</v></c>";
            } else {
                $si = $strIdx[$v] ?? null;
                if ($si === null) { $strings[] = $v; $si = count($strings) - 1; $strIdx[$v] = $si; }
                $cells .= "<c r=\"{$col}{$rn}\" t=\"s\"><v>$si</v></c>";
            }
        }
        $rowsXml .= "<row r=\"$rn\">$cells</row>";
    }

    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
    foreach ($strings as $s) {
        $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES) . '</t></si>';
    }
    $ssXml .= '</sst>';

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>'
        . "<row r=\"1\">$row1</row>"
        . $rowsXml
        . '</sheetData></worksheet>';

    $wbXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '</cellXfs></styleSheet>';

    $ctXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $relsWb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml',          $ctXml);
    $zip->addFromString('_rels/.rels',                  $relsRoot);
    $zip->addFromString('xl/workbook.xml',              $wbXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels',   $relsWb);
    $zip->addFromString('xl/worksheets/sheet1.xml',     $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml',         $ssXml);
    $zip->addFromString('xl/styles.xml',                $stylesXml);
    $zip->close();

    $data = file_get_contents($tmp);
    unlink($tmp);
    return $data;
}

function _xlsx_parse(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];

    // Shared strings — strip default namespace so xpath works without prefix
    $ss = [];
    $ssRaw = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssRaw) {
        $ssRaw = _xml_strip_ns($ssRaw);
        libxml_use_internal_errors(true);
        $ssXml = simplexml_load_string($ssRaw);
        if ($ssXml) {
            foreach ($ssXml->xpath('//si') as $si) {
                $t = '';
                foreach ($si->xpath('.//t') as $tEl) { $t .= (string)$tEl; }
                $ss[] = $t;
            }
        }
    }

    $sheetRaw = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetRaw) return [];

    $sheetRaw = _xml_strip_ns($sheetRaw);
    libxml_use_internal_errors(true);
    $sheet = simplexml_load_string($sheetRaw);
    if (!$sheet) return [];

    $rows = [];
    foreach ($sheet->xpath('//row') as $rowEl) {
        $rowData = []; $prevCol = -1;
        foreach ($rowEl->xpath('c') as $cell) {
            preg_match('/^([A-Z]+)/', (string)$cell['r'], $m);
            $col = 0;
            foreach (str_split($m[1]) as $ch) { $col = $col * 26 + (ord($ch) - ord('A') + 1); }
            $col--;
            while ($prevCol < $col - 1) { $rowData[] = ''; $prevCol++; }
            $t = (string)($cell['t'] ?? '');
            $v = (string)($cell->v ?? '');
            if ($t === 's') {
                $rowData[] = $ss[(int)$v] ?? '';
            } elseif ($t === 'inlineStr') {
                $rowData[] = (string)($cell->is->t ?? '');
            } else {
                $rowData[] = $v;
            }
            $prevCol = $col;
        }
        if (array_filter($rowData, fn($v) => trim($v) !== '')) {
            $rows[] = $rowData;
        }
    }
    return $rows;
}

function _xml_strip_ns(string $xml): string {
    // Remove default namespace declarations so SimpleXML xpath works without prefix
    return preg_replace('/\s+xmlns(?::[a-z0-9]+)?="[^"]*"/i', '', $xml) ?? $xml;
}

function sync_external_skill(array $p): void {
    require_edit();
    header('Content-Type: application/json; charset=utf-8');
    $pid    = (int)$p['id'];
    $source = $p['source'] ?? '';
    $player = db_fetch("SELECT * FROM player WHERE id=?", [$pid]);
    if (!$player) { echo json_encode(['error' => 'Spieler nicht gefunden']); exit; }

    if ($source === 'ratingscentral') {
        $ext_id = trim($player['ratingscentral_id'] ?? '');
        if (!$ext_id) { echo json_encode(['error' => 'Keine RatingsCentral-ID hinterlegt']); exit; }
        $result = _fetch_ratingscentral_rating($ext_id);
        if (!empty($result['error'])) { echo json_encode($result); exit; }
        db_execute(
            "INSERT INTO player_skill (player_id, sport, skill, updated_at) VALUES (?,?,?,NOW())
             ON DUPLICATE KEY UPDATE skill=VALUES(skill), updated_at=NOW()",
            [$pid, 'tischtennis', $result['rating']]
        );
        echo json_encode(['success' => true, 'skill' => $result['rating']]);
        exit;
    }

    if ($source === 'oetv') {
        $ext_id = trim($player['oetv_nr'] ?? '');
        if (!$ext_id) { echo json_encode(['error' => 'Keine ÖTV-ID hinterlegt']); exit; }
        $result = _fetch_oetv_rating($ext_id);
        if (!empty($result['error'])) { echo json_encode($result); exit; }
        db_execute(
            "INSERT INTO player_skill (player_id, sport, skill, updated_at) VALUES (?,?,?,NOW())
             ON DUPLICATE KEY UPDATE skill=VALUES(skill), updated_at=NOW()",
            [$pid, 'tennis', $result['rating']]
        );
        echo json_encode(['success' => true, 'skill' => $result['rating']]);
        exit;
    }

    echo json_encode(['error' => 'Unbekannte Quelle']);
    exit;
}


function _fetch_oetv_rating(string $oetv_nr): array {
    // Vorbereitet für späteren automatischen Abgleich über die ÖTV-Website/-API.
    return ['error' => 'Automatischer Abgleich noch nicht verfügbar'];
}

function _fetch_ratingscentral_rating(string $id): array {
    $url  = 'https://www.ratingscentral.com/Player.php?PlayerID=' . urlencode($id);
    $ua   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    $html = false;

    // cURL bevorzugen (dogado hat allow_url_fopen deaktiviert)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: de,en;q=0.9'],
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res && !$err) $html = $res;
    }

    // Fallback: file_get_contents (lokal, allow_url_fopen aktiv)
    if (!$html && ini_get('allow_url_fopen')) {
        $ctx  = stream_context_create(['http' => [
            'user_agent'    => $ua,
            'timeout'       => 15,
            'ignore_errors' => true,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res) $html = $res;
    }

    if (!$html) return ['error' => 'Verbindungsfehler — bitte ID prüfen'];

    // Erste Zahl nach <span class="Subheader"> — unabhängig von ±-Kodierung
    if (preg_match('/<span[^>]*class="Subheader"[^>]*>\s*(\d+)/u', $html, $m)) {
        return ['rating' => (float)$m[1]];
    }
    return ['error' => 'Rating nicht gefunden — bitte ID prüfen'];
}

function _csv_parse(string $path): array {
    $raw = file_get_contents($path);
    // Strip UTF-8 BOM
    if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
    // Normalize line endings
    $raw = str_replace("\r\n", "\n", $raw);
    $raw = str_replace("\r", "\n", $raw);

    // Detect delimiter: try ; first, then ,
    $firstLine = strtok($raw, "\n");
    $delim = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

    $tmp = fopen('php://memory', 'r+');
    fwrite($tmp, $raw);
    rewind($tmp);

    $rows = [];
    while (($row = fgetcsv($tmp, 0, $delim)) !== false) {
        if (array_filter($row, fn($v) => trim($v) !== '')) $rows[] = $row;
    }
    fclose($tmp);
    return $rows;
}
