<?php

function index(array $p): void {
    $u = current_user();
    if ($u && $u['role'] === 'admin') {
        $tournaments = db_fetchall("SELECT * FROM tournament ORDER BY sort_order ASC, event_date DESC, id DESC");
    } elseif ($u && $u['role'] === 'editor') {
        // Editor sieht zugeordnete Turniere (bearbeitbar) plus öffentliche (nur Ansicht)
        $tournaments = db_fetchall(
            "SELECT * FROM tournament
             WHERE is_public=1 OR id IN (SELECT tournament_id FROM tournament_editor WHERE user_id=?)
             ORDER BY sort_order ASC, event_date DESC, id DESC",
            [$u['id']]
        );
    } else {
        $tournaments = db_fetchall("SELECT * FROM tournament WHERE is_public=1 ORDER BY sort_order ASC, event_date DESC, id DESC");
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
    // Ersteller automatisch dem Turnier zuordnen (erhält Bearbeitungsrechte)
    $u = current_user();
    if ($u) {
        db_execute("INSERT IGNORE INTO tournament_editor (tournament_id, user_id) VALUES (?,?)", [(int)$tid, (int)$u['id']]);
    }
    _save_tournament_files((int)$tid);
    redirect('tournament/' . $tid);
}

function show(array $p): void {
    $t = db_fetch("SELECT * FROM tournament WHERE id = ?", [$p['id']]);
    if (!$t) { redirect(''); return; }
    $can_edit = can_edit_tournament((int)$p['id']);
    if (!$t['is_public'] && !$can_edit) {
        flash('warning', 'Dieses Turnier ist nicht öffentlich sichtbar.');
        redirect('');
        return;
    }
    $comps = db_fetchall("SELECT * FROM competition WHERE tournament_id = ? ORDER BY sort_order ASC, id", [$p['id']]);
    $comp_info = [];
    foreach ($comps as $c) {
        if (!empty($c['is_team'])) {
            $cnt = db_fetch("SELECT COUNT(*) as n FROM competition_team WHERE competition_id=?", [$c['id']])['n'];
        } elseif (!empty($c['is_doubles'])) {
            $cnt = db_fetch("SELECT COUNT(*) as n FROM competition_double WHERE competition_id=?", [$c['id']])['n'];
        } else {
            $cnt = db_fetch("SELECT COUNT(*) as n FROM competition_player WHERE competition_id=?", [$c['id']])['n'];
        }
        $comp_info[] = ['comp' => $c, 'player_count' => $cnt];
    }

    $registrations   = [];
    $change_requests = [];
    $history         = [];

    if ($can_edit) {
        $regs = db_fetchall(
            "SELECT * FROM registration WHERE tournament_id=? ORDER BY (status='pending') DESC, created_at DESC",
            [$p['id']]
        );
        foreach ($regs as $r) {
            $rcomps = db_fetchall(
                "SELECT c.id as cid, c.name, c.is_doubles, rc.status, rc.partner_name FROM competition c
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
                "SELECT rcc.competition_id, rcc.action, rcc.status, rcc.partner_name, c.name, c.is_doubles
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

    $pending_registrations = $can_edit
        ? array_values(array_filter($registrations, fn($i) => $i['has_pending']))
        : [];

    // Editor-Verwaltung: zugeordnete Editoren + noch verfügbare Editor-Benutzer
    $editors = $available_editors = [];
    if ($can_edit) {
        $editors = db_fetchall(
            "SELECT u.id, u.username, u.email FROM tournament_editor te
             JOIN user u ON u.id = te.user_id
             WHERE te.tournament_id = ? ORDER BY u.username",
            [(int)$p['id']]
        );
        $available_editors = db_fetchall(
            "SELECT id, username, email FROM user
             WHERE role='editor'
               AND id NOT IN (SELECT user_id FROM tournament_editor WHERE tournament_id=?)
             ORDER BY username",
            [(int)$p['id']]
        );
    }

    render('tournament/show', [
        'page_title'        => $t['name'],
        't'                 => $t,
        'comp_info'         => $comp_info,
        'registrations'     => $pending_registrations,
        'change_requests'   => $change_requests,
        'history'           => $history,
        'can_edit'          => $can_edit,
        'editors'           => $editors,
        'available_editors' => $available_editors,
    ]);
}

function settings(array $p): void {
    require_tournament_edit((int)$p['id']);
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
        if ($ausschreibung && file_exists(UPLOAD_DIR . $ausschreibung)) unlink(UPLOAD_DIR . $ausschreibung);
        $ausschreibung = '';
    } else {
        $f = upload_file('ausschreibung_file', ['pdf']);
        if ($f) {
            if ($ausschreibung && file_exists(UPLOAD_DIR . $ausschreibung)) unlink(UPLOAD_DIR . $ausschreibung);
            $ausschreibung = $f;
        }
    }

    $banner_image = $t['banner_image'] ?? '';
    if (post('remove_banner')) {
        if ($banner_image && file_exists(UPLOAD_DIR . $banner_image)) unlink(UPLOAD_DIR . $banner_image);
        $banner_image = '';
    } else {
        $fb = upload_file('banner_file', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if ($fb) {
            if ($banner_image && file_exists(UPLOAD_DIR . $banner_image)) unlink(UPLOAD_DIR . $banner_image);
            $banner_image = $fb;
        }
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
    redirect('tournament/' . $p['id'] . '#tab-settings');
}

function delete(array $p): void {
    require_tournament_edit((int)$p['id']);
    csrf_verify();
    db_execute("DELETE FROM tournament WHERE id = ?", [$p['id']]);
    flash('info', 'Turnier gelöscht.');
    redirect('');
}

// Turnier-Monitor: mehrere Bewerbe nebeneinander (je Bewerb eine Spalte als eingebettete
// Bewerbs-Monitoransicht). Öffentlich wie tournament/show().
function monitor(array $p): void {
    $t = db_fetch("SELECT * FROM tournament WHERE id = ?", [$p['id']]);
    if (!$t) { redirect(''); return; }
    if (!$t['is_public'] && !can_edit_tournament((int)$p['id'])) {
        flash('warning', 'Dieses Turnier ist nicht öffentlich sichtbar.');
        redirect('');
        return;
    }
    $all = db_fetchall("SELECT id, name FROM competition WHERE tournament_id = ? ORDER BY sort_order ASC, id", [$p['id']]);
    $sel = array_filter(array_map('intval', explode(',', (string)($t['monitor_competitions'] ?? ''))));
    // Auswahl in der Reihenfolge der Bewerbsliste; ohne Auswahl alle anzeigen.
    $comps = $sel
        ? array_values(array_filter($all, fn($c) => in_array((int)$c['id'], $sel, true)))
        : $all;
    render('tournament/monitor', [
        'page_title'        => $t['name'] . ' — Monitor',
        't'                 => $t,
        'comps'             => $comps,
        'mon_show_schedule' => !empty($t['monitor_show_schedule']),
        'mon_scroll_speed'  => in_array($t['monitor_scroll_speed'] ?? 'medium', ['slow','medium','fast'], true) ? $t['monitor_scroll_speed'] : 'medium',
        'mon_scroll_mode'   => ($t['monitor_scroll_mode'] ?? 'smooth') === 'block' ? 'block' : 'smooth',
        'mon_block_pause'   => max(1, (int)($t['monitor_block_pause'] ?? 5)),
    ]);
}

// Einstellungen des Turnier-Monitors speichern (Register „Monitor" auf Turnierebene).
function monitor_settings(array $p): void {
    require_tournament_edit((int)$p['id']);
    csrf_verify();
    $tid = (int)$p['id'];
    $show_schedule = post('monitor_show_schedule') ? 1 : 0;
    $speed = in_array(post('monitor_scroll_speed'), ['slow', 'medium', 'fast'], true) ? post('monitor_scroll_speed') : 'medium';
    $mode  = post('monitor_scroll_mode') === 'block' ? 'block' : 'smooth';
    $pause = max(1, min(120, (int)post('monitor_block_pause', 5)));
    // Ausgewählte Bewerbe (nur tatsächlich zu diesem Turnier gehörende IDs übernehmen)
    $valid = array_map(fn($c) => (int)$c['id'], db_fetchall("SELECT id FROM competition WHERE tournament_id=?", [$tid]));
    $picked = array_values(array_intersect(array_map('intval', (array)post('monitor_competitions', [])), $valid));
    $comp_csv = implode(',', $picked);
    db_execute(
        "UPDATE tournament SET monitor_show_schedule=?, monitor_scroll_speed=?, monitor_scroll_mode=?, monitor_block_pause=?, monitor_competitions=? WHERE id=?",
        [$show_schedule, $speed, $mode, $pause, $comp_csv, $tid]
    );
    flash('success', 'Monitor-Einstellungen gespeichert.');
    redirect('tournament/' . $tid . '#tab-monitor');
}

function ausschreibung(array $p): void {
    $t = db_fetch("SELECT ausschreibung, is_public FROM tournament WHERE id=?", [$p['id']]);
    if (!$t || !$t['ausschreibung']) {
        flash('warning', 'Keine Ausschreibung vorhanden.');
        redirect('tournament/' . $p['id']);
        return;
    }
    if (!$t['is_public'] && !can_edit_tournament((int)$p['id'])) {
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

function reorder(array $p): void {
    require_edit();
    csrf_verify();
    $ids = array_map('intval', $_POST['ids'] ?? []);
    foreach ($ids as $i => $id) {
        db_execute("UPDATE tournament SET sort_order=? WHERE id=?", [$i, $id]);
    }
    header('Content-Type: application/json');
    echo '{"ok":true}';
    exit;
}

function reorder_competitions(array $p): void {
    require_tournament_edit((int)$p['tid']);
    csrf_verify();
    $tid = (int)$p['tid'];
    $ids = array_map('intval', $_POST['ids'] ?? []);
    foreach ($ids as $i => $id) {
        db_execute("UPDATE competition SET sort_order=? WHERE id=? AND tournament_id=?", [$i, $id, $tid]);
    }
    header('Content-Type: application/json');
    echo '{"ok":true}';
    exit;
}

// ── Editor-Zuordnung (Turniereinstellungen) ───────────────────────────────────

// Editor einem Turnier zuordnen. Erlaubt für Admins und bereits zugeordnete Editoren.
function add_editor(array $p): void {
    require_tournament_edit((int)$p['id']);
    csrf_verify();
    $tid = (int)$p['id'];
    $uid = (int)post('user_id');
    // Nur Benutzer mit Rolle 'editor' zuordnen
    $u = db_fetch("SELECT id FROM user WHERE id=? AND role='editor'", [$uid]);
    if (!$u) {
        flash('danger', 'Benutzer nicht gefunden oder kein Editor.');
    } else {
        db_execute("INSERT IGNORE INTO tournament_editor (tournament_id, user_id) VALUES (?,?)", [$tid, $uid]);
        flash('success', 'Editor zugeordnet.');
    }
    redirect('tournament/' . $tid . '#tab-settings');
}

// Editor-Zuordnung entfernen. Erlaubt für Admins und bereits zugeordnete Editoren.
// Editoren können sich jedoch nicht selbst entfernen (Schutz vor Selbst-Aussperrung);
// das übernimmt ein Admin.
function remove_editor(array $p): void {
    require_tournament_edit((int)$p['id']);
    csrf_verify();
    $tid = (int)$p['id'];
    $uid = (int)$p['uid'];
    $u   = current_user();
    if (!is_admin() && (int)$u['id'] === $uid) {
        flash('danger', 'Sie können sich nicht selbst aus dem Turnier entfernen. Bitte einen Admin darum bitten.');
        redirect('tournament/' . $tid . '#tab-settings');
        return;
    }
    db_execute("DELETE FROM tournament_editor WHERE tournament_id=? AND user_id=?", [$tid, $uid]);
    flash('info', 'Editor-Zuordnung entfernt.');
    redirect('tournament/' . $tid . '#tab-settings');
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
