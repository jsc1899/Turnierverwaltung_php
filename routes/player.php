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
    render('player/index', [
        'page_title'    => 'Spielerregister',
        'players'       => $all_players,
        'player_comps'  => $player_comps,
        'player_skills' => $player_skills,
        'sports_list'   => SPORTS_LIST,
    ]);
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

    if (!$name || !$firstname || !$pass_nr || !$email) {
        flash('danger', 'Nachname, Vorname, Pass-Nr. und E-Mail sind Pflichtfelder.');
        redirect('players');
        return;
    }
    $pid = db_insert(
        "INSERT INTO player (name, firstname, club, gender, pass_nr, email) VALUES (?,?,?,?,?,?)",
        [$name, $firstname, $club, $gender, $pass_nr, $email]
    );
    _save_player_skills((int)$pid);
    redirect('players');
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

    if (!$name || !$firstname || !$pass_nr || !$email) {
        flash('danger', 'Nachname, Vorname, Pass-Nr. und E-Mail sind Pflichtfelder.');
        redirect('players');
        return;
    }
    db_execute(
        "UPDATE player SET name=?, firstname=?, club=?, gender=?, pass_nr=?, email=? WHERE id=?",
        [$name, $firstname, $club, $gender, $pass_nr, $email, $pid]
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
    redirect('players');
}

function delete(array $p): void {
    require_edit();
    csrf_verify();
    db_execute("DELETE FROM player WHERE id=?", [(int)$p['id']]);
    redirect('players');
}

// ── Helpers ───────────────────────────────────────────────────────────────────

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
