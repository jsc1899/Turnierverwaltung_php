<?php
ob_start(); ?>
<h2 class="mb-1"><i class="bi bi-file-text me-2"></i>Impressum</h2>
<p class="text-muted mb-4">Dieser Text wird ganz unten auf jeder Seite angezeigt. Leer lassen, um keine Impressumzeile anzuzeigen.</p>

<form method="post" action="<?= url('admin/impressum') ?>" style="max-width:680px">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label" for="impressum-text">Impressumtext</label>
    <textarea name="impressum" id="impressum-text" class="form-control" rows="6"
              placeholder="z.B. Verein XY · Musterstraße 1, 1234 Musterstadt · kontakt@example.com"><?= e($impressum) ?></textarea>
    <div class="form-text">Zeilenumbrüche werden übernommen.</div>
  </div>
  <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Speichern</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
