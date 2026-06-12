<?php
require_once __DIR__ . '/../lib/mail.php';

function login(array $p): void {
    if (is_logged_in()) { redirect(''); }

    if (is_post()) {
        csrf_verify();
        if (!rate_limit_check('login', 10, 60)) {
            flash('danger', 'Zu viele Anmeldeversuche. Bitte warte eine Minute.');
            render('auth/login', ['page_title' => 'Anmelden']);
            return;
        }
        $email    = trim(post('email'));
        $password = post('password');
        $user     = db_fetch("SELECT * FROM user WHERE email = ?", [$email]);
        if ($user && $user['confirmed'] && password_verify($password, $user['password_hash'])) {
            login_user_session($user);
            flash('success', 'Willkommen, ' . e($user['username']) . '!');
            $next = get_param('next', '');
            redirect($next ?: '');
        }
        flash('danger', 'E-Mail oder Passwort falsch, oder E-Mail noch nicht bestätigt.');
    }
    render('auth/login', ['page_title' => 'Anmelden']);
}

function logout(array $p): void {
    logout_user_session();
    flash('success', 'Du wurdest abgemeldet.');
    redirect('');
}

function register(array $p): void {
    if (is_logged_in()) { redirect(''); }

    if (is_post()) {
        csrf_verify();
        if (!rate_limit_check('register', 5, 300)) {
            flash('danger', 'Zu viele Registrierungsversuche.');
            render('auth/register', ['page_title' => 'Registrieren']);
            return;
        }
        $username = trim(post('username'));
        $email    = trim(post('email'));
        $pw       = post('password');
        $pw2      = post('password2');

        if (strlen($username) < 3) {
            flash('danger', 'Benutzername muss mindestens 3 Zeichen haben.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Ungültige E-Mail-Adresse.');
        } elseif (strlen($pw) < 8) {
            flash('danger', 'Passwort muss mindestens 8 Zeichen haben.');
        } elseif ($pw !== $pw2) {
            flash('danger', 'Passwörter stimmen nicht überein.');
        } elseif (db_fetch("SELECT id FROM user WHERE email = ?", [$email])
               || db_fetch("SELECT id FROM user WHERE username = ?", [$username])) {
            flash('danger', 'Registrierung konnte nicht abgeschlossen werden. Bitte Angaben prüfen.');
        } else {
            $hash  = password_hash($pw, PASSWORD_ARGON2ID);
            $role  = ($email === ADMIN_EMAIL) ? 'admin' : 'viewer';
            $confirmed = ($email === ADMIN_EMAIL) ? 1 : 0;
            db_insert(
                "INSERT INTO user (username, email, password_hash, role, confirmed) VALUES (?,?,?,?,?)",
                [$username, $email, $hash, $role, $confirmed]
            );
            if ($confirmed) {
                flash('success', 'Registrierung erfolgreich. Du kannst dich jetzt anmelden.');
                redirect('login');
                return;
            }
            $token = make_email_confirm_token($email);
            send_confirm_mail($email, $token);
            flash('info', 'Registrierung erfolgreich! Bitte bestätige deine E-Mail-Adresse.');
            redirect('login');
            return;
        }
    }
    render('auth/register', ['page_title' => 'Registrieren']);
}

function confirm(array $p): void {
    $token = get_param('token');
    $email = verify_email_confirm_token($token);
    if (!$email) {
        flash('danger', 'Ungültiger oder abgelaufener Bestätigungslink.');
        redirect('login');
        return;
    }
    $rows = db_execute("UPDATE user SET confirmed = 1 WHERE email = ? AND confirmed = 0", [$email]);
    if ($rows > 0) {
        flash('success', 'E-Mail-Adresse bestätigt. Du kannst dich jetzt anmelden.');
    } else {
        flash('info', 'E-Mail-Adresse war bereits bestätigt.');
    }
    redirect('login');
}

function forgot_password(array $p): void {
    if (is_post()) {
        csrf_verify();
        if (!rate_limit_check('forgot_password', 5, 300)) {
            flash('info', 'Falls die E-Mail-Adresse bekannt ist, wurde ein Link gesendet.');
            redirect('login');
            return;
        }
        $email = trim(post('email'));
        $user  = db_fetch("SELECT * FROM user WHERE email = ? AND confirmed = 1", [$email]);
        if ($user) {
            $token = make_reset_token($email, $user['password_hash']);
            send_reset_mail($email, $token);
        }
        // Immer gleiche Antwort (kein User-Enumeration)
        flash('info', 'Falls die E-Mail-Adresse bekannt ist, wurde ein Link gesendet.');
        redirect('login');
        return;
    }
    render('auth/forgot_password', ['page_title' => 'Passwort vergessen']);
}

function reset_password(array $p): void {
    $token = get_param('token');
    [$email, $old_hash] = verify_reset_token($token);
    if (!$email) {
        flash('danger', 'Ungültiger oder abgelaufener Reset-Link.');
        redirect('login');
        return;
    }
    $user = db_fetch("SELECT * FROM user WHERE email = ?", [$email]);
    if (!$user || $user['password_hash'] !== $old_hash) {
        flash('danger', 'Dieser Link wurde bereits verwendet.');
        redirect('login');
        return;
    }

    if (is_post()) {
        csrf_verify();
        $pw  = post('password');
        $pw2 = post('password2');
        if (strlen($pw) < 8) {
            flash('danger', 'Passwort muss mindestens 8 Zeichen haben.');
        } elseif ($pw !== $pw2) {
            flash('danger', 'Passwörter stimmen nicht überein.');
        } else {
            $hash = password_hash($pw, PASSWORD_ARGON2ID);
            db_execute("UPDATE user SET password_hash = ? WHERE email = ?", [$hash, $email]);
            flash('success', 'Passwort erfolgreich geändert. Du kannst dich jetzt anmelden.');
            redirect('login');
            return;
        }
    }
    render('auth/reset_password', ['page_title' => 'Neues Passwort', 'token' => $token]);
}
