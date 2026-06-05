<?php
ob_start(); ?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h2 class="mb-4"><i class="bi bi-key"></i> Passwort vergessen</h2>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">E-Mail-Adresse</label>
        <input type="email" name="email" class="form-control" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary w-100">Reset-Link senden</button>
    </form>
    <div class="mt-3 text-center">
      <a href="<?= url('login') ?>">Zurück zum Login</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
