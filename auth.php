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

function require_login(): void {
    if (!is_logged_in()) {
        flash('warning', 'Bitte melde dich an, um Änderungen vorzunehmen.');
        redirect('login');
    }
}

function require_edit(): void {
    require_login();
    if (!can_edit()) {
        http_response_code(403);
        render('error', ['message' => 'Keine Berechtigung.', 'code' => 403]);
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        render('error', ['message' => 'Keine Berechtigung.', 'code' => 403]);
        exit;
    }
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
