<?php
ob_start(); ?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h2 class="mb-4"><i class="bi bi-person-plus"></i> Registrieren</h2>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Benutzername</label>
        <input type="text" name="username" class="form-control" required autofocus
               value="<?= e(post('username')) ?>" minlength="3">
      </div>
      <div class="mb-3">
        <label class="form-label">E-Mail</label>
        <input type="email" name="email" class="form-control" required
               value="<?= e(post('email')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Passwort</label>
        <input type="password" name="password" class="form-control" required minlength="8">
      </div>
      <div class="mb-3">
        <label class="form-label">Passwort wiederholen</label>
        <input type="password" name="password2" class="form-control" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary w-100">Registrieren</button>
    </form>
    <div class="mt-3 text-center">
      <a href="<?= url('login') ?>">Bereits registriert? Anmelden</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
