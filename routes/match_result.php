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
    $mode = db_fetch("SELECT mode FROM competition WHERE id=?", [$cid])['mode'] ?? '';
    if ($mode === 'double_ko') {
        require_once __DIR__ . '/../lib/double_ko_bracket.php';
        recompute_double_ko($cid);
        _maybe_set_done_dko($cid);
    } elseif ($mode === 'groups_cross') {
        require_once __DIR__ . '/../lib/placement_bracket.php';
        recompute_placement($cid);
        _maybe_set_done_placement($cid);
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
    require_competition_open((int)$m['competition_id']);

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
    require_competition_open($cid);
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
    require_competition_open((int)$m['competition_id']);

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
    require_competition_open((int)$m['competition_id']);
    db_execute("UPDATE `match` SET tiebreak_winner=? WHERE id=?", [$slot, $mid]);
    $m['tiebreak_winner'] = $slot;
    $mode = db_fetch("SELECT mode FROM competition WHERE id=?", [(int)$m['competition_id']])['mode'] ?? '';
    if ($mode === 'groups_cross') {
        require_once __DIR__ . '/../lib/placement_bracket.php';
        recompute_placement((int)$m['competition_id']);
        _maybe_set_done_placement((int)$m['competition_id']);
    } else {
        require_once __DIR__ . '/../lib/ko_bracket.php';
        advance_ko_winner($m);
    }
    redirect('competition/' . $m['competition_id']);
}

// Speichert die Einzelspiele (Duelle) EINER Team-Begegnung. Erwartet das Match inkl.
// team_size/team_result_mode/group_id/competition_id. Keine Auth/CSRF/Redirect/Lock-Prüfung
// (Aufrufer zuständig) → in Einzel- und Bulk-Speicherung wiederverwendbar.
function _apply_duels(array $m, array $duels, string $tot1_raw, string $tot2_raw): void {
    $mid         = (int)$m['id'];
    $team_size   = (int)$m['team_size'];
    $result_mode = ($m['team_result_mode'] ?? 'wins') === 'sum' ? 'sum' : 'wins';
    $has_total   = ($tot1_raw !== '' && $tot2_raw !== '');

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
            if ($result_mode === 'sum') {
                $s1 += $ds1;
                $s2 += $ds2;
            } else {
                if ($ds1 > $ds2) $s1++;
                elseif ($ds2 > $ds1) $s2++;
            }
        }
    }

    if ($has_total) {
        // Manuelles Gesamtergebnis: zählt unabhängig von den Einzel-Duellen.
        $s1 = (int)$tot1_raw;
        $s2 = (int)$tot2_raw;
        db_execute("UPDATE `match` SET score1=?, score2=?, played=1 WHERE id=?", [$s1, $s2, $mid]);
        if ($m['group_id'] !== null) _reset_group_tiebreak((int)$m['group_id']);
        _propagate_result((int)$m['competition_id'], $m);
    } elseif ($played_count > 0) {
        $all_played = ($played_count >= $team_size) ? 1 : 0;
        db_execute("UPDATE `match` SET score1=?, score2=?, played=? WHERE id=?", [$s1, $s2, $all_played, $mid]);
        if ($m['group_id'] !== null) _reset_group_tiebreak((int)$m['group_id']);
        if ($all_played) _propagate_result((int)$m['competition_id'], $m);
    }
}

function save_duels(array $p): void {
    require_edit();
    csrf_verify();
    $mid = (int)$p['id'];
    $m   = db_fetch(
        "SELECT m.*, c.team_size, c.team_result_mode, c.id as cid FROM `match` m
         JOIN competition c ON c.id=m.competition_id WHERE m.id=?",
        [$mid]
    );
    if (!$m || !(int)$m['team_size']) { redirect(''); return; }
    require_competition_open((int)$m['cid']);

    if ($m['group_id'] !== null && _group_phase_locked((int)$m['cid'])) {
        flash('danger', 'Gruppenspielergebnisse können nach dem KO-Auslosen nicht mehr geändert werden.');
        redirect('competition/' . $m['cid']);
        return;
    }

    _apply_duels($m, $_POST['duels'] ?? [], (string)($_POST['total_score1'] ?? ''), (string)($_POST['total_score2'] ?? ''));
    redirect('competition/' . $m['competition_id']);
}

// Bulk: speichert die Duelle MEHRERER Team-Begegnungen in EINEM Request (Test-Hilfe).
// Nutzlast als JSON-Body (NICHT als Formularfelder), da viele Begegnungen sonst die
// PHP-Grenze max_input_vars (Default 1000) überschreiten und stillschweigend verworfen würden.
// Format: { csrf_token, dm: { matchId: { duels: { i: {score1,score2,player1_id,player2_id} }, total_score1, total_score2 } } }
function save_duels_bulk(array $p): void {
    require_edit();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) { http_response_code(400); exit; }
    $_POST['csrf_token'] = (string)($data['csrf_token'] ?? '');  // csrf_verify liest aus $_POST
    csrf_verify();
    $cid = (int)$p['id'];
    require_competition_open($cid);
    $locked = null;   // _group_phase_locked nur einmal auswerten
    foreach ((array)($data['dm'] ?? []) as $mid => $payload) {
        if (!is_array($payload)) continue;
        $mid = (int)$mid;
        $m = db_fetch(
            "SELECT m.*, c.team_size, c.team_result_mode, c.id as cid FROM `match` m
             JOIN competition c ON c.id=m.competition_id WHERE m.id=?",
            [$mid]
        );
        if (!$m || (int)$m['competition_id'] !== $cid || !(int)$m['team_size']) continue;
        if ($m['group_id'] !== null) {
            if ($locked === null) $locked = _group_phase_locked($cid);
            if ($locked) continue;
        }
        _apply_duels(
            $m,
            (array)($payload['duels'] ?? []),
            (string)($payload['total_score1'] ?? ''),
            (string)($payload['total_score2'] ?? '')
        );
    }
    http_response_code(204);   // AJAX-Aufruf (JSON) → kein Redirect, JS lädt selbst neu
    exit;
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
    require_competition_open((int)$m['cid']);

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
