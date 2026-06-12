<?php
require_once __DIR__ . '/../lib/ko_bracket.php';

function _is_double_ko(int $cid): bool {
    $c = db_fetch("SELECT mode FROM competition WHERE id=?", [$cid]);
    return $c && $c['mode'] === 'double_ko';
}

function _group_phase_locked(int $cid): bool {
    $c = db_fetch("SELECT phase FROM competition WHERE id=?", [$cid]);
    return $c && in_array($c['phase'], ['ko', 'done'], true);
}

function _reset_group_tiebreak(int $group_id): void {
    db_execute("UPDATE group_player SET tiebreak_order=NULL WHERE group_id=?", [$group_id]);
    db_execute("UPDATE group_double SET tiebreak_order=NULL WHERE group_id=?", [$group_id]);
    db_execute("UPDATE group_team   SET tiebreak_order=NULL WHERE group_id=?", [$group_id]);
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

    if ($m['group_id'] !== null && _group_phase_locked((int)$m['competition_id'])) {
        flash('danger', 'Gruppenspielergebnisse können nach dem KO-Auslosen nicht mehr geändert werden.');
        redirect('competition/' . $m['competition_id']);
        return;
    }

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
    if ($m['group_id'] !== null) _reset_group_tiebreak((int)$m['group_id']);
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
        if ($m['group_id'] !== null && _group_phase_locked($cid)) {
            $errors[] = "Gruppenspiele können nach dem KO-Auslosen nicht mehr geändert werden.";
            continue;
        }
        if ($m['group_id'] === null && $score1 === $score2) {
            $errors[] = "Unentschieden im KO-Bewerb nicht erlaubt.";
            continue;
        }
        db_execute("UPDATE `match` SET score1=?, score2=?, played=1 WHERE id=?", [$score1, $score2, $mid]);
        if ($m['group_id'] !== null) _reset_group_tiebreak((int)$m['group_id']);
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

    if ($m['group_id'] !== null && _group_phase_locked((int)$m['competition_id'])) {
        flash('danger', 'Gruppenspielergebnisse können nach dem KO-Auslosen nicht mehr geändert werden.');
        redirect('competition/' . $m['competition_id']);
        return;
    }

    db_execute("UPDATE `match` SET score1=NULL, score2=NULL, played=0, tiebreak_winner=0 WHERE id=?", [$mid]);
    $comp = db_fetch("SELECT team_size, score_mode FROM competition WHERE id=?", [(int)$m['competition_id']]);
    if (!empty($comp['team_size'])) {
        db_execute("DELETE FROM team_match_duel WHERE match_id=?", [$mid]);
    }
    if (($comp['score_mode'] ?? 'match') === 'sets') {
        db_execute("DELETE FROM match_set WHERE match_id=?", [$mid]);
    }
    if ($m['group_id'] !== null) {
        _reset_group_tiebreak((int)$m['group_id']);
    } else {
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

    if ($m['group_id'] !== null && _group_phase_locked((int)$m['cid'])) {
        flash('danger', 'Gruppenspielergebnisse können nach dem KO-Auslosen nicht mehr geändert werden.');
        redirect('competition/' . $m['cid']);
        return;
    }

    $duels      = $_POST['duels'] ?? [];
    $team_size  = (int)$m['team_size'];

    db_execute("DELETE FROM team_match_duel WHERE match_id=?", [$mid]);

    $s1 = 0; $s2 = 0; $played_count = 0;
    foreach ($duels as $order => $d) {
        $p1raw = $d['player1_id'] ?? '';
        $p2raw = $d['player2_id'] ?? '';
        $label = null;
        if ($p1raw === '__doppel__' || $p2raw === '__doppel__') {
            $label = 'Doppel';
            $p1id  = null;
            $p2id  = null;
        } else {
            $p1id = !empty($p1raw) ? (int)$p1raw : null;
            $p2id = !empty($p2raw) ? (int)$p2raw : null;
        }
        $ds1   = ($d['score1'] ?? '') !== '' ? (int)$d['score1'] : null;
        $ds2   = ($d['score2'] ?? '') !== '' ? (int)$d['score2'] : null;
        $dplayed = ($ds1 !== null && $ds2 !== null) ? 1 : 0;
        db_insert(
            "INSERT INTO team_match_duel
             (match_id, duel_order, player1_id, player2_id, score1, score2, played, duel_label)
             VALUES (?,?,?,?,?,?,?,?)",
            [$mid, (int)$order, $p1id, $p2id, $ds1, $ds2, $dplayed, $label]
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
        if ($m['group_id'] !== null) _reset_group_tiebreak((int)$m['group_id']);
        if ($all_played) {
            _propagate_result((int)$m['competition_id'], $m);
        }
    }
    redirect('competition/' . $m['competition_id']);
}

function save_sets(array $p): void {
    require_edit();
    csrf_verify();
    $mid = (int)$p['id'];
    $m   = db_fetch(
        "SELECT m.*, c.score_mode, c.id as cid FROM `match` m
         JOIN competition c ON c.id = m.competition_id WHERE m.id = ?",
        [$mid]
    );
    $sm = $m['score_mode'] ?? 'match';
    $use_sets = $sm === 'sets' || ($sm === 'sets_grp' && $m['group_id'] !== null);
    if (!$m || !$use_sets) { redirect(''); return; }

    if ($m['group_id'] !== null && _group_phase_locked((int)$m['cid'])) {
        flash('danger', 'Gruppenspielergebnisse können nach dem KO-Auslosen nicht mehr geändert werden.');
        redirect('competition/' . $m['cid']);
        return;
    }

    $sets = $_POST['sets'] ?? [];
    db_execute("DELETE FROM match_set WHERE match_id = ?", [$mid]);

    $s1 = 0; $s2 = 0; $played_count = 0;
    foreach ($sets as $order => $s) {
        $ss1 = ($s['score1'] ?? '') !== '' ? (int)$s['score1'] : null;
        $ss2 = ($s['score2'] ?? '') !== '' ? (int)$s['score2'] : null;
        if ($ss1 === null || $ss2 === null) continue;
        db_insert(
            "INSERT INTO match_set (match_id, set_order, score1, score2) VALUES (?,?,?,?)",
            [$mid, (int)$order, $ss1, $ss2]
        );
        $played_count++;
        if ($ss1 > $ss2) $s1++;
        elseif ($ss2 > $ss1) $s2++;
    }

    if ($played_count > 0) {
        db_execute(
            "UPDATE `match` SET score1=?, score2=?, played=1 WHERE id=?",
            [$s1, $s2, $mid]
        );
        if ($m['group_id'] !== null) _reset_group_tiebreak((int)$m['group_id']);
        _propagate_result((int)$m['competition_id'], $m);
    } else {
        db_execute("UPDATE `match` SET score1=NULL, score2=NULL, played=0 WHERE id=?", [$mid]);
    }
    redirect('competition/' . $m['competition_id']);
}

// _maybe_set_done() is defined in lib/ko_bracket.php (required above)
