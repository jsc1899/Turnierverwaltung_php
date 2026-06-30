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
    $allowed = ['default', 'dunkel', 'elegant', 'modern', 'klassisch', 'sport'];
    if (in_array($theme, $allowed, true)) {
        set_setting('theme', $theme);
        flash('success', 'Design gespeichert.');
    }
    redirect('admin/design');
}

// Aktivitätsprotokoll: privilegierte Aktionen (ändernde POSTs) und verweigerte Zugriffe.
function audit(array $p): void {
    require_admin();
    $status = in_array(get_param('status'), ['ok', 'denied'], true) ? get_param('status') : '';
    $q      = trim((string)get_param('q'));

    $cond = [];
    $args = [];
    if ($status !== '') { $cond[] = "status=?"; $args[] = $status; }
    if ($q !== '') {
        $cond[] = "(username LIKE ? OR role LIKE ? OR action LIKE ? OR target LIKE ? OR path LIKE ? OR ip LIKE ?)";
        $like = '%' . $q . '%';
        array_push($args, $like, $like, $like, $like, $like, $like);
    }
    $where = $cond ? ('WHERE ' . implode(' AND ', $cond)) : '';

    $per   = 100;
    $total = (int)db_fetch("SELECT COUNT(*) n FROM audit_log $where", $args)['n'];
    $pages = max(1, (int)ceil($total / $per));
    $page  = min(max(1, (int)get_param('page', 1)), $pages);
    $off   = ($page - 1) * $per;

    $rows = db_fetchall("SELECT * FROM audit_log $where ORDER BY id DESC LIMIT $per OFFSET $off", $args);
    render('admin/audit', [
        'page_title' => 'Aktivitätsprotokoll',
        'rows'       => $rows,
        'total'      => $total,
        'page'       => $page,
        'pages'      => $pages,
        'status'     => $status,
        'q'          => $q,
    ]);
}

function clear_audit(array $p): void {
    require_admin();
    csrf_verify();
    db_execute("DELETE FROM audit_log");
    flash('info', 'Protokoll geleert.');
    redirect('admin/audit');
}

function impressum(array $p): void {
    require_admin();
    render('admin/impressum', ['page_title' => 'Impressum', 'impressum' => get_setting('impressum', '')]);
}

function save_impressum(array $p): void {
    require_admin();
    csrf_verify();
    set_setting('impressum', trim((string)($_POST['impressum'] ?? '')));
    flash('success', 'Impressum gespeichert.');
    redirect('admin/impressum');
}
