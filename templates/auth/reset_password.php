<?php
ob_start(); ?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h2 class="mb-4"><i class="bi bi-shield-lock"></i> Neues Passwort</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="mb-3">
        <label class="form-label">Neues Passwort</label>
        <input type="password" name="password" class="form-control" required minlength="8" autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Passwort wiederholen</label>
        <input type="password" name="password2" class="form-control" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary w-100">Passwort ändern</button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
