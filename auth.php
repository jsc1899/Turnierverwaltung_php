<?php

function current_user(): ?array {
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $row = db_fetch("SELECT * FROM user WHERE id = ?", [$_SESSION['user_id']]);
            if ($row) $user = $row;
        }
    }
    return $user;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function can_edit(): bool {
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'editor'], true);
}

function is_admin(): bool {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

// Turnier-gebundene Bearbeitungsrechte: Admins dürfen alles; Editoren nur Turniere,
// denen sie zugeordnet sind (Tabelle tournament_editor). Viewer/Gäste nie.
function can_edit_tournament(?int $tid): bool {
    $u = current_user();
    if (!$u) return false;
    if ($u['role'] === 'admin') return true;
    if ($u['role'] !== 'editor' || !$tid) return false;
    return db_fetch(
        "SELECT 1 FROM tournament_editor WHERE tournament_id=? AND user_id=?",
        [$tid, $u['id']]
    ) !== null;
}

function require_tournament_edit(int $tid): void {
    if (!can_edit_tournament($tid)) { _audit_deny('Keine Berechtigung für dieses Turnier.'); }
    audit_log('ok');
}

// Turnier-ID eines Bewerbs (oder null, wenn nicht gefunden)
function competition_tid(int $cid): ?int {
    $c = db_fetch("SELECT tournament_id FROM competition WHERE id=?", [$cid]);
    return $c ? (int)$c['tournament_id'] : null;
}

function require_competition_edit(int $cid): void {
    $tid = competition_tid($cid);
    if ($tid === null) { http_response_code(404); exit; }
    require_tournament_edit($tid);
}

function require_match_edit(int $mid): void {
    $m = db_fetch("SELECT competition_id FROM `match` WHERE id=?", [$mid]);
    if (!$m) { http_response_code(404); exit; }
    require_competition_edit((int)$m['competition_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        flash('warning', 'Bitte melde dich an, um Änderungen vorzunehmen.');
        redirect('login');
    }
}

// Verweigerten Zugriff protokollieren und beenden: Gäste werden zum Login geleitet,
// angemeldete Benutzer mit fehlender Berechtigung erhalten 403.
function _audit_deny(string $message = 'Keine Berechtigung.'): void {
    audit_log('denied');
    if (!is_logged_in()) {
        flash('warning', 'Bitte melde dich an, um Änderungen vorzunehmen.');
        redirect('login');
    }
    http_response_code(403);
    render('error', ['message' => $message, 'code' => 403]);
    exit;
}

function require_edit(): void {
    if (!can_edit()) { _audit_deny(); }
    audit_log('ok');
}

function require_admin(): void {
    if (!is_admin()) { _audit_deny(); }
    audit_log('ok');
}

function login_user_session(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    db_execute("UPDATE user SET last_login=NOW() WHERE id=?", [$user['id']]);
}

function logout_user_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
