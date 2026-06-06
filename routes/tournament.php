<?php

function index(array $p): void {
    if (can_edit()) {
        $tournaments = db_fetchall("SELECT * FROM tournament ORDER BY event_date DESC, id DESC");
    } else {
        $tournaments = db_fetchall("SELECT * FROM tournament WHERE is_public=1 ORDER BY event_date DESC, id DESC");
    }
    render('tournament/index', ['page_title' => 'Turniere', 'tournaments' => $tournaments]);
}

function new_tournament(array $p): void {
    require_edit();
    csrf_verify();
    $name             = trim(post('name'));
    $organizer        = trim(post('organizer'));
    $sport            = trim(post('sport'));
    $raw_url          = trim(post('info_url'));
    $parsed           = parse_url($raw_url);
    $info_url         = in_array($parsed['scheme'] ?? '', ['http', 'https']) ? $raw_url : '';
    $event_date       = trim(post('event_date'));
    $max_competitions = max(1, min(5, (int)post('max_competitions', 1)));
    $is_public        = post('is_public') === '1' ? 1 : 0;
    $show_skill       = post('show_skill') === '1' ? 1 : 0;
    [$registrations_open, $is_done] = _tournament_status(post('tournament_status', 'open'));

    if (!$name) {
        flash('danger', 'Name erforderlich.');
        redirect('');
        return;
    }
    $tid = db_insert(
        "INSERT INTO tournament (name, organizer, sport, event_date, max_competitions, registrations_open, is_public, is_done, info_url, show_skill) VALUES (?,?,?,?,?,?,?,?,?,?)",
        [$name, $organizer, $sport, $event_date, $max_competitions, $registrations_open, $is_public, $is_done, $info_url, $show_skill]
    );
    _save_tournament_files((int)$tid);
    redirect('tournament/' . $tid);
}

function show(array $p): void {
    $t = db_fetch("SELECT * FROM tournament WHERE id = ?", [$p['id']]);
    if (!$t) { redirect(''); return; }
    if (!$t['is_public'] && !can_edit()) {
        flash('warning', 'Dieses Turnier ist nicht öffentlich sichtbar.');
        redirect('');
        return;
    }
    $comps = db_fetchall("SELECT * FROM competition WHERE tournament_id = ? ORDER BY id", [$p['id']]);
    $comp_info = [];
    foreach ($comps as $c) {
        $cnt = db_fetch("SELECT COUNT(*) as n FROM competition_player WHERE competition_id = ?", [$c['id']])['n'];
        $comp_info[] = ['comp' => $c, 'player_count' => $cnt];
    }

    $registrations   = [];
    $change_requests = [];
    $history         = [];

    if (can_edit()) {
        $regs = db_fetchall(
            "SELECT * FROM registration WHERE tournament_id=? ORDER BY (status='pending') DESC, created_at DESC",
            [$p['id']]
        );
        foreach ($regs as $r) {
            $rcomps = db_fetchall(
                "SELECT c.id as cid, c.name, rc.status FROM competition c
                 JOIN registration_competition rc ON rc.competition_id=c.id
                 WHERE rc.registration_id=? ORDER BY c.name",
                [$r['id']]
            );
            $has_pending = count(array_filter($rcomps, fn($c) => $c['status'] === 'pending')) > 0;
            $registrations[] = ['reg' => $r, 'competitions' => $rcomps, 'has_pending' => $has_pending];
        }

        $raw_crs = db_fetchall(
            "SELECT rcr.id, rcr.request_type, rcr.new_competitions, rcr.status, rcr.created_at,
             r.id as rid, r.lastname, r.firstname, r.email
             FROM registration_change_request rcr
             JOIN registration r ON r.id = rcr.registration_id
             WHERE r.tournament_id=? ORDER BY rcr.created_at",
            [$p['id']]
        );
        foreach ($raw_crs as $rcr) {
            $comp_rows = db_fetchall(
                "SELECT rcc.competition_id, rcc.action, rcc.status, c.name
                 FROM registration_change_competition rcc
                 JOIN competition c ON c.id = rcc.competition_id
                 WHERE rcc.change_request_id=? ORDER BY rcc.action DESC, c.name",
                [$rcr['id']]
            );
            $entry = ['rcr' => $rcr, 'competitions' => $comp_rows];
            if ($rcr['status'] === 'pending') {
                $change_requests[] = $entry;
            } else {
                $history[] = [
                    'date'           => $rcr['created_at'] ?? '',
                    'kind'           => 'change',
                    'lastname'       => $rcr['lastname'],
                    'firstname'      => $rcr['firstname'],
                    'type_label'     => $rcr['request_type'] === 'withdraw' ? 'Rückzug' : 'Bewerbsänderung',
                    'overall_status' => $rcr['status'],
                    'competitions'   => $comp_rows,
                ];
            }
        }
        foreach (array_filter($registrations, fn($i) => !$i['has_pending']) as $item) {
            $r = $item['reg'];
            $history[] = [
                'date'           => $r['created_at'] ?? '',
                'kind'           => 'registration',
                'lastname'       => $r['lastname'],
                'firstname'      => $r['firstname'],
                'type_label'     => 'Nennung',
                'overall_status' => $r['status'],
                'competitions'   => $item['competitions'],
            ];
        }
        usort($history, fn($a, $b) => strcmp($b['date'], $a['date']));
    }

    $pending_registrations = can_edit()
        ? array_values(array_filter($registrations, fn($i) => $i['has_pending']))
        : [];

    render('tournament/show', [
        'page_title'      => $t['name'],
        't'               => $t,
        'comp_info'       => $comp_info,
        'registrations'   => $pending_registrations,
        'change_requests' => $change_requests,
        'history'         => $history,
    ]);
}

function settings(array $p): void {
    require_edit();
    csrf_verify();
    $t = db_fetch("SELECT * FROM tournament WHERE id = ?", [$p['id']]);
    if (!$t) { redirect(''); return; }

    $name             = trim(post('name')) ?: $t['name'];
    $organizer        = trim(post('organizer'));
    $sport            = trim(post('sport'));
    $raw_url          = trim(post('info_url'));
    $parsed           = parse_url($raw_url);
    $info_url         = in_array($parsed['scheme'] ?? '', ['http', 'https']) ? $raw_url : '';
    $event_date       = trim(post('event_date'));
    $max_competitions = max(1, min(5, (int)post('max_competitions', 1)));
    $is_public        = post('is_public') === '1' ? 1 : 0;
    $show_skill       = post('show_skill') === '1' ? 1 : 0;
    [$registrations_open, $is_done] = _tournament_status(post('tournament_status', 'open'));

    $ausschreibung = $t['ausschreibung'] ?? '';
    if (post('remove_ausschreibung')) {
        if ($ausschreibung) @unlink(UPLOAD_DIR . $ausschreibung);
        $ausschreibung = '';
    } else {
        $f = upload_file('ausschreibung_file', ['pdf']);
        if ($f) { if ($ausschreibung) @unlink(UPLOAD_DIR . $ausschreibung); $ausschreibung = $f; }
    }

    $banner_image = $t['banner_image'] ?? '';
    if (post('remove_banner')) {
        if ($banner_image) @unlink(UPLOAD_DIR . $banner_image);
        $banner_image = '';
    } else {
        $fb = upload_file('banner_file', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if ($fb) { if ($banner_image) @unlink(UPLOAD_DIR . $banner_image); $banner_image = $fb; }
    }

    db_execute(
        "UPDATE tournament SET name=?, organizer=?, sport=?, event_date=?, max_competitions=?,
         ausschreibung=?, registrations_open=?, is_public=?, is_done=?, banner_image=?, info_url=?, show_skill=?
         WHERE id=?",
        [$name, $organizer, $sport, $event_date, $max_competitions,
         $ausschreibung, $registrations_open, $is_public, $is_done, $banner_image, $info_url, $show_skill,
         $p['id']]
    );
    flash('success', 'Einstellungen gespeichert.');
    redirect('tournament/' . $p['id']);
}

function delete(array $p): void {
    require_edit();
    csrf_verify();
    db_execute("DELETE FROM tournament WHERE id = ?", [$p['id']]);
    flash('info', 'Turnier gelöscht.');
    redirect('');
}

function ausschreibung(array $p): void {
    $t = db_fetch("SELECT ausschreibung, is_public FROM tournament WHERE id=?", [$p['id']]);
    if (!$t || !$t['ausschreibung']) {
        flash('warning', 'Keine Ausschreibung vorhanden.');
        redirect('tournament/' . $p['id']);
        return;
    }
    if (!$t['is_public'] && !can_edit()) {
        flash('warning', 'Dieses Turnier ist nicht öffentlich sichtbar.');
        redirect('');
        return;
    }
    $file = UPLOAD_DIR . $t['ausschreibung'];
    if (!file_exists($file)) {
        flash('warning', 'Datei nicht gefunden.');
        redirect('tournament/' . $p['id']);
        return;
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Ausschreibung.pdf"');
    readfile($file);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function _tournament_status(string $status): array {
    return match($status) {
        'done'   => [0, 1],
        'closed' => [0, 0],
        default  => [1, 0],
    };
}

function _save_tournament_files(int $tid): void {
    $ausschreibung = upload_file('ausschreibung_file', ['pdf']);
    if ($ausschreibung) {
        db_execute("UPDATE tournament SET ausschreibung=? WHERE id=?", [$ausschreibung, $tid]);
    }
    $banner = upload_file('banner_file', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    if ($banner) {
        db_execute("UPDATE tournament SET banner_image=? WHERE id=?", [$banner, $tid]);
    }
}
