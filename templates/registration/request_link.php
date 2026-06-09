<?php
$sport_icons = ['tischtennis'=>'🏓','tennis'=>'🎾','fussball'=>'⚽','cornhole'=>'🫘'];
ob_start(); ?>

<div class="row g-4">
  <div class="col-12">
    <h2 class="mb-1"><i class="bi bi-person-gear me-2"></i>Nennung verwalten</h2>
    <p class="text-muted mb-4">Hier kannst du eine Nennung für offene Turniere abgeben oder eine bereits abgegebene Nennung bearbeiten.</p>
  </div>

  <!-- Offene Turniere -->
  <div class="col-lg-7">
    <h4 class="h5 mb-3"><i class="bi bi-calendar2-check me-2 text-success"></i>Nennung für Turnier abgeben</h4>
    <?php if ($open_tournaments): ?>
    <div class="d-flex flex-column gap-3">
      <?php foreach ($open_tournaments as $t): ?>
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-start gap-2">
            <?php if (!empty($t['sport']) && isset($sport_icons[$t['sport']])): ?>
            <span style="font-size:1.6rem;line-height:1.2;flex-shrink:0"><?= $sport_icons[$t['sport']] ?></span>
            <?php endif; ?>
            <div class="flex-grow-1">
              <h6 class="card-title mb-1 fw-semibold"><?= e($t['name']) ?></h6>
              <div class="text-muted small mb-2">
                <?php if ($t['organizer']): ?>
                <span class="me-2"><i class="bi bi-building me-1"></i><?= e($t['organizer']) ?></span>
                <?php endif; ?>
                <?php if ($t['event_date']): ?>
                <span><i class="bi bi-calendar-event me-1"></i><?= fmtdate($t['event_date']) ?></span>
                <?php endif; ?>
              </div>
              <?php if ($t['competitions']): ?>
              <div class="mb-2 d-flex flex-wrap gap-1">
                <?php foreach ($t['competitions'] as $c): ?>
                <span class="badge bg-light text-dark border" style="font-size:.78rem">
                  <?php if ($c['is_doubles']): ?><i class="bi bi-people me-1"></i><?php elseif ($c['is_team']): ?><i class="bi bi-shield me-1"></i><?php endif; ?>
                  <?= e($c['name']) ?>
                </span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('tournament/' . $t['id']) ?>" class="btn btn-outline-secondary btn-sm">
                  <i class="bi bi-eye me-1"></i>Turnier ansehen
                </a>
                <a href="<?= url('tournament/' . $t['id'] . '/register') ?>" class="btn btn-success btn-sm">
                  <i class="bi bi-pencil-square me-1"></i>Jetzt Nennung abgeben
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary">
      <i class="bi bi-info-circle me-1"></i>Derzeit sind keine Anmeldungen offen.
    </div>
    <?php endif; ?>
  </div>

  <!-- Bestehende Nennung verwalten -->
  <div class="col-lg-5">
    <h4 class="h5 mb-3"><i class="bi bi-envelope-open me-2 text-primary"></i>Bestehende Nennung bearbeiten</h4>
    <div class="card shadow-sm">
      <div class="card-body">
        <p class="text-muted small mb-3">
          Du hast bereits eine Nennung abgegeben und möchtest sie ändern — z.B. einzelne Bewerbe abmelden, hinzufügen oder die Nennung ganz zurückziehen? Gib deine E-Mail-Adresse ein und du erhältst einen persönlichen Verwaltungslink.
        </p>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">E-Mail-Adresse der Nennung</label>
            <input type="email" name="email" class="form-control" required autofocus
                   placeholder="deine@email.at">
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-send me-1"></i>Verwaltungslink senden
          </button>
        </form>
      </div>
    </div>
    <p class="text-muted small mt-2 px-1">
      <i class="bi bi-shield-check me-1"></i>Der Link ist 60 Minuten gültig. Es wird kein Account benötigt.
    </p>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
