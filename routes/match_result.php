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

function _maybe_set_done(int $cid): void {
    $c = db_fetch("SELECT phase FROM competition WHERE id=?", [$cid]);
    if (!$c || !in_array($c['phase'], ['group', 'ko'], true)) return;
    if ($c['phase'] === 'ko') {
        $final = db_fetch(
            "SELECT id FROM `match` WHERE competition_id=? AND ko_round=2 AND played=1", [$cid]
        );
        if ($final) db_execute("UPDATE competition SET phase='done' WHERE id=?", [$cid]);
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
