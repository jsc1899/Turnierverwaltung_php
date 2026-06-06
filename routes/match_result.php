<?php
require_once __DIR__ . '/../lib/ko_bracket.php';

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

    if ($m['group_id'] === null && (int)$m['ko_round'] !== 3) {
        recompute_ko_from((int)$m['competition_id'], (int)$m['ko_round']);
    }
    _maybe_set_done((int)$m['competition_id']);
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
        if ($m['group_id'] === null && (int)$m['ko_round'] !== 3) {
            recompute_ko_from($cid, (int)$m['ko_round']);
        }
    }

    _maybe_set_done($cid);
    if ($errors) flash('warning', implode('<br>', $errors));
    redirect('competition/' . $cid);
}

function clear_result(array $p): void {
    require_edit();
    csrf_verify();
    $mid = (int)$p['id'];
    $m   = db_fetch("SELECT * FROM `match` WHERE id=?", [$mid]);
    if (!$m) { redirect(''); return; }
    db_execute("UPDATE `match` SET score1=NULL, score2=NULL, played=0 WHERE id=?", [$mid]);
    if ($m['group_id'] === null && (int)$m['ko_round'] !== 3) {
        recompute_ko_from((int)$m['competition_id'], (int)$m['ko_round']);
    }
    redirect('competition/' . $m['competition_id']);
}

// _maybe_set_done() is defined in lib/ko_bracket.php (required above)
