<?php

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
