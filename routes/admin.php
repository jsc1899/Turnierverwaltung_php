<?php

require_once __DIR__ . '/../lib/mail.php';

function users(array $p): void {
    require_admin();
    $users = db_fetchall("SELECT * FROM user ORDER BY role, username");
    render('admin/users', ['page_title' => 'Benutzerverwaltung', 'users' => $users]);
}

function set_role(array $p): void {
    require_admin();
    csrf_verify();
    $uid  = (int)$p['id'];
    $role = post('role');
    if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
        flash('danger', 'Ungültige Rolle.');
        redirect('admin/users');
        return;
    }
    $u = db_fetch("SELECT email FROM user WHERE id=?", [$uid]);
    if ($u && $u['email'] === ADMIN_EMAIL && $role !== 'admin') {
        flash('danger', 'Der Haupt-Admin kann nicht heruntergestuft werden.');
        redirect('admin/users');
        return;
    }
    db_execute("UPDATE user SET role=? WHERE id=?", [$role, $uid]);
    flash('success', 'Rolle gespeichert.');
    redirect('admin/users');
}

function toggle_active(array $p): void {
    require_admin();
    csrf_verify();
    $uid = (int)$p['id'];
    $u   = db_fetch("SELECT email, confirmed FROM user WHERE id=?", [$uid]);
    if (!$u) {
        flash('danger', 'Benutzer nicht gefunden.');
        redirect('admin/users');
        return;
    }
    if ($u['email'] === ADMIN_EMAIL) {
        flash('danger', 'Der Haupt-Admin kann nicht deaktiviert werden.');
        redirect('admin/users');
        return;
    }
    $new = $u['confirmed'] ? 0 : 1;
    db_execute("UPDATE user SET confirmed=? WHERE id=?", [$new, $uid]);
    flash('success', $new ? 'Benutzer aktiviert.' : 'Benutzer deaktiviert.');
    redirect('admin/users');
}

function resend_confirm(array $p): void {
    require_admin();
    csrf_verify();
    $uid = (int)$p['id'];
    $u   = db_fetch("SELECT email, confirmed FROM user WHERE id=?", [$uid]);
    if (!$u) {
        flash('danger', 'Benutzer nicht gefunden.');
        redirect('admin/users');
        return;
    }
    if ($u['confirmed']) {
        flash('info', 'Benutzer ist bereits aktiviert.');
        redirect('admin/users');
        return;
    }
    $token = make_email_confirm_token($u['email']);
    send_confirm_mail($u['email'], $token);
    flash('success', 'Aktivierungsmail an ' . $u['email'] . ' gesendet.');
    redirect('admin/users');
}

function delete_user(array $p): void {
    require_admin();
    csrf_verify();
    $uid = (int)$p['id'];
    $u   = db_fetch("SELECT email FROM user WHERE id=?", [$uid]);
    if ($u && $u['email'] === ADMIN_EMAIL) {
        flash('danger', 'Der Haupt-Admin kann nicht gelöscht werden.');
        redirect('admin/users');
        return;
    }
    db_execute("DELETE FROM user WHERE id=?", [$uid]);
    flash('info', 'Benutzer gelöscht.');
    redirect('admin/users');
}

function design(array $p): void {
    require_admin();
    $active = get_setting('theme', 'default');
    render('admin/design', ['page_title' => 'Design', 'active' => $active]);
}

function save_design(array $p): void {
    require_admin();
    csrf_verify();
    $theme   = $_POST['theme'] ?? 'default';
    $allowed = ['default', 'dunkel', 'elegant', 'modern', 'klassisch'];
    if (in_array($theme, $allowed, true)) {
        set_setting('theme', $theme);
        flash('success', 'Design gespeichert.');
    }
    redirect('admin/design');
}
