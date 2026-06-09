<?php
require_once __DIR__ . '/../lib/ko_bracket.php';

function _is_double_ko(int $cid): bool {
    $c = db_fetch("SELECT mode FROM competition WHERE id=?", [$cid]);
    return $c && $c['mode'] === 'double_ko';
}

function _propagate_result(int $cid, array $m): void {
    if ($m['group_id'] !== null) {
        _maybe_set_done($cid);
        return;
    }
    if (_is_double_ko($cid)) {
        require_once __DIR__ . '/../lib/double_ko_bracket.php';
        recompute_double_ko($cid);
        _maybe_set_done_dko($cid);
    } else {
        if ((int)$m['ko_round'] !== 3) {
            recompute_ko_from($cid, (int)$m['ko_round']);
        }
        _maybe_set_done($cid);
    }
}

function save(array $p): void {
    require_edit();
    csrf_verify();
    $mid    = (int)$p['id'];
    $score1 = isset($_POST['score1']) ? (int)$_POST['score1'] : null;
    $score2 = isset($_POST['score2']) ? (int)$_POST['score2'] : null;

    $m = db_fetch("SELECT * FROM `match` WHERE id=?", [$mid]);
    if (!$m) { redirect(''); return; }

    if ($score1 === null || $score2 === null || $score1 < 0 || $score2 < 0) {
        flash('danger', 'Ungültiges Ergebnis.');
        redirect('competition/' . $m['competition_id']);
        return;
    }
    if ($m['group_id'] === null && $score1 === $score2) {
        flash('warning', 'Unentschieden im KO-Bewerb nicht erlaubt.');
        redirect('competition/' . $m['competition_id']);
        return;
    }

    db_execute("UPDATE `match` SET score1=?, score2=?, played=1 WHERE id=?", [$score1, $score2, $mid]);
    _propagate_result((int)$m['competition_id'], $m);
    redirect('competition/' . $m['competition_id']);
}

function save_ko(array $p): void {
    save($p);
}

function save_bulk(array $p): void {
    require_edit();
    csrf_verify();
    $cid     = (int)$p['id'];
    $matches = $_POST['matches'] ?? [];
    $errors  = [];

    foreach ($matches as $mid => $scores) {
        $mid    = (int)$mid;
        $s1raw  = $scores['score1'] ?? '';
        $s2raw  = $scores['score2'] ?? '';
        if ($s1raw === '' && $s2raw === '') continue;
        $score1 = $s1raw !== '' ? (int)$s1raw : null;
        $score2 = $s2raw !== '' ? (int)$s2raw : null;
        if ($score1 === null || $score2 === null || $score1 < 0 || $score2 < 0) {
            $errors[] = "Ungültiges Ergebnis für Spiel #$mid.";
            continue;
        }
        $m = db_fetch("SELECT * FROM `match` WHERE id=?", [$mid]);
        if (!$m || (int)$m['competition_id'] !== $cid) continue;
        if ($m['group_id'] === null && $score1 === $score2) {
            $errors[] = "Unentschieden im KO-Bewerb nicht erlaubt.";
            continue;
        }
        db_execute("UPDATE `match` SET score1=?, score2=?, played=1 WHERE id=?", [$score1, $score2, $mid]);
        _propagate_result($cid, $m);
    }

    if ($errors) flash('warning', implode('<br>', $errors));
    redirect('competition/' . $cid);
}

function clear_result(array $p): void {
    require_edit();
    csrf_verify();
    $mid = (int)$p['id'];
    $m   = db_fetch("SELECT * FROM `match` WHERE id=?", [$mid]);
    if (!$m) { redirect(''); return; }
    db_execute("UPDATE `match` SET score1=NULL, score2=NULL, played=0, tiebreak_winner=0 WHERE id=?", [$mid]);
    $comp = db_fetch("SELECT team_size FROM competition WHERE id=?", [(int)$m['competition_id']]);
    if (!empty($comp['team_size'])) {
        db_execute("DELETE FROM team_match_duel WHERE match_id=?", [$mid]);
    }
    if ($m['group_id'] === null) {
        _propagate_result((int)$m['competition_id'], $m);
    }
    redirect('competition/' . $m['competition_id']);
}

function force_advance_ko(array $p): void {
    require_edit();
    csrf_verify();
    $mid  = (int)$p['id'];
    $slot = (int)$p['slot'];
    if ($slot !== 1 && $slot !== 2) { redirect(''); return; }
    $m = db_fetch("SELECT * FROM `match` WHERE id=?", [$mid]);
    if (!$m || !$m['played'] || (int)$m['score1'] !== (int)$m['score2']) {
        redirect('competition/' . ($m['competition_id'] ?? '')); return;
    }
    db_execute("UPDATE `match` SET tiebreak_winner=? WHERE id=?", [$slot, $mid]);
    require_once __DIR__ . '/../lib/ko_bracket.php';
    $m['tiebreak_winner'] = $slot;
    advance_ko_winner($m);
    redirect('competition/' . $m['competition_id']);
}

function save_duels(array $p): void {
    require_edit();
    csrf_verify();
    $mid = (int)$p['id'];
    $m   = db_fetch(
        "SELECT m.*, c.team_size, c.id as cid FROM `match` m
         JOIN competition c ON c.id=m.competition_id WHERE m.id=?",
        [$mid]
    );
    if (!$m || !(int)$m['team_size']) { redirect(''); return; }

    $duels      = $_POST['duels'] ?? [];
    $team_size  = (int)$m['team_size'];

    $p1ids = []; $p2ids = [];
    foreach ($duels as $d) {
        if (!empty($d['player1_id'])) {
            $id = (int)$d['player1_id'];
            if (in_array($id, $p1ids)) {
                flash('warning', 'Jeder Spieler darf nur einmal ausgewählt werden.');
                redirect('competition/' . $m['cid']); return;
            }
            $p1ids[] = $id;
        }
        if (!empty($d['player2_id'])) {
            $id = (int)$d['player2_id'];
            if (in_array($id, $p2ids)) {
                flash('warning', 'Jeder Spieler darf nur einmal ausgewählt werden.');
                redirect('competition/' . $m['cid']); return;
            }
            $p2ids[] = $id;
        }
    }

    db_execute("DELETE FROM team_match_duel WHERE match_id=?", [$mid]);

    $s1 = 0; $s2 = 0; $played_count = 0;
    foreach ($duels as $order => $d) {
        $p1id  = !empty($d['player1_id']) ? (int)$d['player1_id'] : null;
        $p2id  = !empty($d['player2_id']) ? (int)$d['player2_id'] : null;
        $ds1   = ($d['score1'] ?? '') !== '' ? (int)$d['score1'] : null;
        $ds2   = ($d['score2'] ?? '') !== '' ? (int)$d['score2'] : null;
        $dplayed = ($ds1 !== null && $ds2 !== null) ? 1 : 0;
        db_insert(
            "INSERT INTO team_match_duel
             (match_id, duel_order, player1_id, player2_id, score1, score2, played)
             VALUES (?,?,?,?,?,?,?)",
            [$mid, (int)$order, $p1id, $p2id, $ds1, $ds2, $dplayed]
        );
        if ($dplayed) {
            $played_count++;
            if ($ds1 > $ds2) $s1++;
            elseif ($ds2 > $ds1) $s2++;
        }
    }

    if ($played_count > 0) {
        $all_played = ($played_count >= $team_size) ? 1 : 0;
        db_execute(
            "UPDATE `match` SET score1=?, score2=?, played=? WHERE id=?",
            [$s1, $s2, $all_played, $mid]
        );
        if ($all_played) {
            _propagate_result((int)$m['competition_id'], $m);
        }
    }
    redirect('competition/' . $m['competition_id']);
}

// _maybe_set_done() is defined in lib/ko_bracket.php (required above)
