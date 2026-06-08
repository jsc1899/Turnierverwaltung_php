<?php
require_once __DIR__ . '/../lib/mail.php';
require_once __DIR__ . '/../lib/tokens.php';

// ── Public registration form ───────────────────────────────────────────────────

function register_form(array $p): void {
    $tid = (int)$p['id'];
    $t   = db_fetch("SELECT * FROM tournament WHERE id=?", [$tid]);
    if (!$t) { redirect(''); return; }
    if (!$t['is_public'] && !can_edit()) {
        flash('warning', 'Dieses Turnier ist nicht öffentlich sichtbar.');
        redirect('');
        return;
    }
    $comps = db_fetchall(
        "SELECT * FROM competition WHERE tournament_id=? AND registrations_open=1 ORDER BY name", [$tid]
    );
    $comp_counts = [];
    foreach ($comps as $c) {
        if (!empty($c['is_doubles'])) {
            $comp_counts[$c['id']] = db_fetch(
                "SELECT COUNT(*) as n FROM competition_double WHERE competition_id=?", [$c['id']]
            )['n'];
        } else {
            $comp_counts[$c['id']] = db_fetch(
                "SELECT COUNT(*) as n FROM competition_player WHERE competition_id=?", [$c['id']]
            )['n'];
        }
    }

    if (is_post()) {
        csrf_verify();
        $lastname   = trim(post('lastname'));
        $firstname  = trim(post('firstname'));
        $club       = trim(post('club'));
        $gender     = trim(post('gender'));
        $pass_nr    = trim(post('pass_nr'));
        $email      = trim(post('email'));
        $skill      = (float)str_replace(',', '.', post('skill', '0'));
        $comp_ids   = (array)($_POST['competition_ids'] ?? []);
        $max_c      = (int)($t['max_competitions'] ?: 1);

        $valid_ids = [];
        if ($t['registrations_open']) {
            foreach ($comps as $c) {
                if ($c['registrations_open'] && (!$c['max_players'] || $comp_counts[$c['id']] < $c['max_players'])) {
                    $valid_ids[] = (string)$c['id'];
                }
            }
        }

        $errors = [];
        if (!$t['registrations_open']) $errors[] = 'Nennungen für dieses Turnier sind derzeit geschlossen.';
        if (!$lastname)  $errors[] = 'Nachname erforderlich.';
        if (!$firstname) $errors[] = 'Vorname erforderlich.';
        if (!$pass_nr)   $errors[] = 'Pass-Nr. erforderlich.';
        if (!$email)     $errors[] = 'E-Mail erforderlich.';
        if ($skill <= 0) $errors[] = 'Spielstärke erforderlich (muss größer als 0 sein).';
        if (!$comp_ids)  $errors[] = 'Mindestens ein Bewerb muss ausgewählt werden.';
        elseif (count($comp_ids) > $max_c) $errors[] = "Maximal $max_c Bewerb(e) erlaubt.";
        foreach ($comp_ids as $cid) {
            if (!in_array((string)$cid, $valid_ids)) { $errors[] = 'Ungültige Bewerbsauswahl.'; break; }
        }

        if (!$errors) {
            // Check existing assignments
            $existing_player = null;
            if ($pass_nr) {
                $existing_player = db_fetch("SELECT id FROM player WHERE pass_nr=? AND pass_nr!=''", [$pass_nr]);
            }
            if (!$existing_player) {
                $existing_player = db_fetch("SELECT id FROM player WHERE name=? AND firstname=?", [$lastname, $firstname]);
            }
            if ($existing_player) {
                $assigned = db_fetchall(
                    "SELECT c.name, cp.competition_id FROM competition_player cp
                     JOIN competition c ON c.id=cp.competition_id
                     WHERE cp.player_id=? AND c.tournament_id=?",
                    [$existing_player['id'], $tid]
                );
                $assigned_cids = array_column($assigned, 'competition_id');
                $overlap    = array_intersect($comp_ids, $assigned_cids);
                $too_many   = (count($assigned) + count($comp_ids) - count($overlap)) > $max_c;
                if ($overlap || $too_many) {
                    $assigned_names = implode(', ', array_column($assigned, 'name'));
                    if ($overlap) {
                        $errors[] = "Sie sind bereits folgenden Bewerben zugeordnet: $assigned_names. Eine erneute Nennung ist nicht möglich.";
                    } else {
                        $errors[] = "Sie sind bereits folgenden Bewerben zugeordnet: $assigned_names. Pro Spieler sind maximal $max_c Bewerb(e) erlaubt.";
                    }
                }
            }
        }

        if ($errors) {
            foreach ($errors as $err) flash('danger', $err);
        } else {
            $rid = db_insert(
                "INSERT INTO registration (tournament_id,lastname,firstname,club,gender,pass_nr,skill,email) VALUES (?,?,?,?,?,?,?,?)",
                [$tid, $lastname, $firstname, $club, $gender, $pass_nr, $skill, $email]
            );
            $comps_map     = array_column($comps, null, 'id');
            $partner_names = $_POST['partner_name'] ?? [];
            foreach ($comp_ids as $cid) {
                $is_d  = !empty($comps_map[(int)$cid]['is_doubles']);
                $pname = $is_d ? trim($partner_names[(int)$cid] ?? '') : '';
                db_execute(
                    "INSERT INTO registration_competition (registration_id,competition_id,status,partner_name) VALUES (?,?,'pending',?)",
                    [(int)$rid, (int)$cid, $pname]
                );
            }
            flash('success', 'Nennung erfolgreich eingereicht! Sie erhalten eine Bestätigung vom Veranstalter.');
            redirect('tournament/' . $tid . '/register');
            return;
        }
    }
    render('registration/form', [
        'page_title'  => 'Nennung: ' . $t['name'],
        't'           => $t,
        'comps'       => $comps,
        'comp_counts' => $comp_counts,
    ]);
}

// ── Admin: confirm/reject registrations ───────────────────────────────────────

function confirm_all(array $p): void {
    require_edit();
    csrf_verify();
    $rid = (int)$p['id'];
    $r   = db_fetch("SELECT * FROM registration WHERE id=?", [$rid]);
    if (!$r) { redirect(''); return; }

    $t     = db_fetch("SELECT sport FROM tournament WHERE id=?", [$r['tournament_id']]);
    $sport = $t ? ($t['sport'] ?? '') : '';
    $pid   = _find_or_create_player($r);
    _update_player_skill_db($pid, $sport, (float)$r['skill']);

    $pending = db_fetchall(
        "SELECT competition_id FROM registration_competition WHERE registration_id=? AND status='pending'", [$rid]
    );
    $added = 0;
    $full_comps = [];
    foreach ($pending as $row) {
        if (_add_player_to_competition($pid, $row['competition_id'], (float)$r['skill'])) {
            $added++;
        } else {
            $comp = db_fetch("SELECT name FROM competition WHERE id=?", [$row['competition_id']]);
            $full_comps[] = $comp ? $comp['name'] : "Bewerb #{$row['competition_id']}";
        }
        db_execute("UPDATE registration_competition SET status='confirmed' WHERE registration_id=? AND competition_id=?",
            [$rid, $row['competition_id']]);
    }
    _update_registration_status($rid);

    _notify_reg_status($r);

    if ($full_comps) {
        flash('warning', 'Spieler nicht zugeordnet (Bewerb voll): ' . implode(', ', $full_comps));
    }
    flash('success', "Alle Nennungen bestätigt — Spieler $added Bewerb(en) zugeordnet.");
    redirect('tournament/' . $r['tournament_id']);
}

function reject_all(array $p): void {
    require_edit();
    csrf_verify();
    $rid = (int)$p['id'];
    $r   = db_fetch("SELECT * FROM registration WHERE id=?", [$rid]);
    if (!$r) { redirect(''); return; }
    db_execute("UPDATE registration_competition SET status='rejected' WHERE registration_id=? AND status='pending'", [$rid]);
    db_execute("UPDATE registration SET status='rejected' WHERE id=?", [$rid]);
    _notify_reg_status($r);
    flash('info', 'Alle Nennungen abgelehnt.');
    redirect('tournament/' . $r['tournament_id']);
}

function confirm_comp(array $p): void {
    require_edit();
    csrf_verify();
    $rid = (int)$p['id']; $cid = (int)$p['cid'];
    $r   = db_fetch("SELECT * FROM registration WHERE id=?", [$rid]);
    $rc  = db_fetch("SELECT * FROM registration_competition WHERE registration_id=? AND competition_id=?", [$rid, $cid]);
    if (!$r || !$rc) { redirect(''); return; }

    $t     = db_fetch("SELECT sport FROM tournament WHERE id=?", [$r['tournament_id']]);
    $sport = $t ? ($t['sport'] ?? '') : '';
    $pid   = _find_or_create_player($r);
    _update_player_skill_db($pid, $sport, (float)$r['skill']);
    $added = _add_player_to_competition($pid, $cid, (float)$r['skill']);
    db_execute("UPDATE registration_competition SET status='confirmed' WHERE registration_id=? AND competition_id=?", [$rid, $cid]);
    _update_registration_status($rid);

    $still_pending = db_fetch(
        "SELECT COUNT(*) as n FROM registration_competition WHERE registration_id=? AND status='pending'", [$rid]
    )['n'];
    if ($still_pending == 0) _notify_reg_status($r);

    if (!$added) {
        flash('warning', 'Bewerb ist voll — Spieler wurde nicht zugeordnet.');
    }
    flash('success', 'Nennung bestätigt.');
    redirect('tournament/' . $r['tournament_id']);
}

function reject_comp(array $p): void {
    require_edit();
    csrf_verify();
    $rid = (int)$p['id']; $cid = (int)$p['cid'];
    $r   = db_fetch("SELECT * FROM registration WHERE id=?", [$rid]);
    if (!$r) { redirect(''); return; }
    db_execute("UPDATE registration_competition SET status='rejected' WHERE registration_id=? AND competition_id=?", [$rid, $cid]);
    _update_registration_status($rid);
    $still_pending = db_fetch(
        "SELECT COUNT(*) as n FROM registration_competition WHERE registration_id=? AND status='pending'", [$rid]
    )['n'];
    if ($still_pending == 0) _notify_reg_status($r);
    flash('info', 'Nennung abgelehnt.');
    redirect('tournament/' . $r['tournament_id']);
}

// ── Magic-Link: Link anfordern ────────────────────────────────────────────────

function request_link(array $p): void {
    if (is_post()) {
        csrf_verify();
        if (!rate_limit_check('request_link', 5, 300)) {
            flash('info', 'Falls Nennungen unter dieser Adresse existieren, wurde ein Link gesendet.');
            redirect('nennung/link');
            return;
        }
        $email = strtolower(trim(post('email')));
        if ($email) {
            $exists = db_fetch(
                "SELECT r.id FROM registration r
                 JOIN tournament t ON t.id = r.tournament_id
                 WHERE LOWER(r.email) = ? AND t.is_done = 0 LIMIT 1",
                [$email]
            );
            if ($exists) {
                $token = make_manage_email_token($email);
                send_reg_manage_mail($email, $token);
            }
        }
        // Gleiche Meldung egal ob Email gefunden — kein User-Enumeration
        flash('info', 'Falls Nennungen unter dieser Adresse existieren, wurde ein Link gesendet.');
        redirect('nennung/link');
        return;
    }
    render('registration/request_link', ['page_title' => 'Nennungen verwalten']);
}

// ── Magic-Link: Verwaltungsseite (alle Nennungen dieser Email) ────────────────

function manage_view(array $p): void {
    $token = $p['token'];
    $email = verify_manage_email_token($token);
    if (!$email) {
        flash('danger', 'Ungültiger oder abgelaufener Link. Bitte erneut anfordern.');
        redirect('nennung/link');
        return;
    }

    // Alle aktiven Nennungen für diese Email
    $regs = db_fetchall(
        "SELECT DISTINCT r.*, t.name as tname, t.registrations_open as t_regs_open, t.is_done,
         t.max_competitions
         FROM registration r
         JOIN tournament t ON t.id = r.tournament_id
         WHERE t.is_done = 0
           AND (LOWER(r.email) = ?
             OR EXISTS (
               SELECT 1 FROM player pl
               WHERE LOWER(pl.email) = ? AND pl.name = r.lastname AND pl.firstname = r.firstname
             ))
         ORDER BY r.created_at DESC",
        [$email, $email]
    );

    $items = [];
    foreach ($regs as $r) {
        $rcomps = db_fetchall(
            "SELECT DISTINCT c.id, c.name, c.is_doubles
             FROM competition c
             JOIN player pl ON pl.name = ? AND pl.firstname = ?
             WHERE c.tournament_id = ?
               AND (
                 EXISTS (SELECT 1 FROM competition_player cp WHERE cp.competition_id = c.id AND cp.player_id = pl.id)
                 OR EXISTS (
                   SELECT 1 FROM competition_double cd
                   JOIN `double` d ON d.id = cd.double_id
                   WHERE cd.competition_id = c.id AND (d.player1_id = pl.id OR d.player2_id = pl.id)
                 )
               )
             ORDER BY c.name",
            [$r['lastname'], $r['firstname'], $r['tournament_id']]
        );
        $pending_req = db_fetch(
            "SELECT * FROM registration_change_request WHERE registration_id=? AND status='pending'",
            [$r['id']]
        );
        $all_comps = db_fetchall(
            "SELECT c.id, c.name, c.registrations_open, c.is_doubles,
                    COALESCE(rc.partner_name, '') as partner_name
             FROM competition c
             LEFT JOIN registration_competition rc ON rc.competition_id = c.id AND rc.registration_id = ?
             WHERE c.tournament_id = ? ORDER BY c.name",
            [$r['id'], $r['tournament_id']]
        );
        $items[] = [
            'reg'          => $r,
            'competitions' => $rcomps,
            'pending_req'  => $pending_req,
            'all_comps'    => $all_comps,
        ];
    }

    render('registration/manage', [
        'page_title' => 'Meine Nennungen',
        'email'      => $email,
        'token'      => $token,
        'items'      => $items,
    ]);
}

// ── Magic-Link: Rückzug ───────────────────────────────────────────────────────

function manage_withdraw(array $p): void {
    csrf_verify();
    $token = $p['token'];
    $rid   = (int)$p['rid'];
    $email = verify_manage_email_token($token);
    if (!$email) {
        flash('danger', 'Ungültiger oder abgelaufener Link.');
        redirect('nennung/link');
        return;
    }
    $r = db_fetch("SELECT * FROM registration WHERE id=?", [$rid]);
    if (!$r || !_reg_belongs_to_email($r, $email)) {
        flash('danger', 'Nennung nicht gefunden.');
        redirect('nennung/verwalten/' . urlencode($token));
        return;
    }
    $existing = db_fetch(
        "SELECT id FROM registration_change_request WHERE registration_id=? AND status='pending'", [$rid]
    );
    if ($existing) {
        flash('warning', 'Es ist bereits ein Antrag für diese Nennung offen.');
    } else {
        db_insert(
            "INSERT INTO registration_change_request (registration_id, request_type) VALUES (?, 'withdraw')",
            [$rid]
        );
        flash('success', 'Rückzugsantrag eingereicht. Der Veranstalter wird ihn bearbeiten.');
    }
    redirect('nennung/verwalten/' . urlencode($token));
}

// ── Magic-Link: Bewerbe ändern ────────────────────────────────────────────────

function manage_change(array $p): void {
    csrf_verify();
    $token = $p['token'];
    $rid   = (int)$p['rid'];
    $email = verify_manage_email_token($token);
    if (!$email) {
        flash('danger', 'Ungültiger oder abgelaufener Link.');
        redirect('nennung/link');
        return;
    }
    $r = db_fetch("SELECT * FROM registration WHERE id=?", [$rid]);
    if (!$r || !_reg_belongs_to_email($r, $email)) {
        flash('danger', 'Nennung nicht gefunden.');
        redirect('nennung/verwalten/' . urlencode($token));
        return;
    }
    $existing = db_fetch(
        "SELECT id FROM registration_change_request WHERE registration_id=? AND status='pending'", [$rid]
    );
    if ($existing) {
        flash('warning', 'Es ist bereits ein offener Antrag für diese Nennung vorhanden.');
        redirect('nennung/verwalten/' . urlencode($token));
        return;
    }

    $new_cids = array_map('intval', (array)($_POST['competition_ids'] ?? []));
    $t        = db_fetch("SELECT max_competitions, registrations_open FROM tournament WHERE id=?", [$r['tournament_id']]);
    $max_c    = (int)($t['max_competitions'] ?: 1);

    if (count($new_cids) > $max_c) {
        flash('warning', "Maximal $max_c Bewerb(e) erlaubt.");
        redirect('nennung/verwalten/' . urlencode($token));
        return;
    }

    $player = db_fetch(
        "SELECT pl.id FROM player pl WHERE pl.name = ? AND pl.firstname = ? LIMIT 1",
        [$r['lastname'], $r['firstname']]
    );
    $current_cids = [];
    if ($player) {
        $pid_c = (int)$player['id'];
        $current_cids = array_column(
            db_fetchall(
                "SELECT DISTINCT c.id as competition_id
                 FROM competition c
                 WHERE c.tournament_id = ?
                   AND (
                     EXISTS (SELECT 1 FROM competition_player cp WHERE cp.competition_id = c.id AND cp.player_id = ?)
                     OR EXISTS (
                       SELECT 1 FROM competition_double cd
                       JOIN `double` d ON d.id = cd.double_id
                       WHERE cd.competition_id = c.id AND (d.player1_id = ? OR d.player2_id = ?)
                     )
                   )",
                [$r['tournament_id'], $pid_c, $pid_c, $pid_c]
            ),
            'competition_id'
        );
    }

    if (empty($new_cids)) {
        // Leere Auswahl = Rückzug
        db_insert(
            "INSERT INTO registration_change_request (registration_id, request_type) VALUES (?, 'withdraw')",
            [$rid]
        );
        flash('success', 'Rückzugsantrag eingereicht.');
        redirect('nennung/verwalten/' . urlencode($token));
        return;
    }

    $to_add    = array_diff($new_cids, $current_cids);
    $to_remove = array_diff($current_cids, $new_cids);

    // Partner-Name für bereits zugeteilte Doppelbewerbe direkt aktualisieren
    $partner_names  = $_POST['partner_name'] ?? [];
    $staying_cids   = array_intersect($new_cids, $current_cids);
    foreach ($staying_cids as $stay_cid) {
        $stay_comp = db_fetch("SELECT is_doubles FROM competition WHERE id=?", [$stay_cid]);
        if (!empty($stay_comp['is_doubles'])) {
            $pname = trim($partner_names[$stay_cid] ?? '');
            db_execute(
                "UPDATE registration_competition SET partner_name=? WHERE registration_id=? AND competition_id=?",
                [$pname, $rid, $stay_cid]
            );
        }
    }

    if (empty($to_add) && empty($to_remove)) {
        flash('info', 'Keine Änderung gegenüber aktueller Zuordnung festgestellt.');
        redirect('nennung/verwalten/' . urlencode($token));
        return;
    }

    // Prüfen: offene Bewerbe für Hinzufügungen
    if (!empty($t['registrations_open'])) {
        $closed = [];
        foreach ($to_add as $cid) {
            $comp = db_fetch("SELECT name, registrations_open FROM competition WHERE id=?", [$cid]);
            if ($comp && !$comp['registrations_open']) $closed[] = $comp['name'];
        }
        if ($closed) {
            flash('warning', 'Nennung für folgende Bewerbe derzeit nicht möglich: ' . implode(', ', $closed));
            redirect('nennung/verwalten/' . urlencode($token));
            return;
        }
    } elseif (!empty($to_add)) {
        flash('warning', 'Nennungen für dieses Turnier sind derzeit geschlossen. Es können nur Abmeldungen beantragt werden.');
        redirect('nennung/verwalten/' . urlencode($token));
        return;
    }

    $rcr_id       = db_insert(
        "INSERT INTO registration_change_request (registration_id, request_type) VALUES (?, 'modify')",
        [$rid]
    );
    $partner_names = $_POST['partner_name'] ?? [];
    foreach ($to_add as $cid) {
        $comp  = db_fetch("SELECT is_doubles FROM competition WHERE id=?", [$cid]);
        $pname = !empty($comp['is_doubles']) ? trim($partner_names[$cid] ?? '') : '';
        db_execute(
            "INSERT INTO registration_change_competition (change_request_id, competition_id, action, partner_name) VALUES (?,?,'add',?)",
            [$rcr_id, $cid, $pname]
        );
    }
    foreach ($to_remove as $cid) {
        db_execute(
            "INSERT INTO registration_change_competition (change_request_id, competition_id, action) VALUES (?,?,'remove')",
            [$rcr_id, $cid]
        );
    }
    flash('success', 'Änderungsantrag eingereicht. Der Veranstalter wird ihn bearbeiten.');
    redirect('nennung/verwalten/' . urlencode($token));
}

// ── Change request admin actions ───────────────────────────────────────────────

function change_confirm_all(array $p): void {
    require_edit();
    csrf_verify();
    $rcr_id = (int)$p['id'];
    $rcr    = db_fetch("SELECT rcr.*, r.tournament_id FROM registration_change_request rcr JOIN registration r ON r.id=rcr.registration_id WHERE rcr.id=?", [$rcr_id]);
    if (!$rcr) { redirect(''); return; }

    if ($rcr['request_type'] === 'withdraw') {
        _process_withdraw($rcr['registration_id'], $rcr['tournament_id']);
        $full_comps = [];
    } else {
        $full_comps = _process_change_approve_all($rcr_id, $rcr['registration_id']);
    }
    db_execute("UPDATE registration_change_request SET status='confirmed' WHERE id=?", [$rcr_id]);
    _notify_change_request_status($rcr_id);
    if ($full_comps) {
        flash('warning', 'Spieler nicht zugeordnet (Bewerb voll): ' . implode(', ', $full_comps));
    }
    flash('success', 'Antrag bestätigt.');
    redirect('tournament/' . $rcr['tournament_id']);
}

function change_reject_all(array $p): void {
    require_edit();
    csrf_verify();
    $rcr_id = (int)$p['id'];
    $rcr    = db_fetch("SELECT rcr.*, r.tournament_id FROM registration_change_request rcr JOIN registration r ON r.id=rcr.registration_id WHERE rcr.id=?", [$rcr_id]);
    if (!$rcr) { redirect(''); return; }
    db_execute("UPDATE registration_change_competition SET status='rejected' WHERE change_request_id=? AND status='pending'", [$rcr_id]);
    db_execute("UPDATE registration_change_request SET status='rejected' WHERE id=?", [$rcr_id]);
    _notify_change_request_status($rcr_id);
    flash('info', 'Antrag abgelehnt.');
    redirect('tournament/' . $rcr['tournament_id']);
}

function change_confirm_comp(array $p): void {
    require_edit();
    csrf_verify();
    $rcr_id = (int)$p['id']; $cid = (int)$p['cid'];
    $rcr    = db_fetch("SELECT rcr.*, r.tournament_id, r.id as rid FROM registration_change_request rcr JOIN registration r ON r.id=rcr.registration_id WHERE rcr.id=?", [$rcr_id]);
    if (!$rcr) { redirect(''); return; }

    $comp_entry = db_fetch("SELECT * FROM registration_change_competition WHERE change_request_id=? AND competition_id=?", [$rcr_id, $cid]);
    if ($comp_entry) {
        if ($comp_entry['action'] === 'add') {
            $r = db_fetch("SELECT * FROM registration WHERE id=?", [$rcr['rid']]);
            if ($r) {
                $pid   = _find_or_create_player($r);
                $added = _add_player_to_competition($pid, $cid, (float)$r['skill']);
                if (!$added) {
                    flash('warning', 'Bewerb ist voll — Spieler wurde nicht zugeordnet.');
                }
                _sync_registration_competition($rcr['rid'], $cid, $comp_entry['partner_name'] ?? '');
            }
        } elseif ($comp_entry['action'] === 'remove') {
            $r = db_fetch("SELECT * FROM registration WHERE id=?", [$rcr['rid']]);
            if ($r) {
                $pid = _find_player($r);
                if ($pid) {
                    db_execute("DELETE FROM competition_player WHERE competition_id=? AND player_id=?", [$cid, $pid]);
                    _remove_player_from_competition_doubles($pid, $cid);
                }
            }
        }
        db_execute("UPDATE registration_change_competition SET status='confirmed' WHERE change_request_id=? AND competition_id=?", [$rcr_id, $cid]);
        _maybe_close_change_request($rcr_id);
        $closed_status = db_fetch("SELECT status FROM registration_change_request WHERE id=?", [$rcr_id])['status'] ?? 'pending';
        if ($closed_status !== 'pending') _notify_change_request_status($rcr_id);
    }
    flash('success', 'Bewerb bestätigt.');
    redirect('tournament/' . $rcr['tournament_id']);
}

function change_reject_comp(array $p): void {
    require_edit();
    csrf_verify();
    $rcr_id = (int)$p['id']; $cid = (int)$p['cid'];
    $rcr    = db_fetch("SELECT rcr.*, r.tournament_id FROM registration_change_request rcr JOIN registration r ON r.id=rcr.registration_id WHERE rcr.id=?", [$rcr_id]);
    if (!$rcr) { redirect(''); return; }
    db_execute("UPDATE registration_change_competition SET status='rejected' WHERE change_request_id=? AND competition_id=?", [$rcr_id, $cid]);
    _maybe_close_change_request($rcr_id);
    $closed_status = db_fetch("SELECT status FROM registration_change_request WHERE id=?", [$rcr_id])['status'] ?? 'pending';
    if ($closed_status !== 'pending') _notify_change_request_status($rcr_id);
    flash('info', 'Bewerb abgelehnt.');
    redirect('tournament/' . $rcr['tournament_id']);
}

// ── Internal helpers ───────────────────────────────────────────────────────────

function _sync_registration_competition(int $rid, int $cid, string $partner_name = ''): void {
    db_execute(
        "INSERT INTO registration_competition (registration_id, competition_id, status, partner_name)
         VALUES (?,?,'confirmed',?)
         ON DUPLICATE KEY UPDATE status='confirmed', partner_name = VALUES(partner_name)",
        [$rid, $cid, $partner_name]
    );
    db_execute("UPDATE registration SET status='confirmed' WHERE id=? AND status='rejected'", [$rid]);
}

function _remove_player_from_competition_doubles(int $pid, int $cid): void {
    $comp = db_fetch("SELECT tournament_id FROM competition WHERE id=?", [$cid]);

    // Collect partner player IDs before deleting
    $doubles = db_fetchall(
        "SELECT d.player1_id, d.player2_id FROM competition_double cd
         JOIN `double` d ON d.id = cd.double_id
         WHERE cd.competition_id = ? AND (d.player1_id = ? OR d.player2_id = ?)",
        [$cid, $pid, $pid]
    );

    db_execute(
        "DELETE cd FROM competition_double cd
         JOIN `double` d ON d.id = cd.double_id
         WHERE cd.competition_id = ?
           AND (d.player1_id = ? OR d.player2_id = ?)",
        [$cid, $pid, $pid]
    );

    // Reset partner's registration_competition back to pending so they no longer appear in
    // "confirmed without partner" and the admin can re-process them
    if ($comp) {
        foreach ($doubles as $double) {
            $partner_pid = ((int)$double['player1_id'] === $pid)
                ? (int)$double['player2_id']
                : (int)$double['player1_id'];
            $pl = db_fetch("SELECT name, firstname, pass_nr FROM player WHERE id=?", [$partner_pid]);
            if (!$pl) continue;
            $partner_reg = db_fetch(
                "SELECT r.id FROM registration r
                 WHERE r.tournament_id = ? AND r.status IN ('confirmed','pending')
                   AND ((r.pass_nr != '' AND r.pass_nr = ?)
                        OR (r.lastname = ? AND r.firstname = ?))
                 LIMIT 1",
                [$comp['tournament_id'], $pl['pass_nr'], $pl['name'], $pl['firstname']]
            );
            if ($partner_reg) {
                db_execute(
                    "UPDATE registration_competition SET status='pending'
                     WHERE registration_id=? AND competition_id=? AND status='confirmed'",
                    [$partner_reg['id'], $cid]
                );
                db_execute(
                    "UPDATE registration SET status='pending' WHERE id=? AND status='confirmed'",
                    [$partner_reg['id']]
                );
            }
        }
    }
}

function _reg_belongs_to_email(array $r, string $email): bool {
    if (!empty($r['email']) && strtolower($r['email']) === $email) return true;
    $player = db_fetch(
        "SELECT id FROM player WHERE name=? AND firstname=? AND LOWER(email)=?",
        [$r['lastname'], $r['firstname'], $email]
    );
    return $player !== null;
}

function _find_or_create_player(array $r): int {
    $player = null;
    if ($r['pass_nr']) {
        $player = db_fetch("SELECT id FROM player WHERE pass_nr=? AND pass_nr!=''", [$r['pass_nr']]);
    }
    if (!$player) {
        $player = db_fetch("SELECT id FROM player WHERE name=? AND firstname=?", [$r['lastname'], $r['firstname']]);
    }
    if (!$player) {
        return (int)db_insert(
            "INSERT INTO player (name, firstname, club, gender, pass_nr, skill, email) VALUES (?,?,?,?,?,?,?)",
            [$r['lastname'], $r['firstname'], $r['club'] ?? '', $r['gender'] ?? '',
             $r['pass_nr'] ?? '', $r['skill'] ?? 0, $r['email'] ?? '']
        );
    }
    return (int)$player['id'];
}

function _find_player(array $r): ?int {
    $player = null;
    if (!empty($r['pass_nr'])) {
        $player = db_fetch("SELECT id FROM player WHERE pass_nr=? AND pass_nr!=''", [$r['pass_nr']]);
    }
    if (!$player) {
        $player = db_fetch("SELECT id FROM player WHERE name=? AND firstname=?", [$r['lastname'], $r['firstname']]);
    }
    return $player ? (int)$player['id'] : null;
}

function _add_player_to_competition(int $pid, int $cid, float $skill = 0): bool {
    $c   = db_fetch("SELECT max_players FROM competition WHERE id=?", [$cid]);
    $max = (int)($c['max_players'] ?? 0);
    if ($max > 0) {
        $already = db_fetch("SELECT 1 FROM competition_player WHERE competition_id=? AND player_id=?", [$cid, $pid]);
        if (!$already) {
            $count = (int)db_fetch("SELECT COUNT(*) as n FROM competition_player WHERE competition_id=?", [$cid])['n'];
            if ($count >= $max) return false;
        }
    }
    try {
        db_execute(
            "INSERT IGNORE INTO competition_player (competition_id, player_id, created_at, skill) VALUES (?,?,NOW(),?)",
            [$cid, $pid, $skill]
        );
        return true;
    } catch (\Exception) {
        return false;
    }
}

function _update_player_skill_db(int $pid, string $sport, float $skill): void {
    if ($skill > 0 && $sport) {
        db_execute(
            "INSERT INTO player_skill (player_id, sport, skill, updated_at) VALUES (?,?,?,NOW())
             ON DUPLICATE KEY UPDATE skill=VALUES(skill), updated_at=NOW()",
            [$pid, $sport, $skill]
        );
    }
}

function _notify_reg_status(array $r): void {
    if (empty($r['email'])) return;
    $t     = db_fetch("SELECT name FROM tournament WHERE id=?", [$r['tournament_id']]);
    $comps = db_fetchall(
        "SELECT c.name, rc.status FROM registration_competition rc
         JOIN competition c ON c.id = rc.competition_id
         WHERE rc.registration_id=? AND rc.status IN ('confirmed','rejected')
         ORDER BY rc.status DESC, c.name",
        [(int)$r['id']]
    );
    $confirmed = []; $rejected = [];
    foreach ($comps as $c) {
        if ($c['status'] === 'confirmed') $confirmed[] = $c['name'];
        else $rejected[] = $c['name'];
    }
    $token = !empty($confirmed) ? make_manage_email_token($r['email']) : null;
    $name  = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
    send_reg_processed_mail($r['email'], $name, $t ? $t['name'] : '', $confirmed, $rejected, $token);
}

function _update_registration_status(int $rid): void {
    $pending = db_fetch(
        "SELECT COUNT(*) as n FROM registration_competition WHERE registration_id=? AND status='pending'", [$rid]
    )['n'];
    if ($pending == 0) {
        db_execute("UPDATE registration SET status='confirmed' WHERE id=?", [$rid]);
    }
}

function _process_withdraw(int $rid, int $tid): void {
    db_execute("UPDATE registration SET status='rejected' WHERE id=?", [$rid]);
    db_execute("UPDATE registration_competition SET status='rejected' WHERE registration_id=?", [$rid]);
    // Remove from competitions
    $r = db_fetch("SELECT * FROM registration WHERE id=?", [$rid]);
    if ($r) {
        $pid = _find_player($r);
        if ($pid) {
            $cids = array_column(
                db_fetchall("SELECT c.id FROM competition c WHERE c.tournament_id=?", [$tid]),
                'id'
            );
            foreach ($cids as $cid) {
                db_execute("DELETE FROM competition_player WHERE competition_id=? AND player_id=?", [$cid, $pid]);
                _remove_player_from_competition_doubles($pid, $cid);
            }
        }
    }
}

function _process_change_approve_all(int $rcr_id, int $rid): array {
    $r = db_fetch("SELECT * FROM registration WHERE id=?", [$rid]);
    if (!$r) return [];
    $entries = db_fetchall(
        "SELECT * FROM registration_change_competition WHERE change_request_id=? AND status='pending'", [$rcr_id]
    );
    $pid = _find_or_create_player($r);
    $full_comps = [];
    foreach ($entries as $entry) {
        if ($entry['action'] === 'add') {
            if (!_add_player_to_competition($pid, $entry['competition_id'], (float)$r['skill'])) {
                $comp = db_fetch("SELECT name FROM competition WHERE id=?", [$entry['competition_id']]);
                $full_comps[] = $comp ? $comp['name'] : "Bewerb #{$entry['competition_id']}";
            }
            _sync_registration_competition($rid, $entry['competition_id'], $entry['partner_name'] ?? '');
        } elseif ($entry['action'] === 'remove') {
            db_execute("DELETE FROM competition_player WHERE competition_id=? AND player_id=?",
                [$entry['competition_id'], $pid]);
            _remove_player_from_competition_doubles($pid, $entry['competition_id']);
        }
        db_execute("UPDATE registration_change_competition SET status='confirmed' WHERE change_request_id=? AND competition_id=?",
            [$rcr_id, $entry['competition_id']]);
    }
    return $full_comps;
}

function _notify_change_request_status(int $rcr_id): void {
    $rcr = db_fetch(
        "SELECT rcr.*, r.email, r.firstname, r.lastname, r.tournament_id
         FROM registration_change_request rcr
         JOIN registration r ON r.id = rcr.registration_id
         WHERE rcr.id=?",
        [$rcr_id]
    );
    if (!$rcr || empty($rcr['email'])) return;
    $t = db_fetch("SELECT name FROM tournament WHERE id=?", [$rcr['tournament_id']]);
    $comp_results = db_fetchall(
        "SELECT rcc.action, rcc.status, c.name
         FROM registration_change_competition rcc
         JOIN competition c ON c.id = rcc.competition_id
         WHERE rcc.change_request_id=?",
        [$rcr_id]
    );
    $name  = trim($rcr['firstname'] . ' ' . $rcr['lastname']);
    $token = make_manage_email_token($rcr['email']);
    send_change_request_processed_mail(
        $rcr['email'], $name, $t ? $t['name'] : '',
        $rcr['request_type'], $comp_results, $token
    );
}

function _maybe_close_change_request(int $rcr_id): void {
    $pending = db_fetch(
        "SELECT COUNT(*) as n FROM registration_change_competition WHERE change_request_id=? AND status='pending'",
        [$rcr_id]
    )['n'];
    if ($pending == 0) {
        db_execute("UPDATE registration_change_request SET status='confirmed' WHERE id=?", [$rcr_id]);
    }
}
