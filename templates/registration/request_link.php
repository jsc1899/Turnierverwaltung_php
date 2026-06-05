<?php ob_start(); ?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h2 class="mb-2"><i class="bi bi-envelope-open me-2"></i>Nennungen verwalten</h2>
    <p class="text-muted mb-4">
      Gib deine E-Mail-Adresse ein — du erhältst einen Link zum Verwalten deiner Nennungen
      (Bewerbe ändern oder zurückziehen).
    </p>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">E-Mail-Adresse</label>
        <input type="email" name="email" class="form-control" required autofocus
               placeholder="deine@email.at">
      </div>
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-send me-1"></i>Verwaltungslink senden
      </button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
