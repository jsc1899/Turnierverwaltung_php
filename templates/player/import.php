<?php
$page_title   = $page_title   ?? 'Importieren';
$template_url  = $template_url ?? 'players/import/template';
$import_url    = $import_url   ?? 'players/import';
$info_html     = $info_html    ?? 'Ein Spieler wird übersprungen, wenn bereits ein Eintrag mit derselben '
                  . '<strong>Pass-Nr.</strong> oder demselben <strong>Nachname + Vorname</strong> existiert. '
                  . 'Bestehende Einträge werden nicht verändert.';
$has_created   = isset($created);
ob_start(); ?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('players') ?>">Spielerregister</a></li>
    <li class="breadcrumb-item active">Importieren</li>
  </ol>
</nav>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <h2 class="mb-4"><i class="bi bi-file-earmark-arrow-up me-2"></i><?= e($page_title) ?></h2>

    <?php if ($done ?? false): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Import abgeschlossen</h5>
        <div class="row g-3 text-center mb-3">
          <div class="col">
            <div class="fs-1 fw-bold text-success"><?= (int)($imported ?? 0) ?></div>
            <div class="text-muted small">importiert</div>
          </div>
          <div class="col">
            <div class="fs-1 fw-bold text-warning"><?= (int)($skipped ?? 0) ?></div>
            <div class="text-muted small">übersprungen (Duplikat)</div>
          </div>
          <?php if ($has_created): ?>
          <div class="col">
            <div class="fs-1 fw-bold text-primary"><?= (int)$created ?></div>
            <div class="text-muted small">neue Spieler angelegt</div>
          </div>
          <?php endif; ?>
          <?php if ($errors ?? []): ?>
          <div class="col">
            <div class="fs-1 fw-bold text-danger"><?= count($errors) ?></div>
            <div class="text-muted small">Fehler</div>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($errors ?? []): ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($errors as $err): ?>
          <li class="list-group-item list-group-item-danger py-1 small"><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <div class="d-flex gap-2 mt-3">
          <a href="<?= url('players') ?>" class="btn btn-primary"><i class="bi bi-person-lines-fill me-1"></i>Zum Register</a>
          <a href="<?= url($import_url) ?>" class="btn btn-outline-secondary">Weiteren Import</a>
        </div>
      </div>
    </div>
    <?php else: ?>

    <div class="card shadow-sm mb-4">
      <div class="card-header fw-semibold"><i class="bi bi-download me-1"></i>Vorlage</div>
      <div class="card-body d-flex align-items-center gap-3">
        <p class="mb-0 text-muted small flex-grow-1">
          Vorlage herunterladen, in Excel ausfüllen und als <code>.xlsx</code> oder <code>.csv</code> hochladen.
        </p>
        <a href="<?= url($template_url) ?>" class="btn btn-outline-success btn-sm text-nowrap">
          <i class="bi bi-file-earmark-excel me-1"></i>Vorlage (.xlsx)
        </a>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header fw-semibold"><i class="bi bi-upload me-1"></i>Datei hochladen</div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Excel- oder CSV-Datei <span class="text-danger">*</span></label>
            <input type="file" name="file" class="form-control" accept=".xlsx,.csv" required>
            <div class="form-text">Unterstützte Formate: <code>.xlsx</code> (Excel) und <code>.csv</code> (Semikolon- oder Komma-getrennt, UTF-8)</div>
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-file-earmark-arrow-up me-1"></i>Importieren
          </button>
        </form>
      </div>
    </div>

    <div class="card border-0 bg-light">
      <div class="card-body small text-muted">
        <strong>Hinweis:</strong> <?= $info_html ?>
      </div>
    </div>

    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
