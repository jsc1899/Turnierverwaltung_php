<?php
ob_start(); ?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h2 class="mb-4"><i class="bi bi-box-arrow-in-right"></i> Anmelden</h2>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">E-Mail</label>
        <input type="email" name="email" class="form-control" required autofocus
               value="<?= e(post('email')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Passwort</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Anmelden</button>
    </form>
    <div class="mt-3 text-center">
      <a href="<?= url('forgot-password') ?>">Passwort vergessen?</a> &nbsp;|&nbsp;
      <a href="<?= url('register') ?>">Registrieren</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
