<?php
$role_labels = ['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Betrachter'];
ob_start(); ?>
<h2 class="mb-4"><i class="bi bi-people me-2"></i>Benutzerverwaltung</h2>
<div class="table-responsive">
  <table class="table table-hover align-middle" data-sortable>
    <thead class="table-light">
      <tr><th>Benutzer</th><th>E-Mail</th><th class="no-sort">Rolle</th><th>Registriert</th><th class="no-sort"></th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td class="fw-semibold"><?= e($u['username']) ?></td>
        <td class="text-muted small"><?= e($u['email']) ?></td>
        <td>
          <form method="post" action="<?= url('admin/user/' . $u['id'] . '/role') ?>" class="d-flex gap-2 align-items-center">
            <?= csrf_field() ?>
            <select name="role" class="form-select form-select-sm w-auto"
                    <?= $u['email'] === ADMIN_EMAIL ? 'disabled' : '' ?>>
              <?php foreach ($role_labels as $val => $label): ?>
              <option value="<?= $val ?>"<?= $u['role'] === $val ? ' selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($u['email'] !== ADMIN_EMAIL): ?>
            <button class="btn btn-primary btn-sm">Speichern</button>
            <?php endif; ?>
          </form>
        </td>
        <td class="text-muted small"><?= e($u['created_at'] ?? '') ?></td>
        <td>
          <?php if ($u['email'] !== ADMIN_EMAIL): ?>
          <form method="post" action="<?= url('admin/user/' . $u['id'] . '/delete') ?>"
                data-confirm="Benutzer wirklich löschen?">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
