<?php ob_start(); ?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url() ?>">Turniere</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tournament/' . $t['id']) ?>"><?= e($t['name']) ?></a></li>
    <li class="breadcrumb-item active">Nennung</li>
  </ol>
</nav>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <h2 class="mb-1"><i class="bi bi-pencil-square me-2"></i>Nennung</h2>
    <h5 class="text-muted mb-4"><?= e($t['name']) ?></h5>

    <?php if (!$t['registrations_open']): ?>
    <div class="alert alert-warning"><i class="bi bi-lock me-2"></i>Nennungen sind derzeit geschlossen.</div>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-person me-1"></i>Persönliche Daten</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nachname <span class="text-danger">*</span></label>
              <input type="text" name="lastname" class="form-control" required value="<?= e(post('lastname')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Vorname <span class="text-danger">*</span></label>
              <input type="text" name="firstname" class="form-control" required value="<?= e(post('firstname')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Verein</label>
              <input type="text" name="club" class="form-control" value="<?= e(post('club')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Geschlecht</label>
              <select name="gender" class="form-select">
                <option value="">—</option>
                <option value="m"<?= post('gender') === 'm' ? ' selected' : '' ?>>m</option>
                <option value="w"<?= post('gender') === 'w' ? ' selected' : '' ?>>w</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Pass-Nr. <span class="text-danger">*</span></label>
              <input type="text" name="pass_nr" class="form-control" required value="<?= e(post('pass_nr')) ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label">E-Mail <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required value="<?= e(post('email')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Spielstärke</label>
              <input type="number" name="skill" class="form-control"
                     step="<?= ($t['sport'] ?? '') === 'tennis' ? '0.1' : '1' ?>" min="0"
                     value="<?= e(post('skill')) ?>" placeholder="z.B. 700">
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-trophy me-1"></i>Bewerbe (max. <?= (int)($t['max_competitions'] ?: 1) ?>)</div>
        <div class="card-body">
          <?php if ($comps): ?>
          <?php foreach ($comps as $c): ?>
          <?php $full = $c['max_players'] && $comp_counts[$c['id']] >= $c['max_players']; ?>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="competition_ids[]"
                   value="<?= $c['id'] ?>" id="comp<?= $c['id'] ?>"
                   <?= in_array((string)$c['id'], (array)($_POST['competition_ids'] ?? [])) ? 'checked' : '' ?>
                   <?= $full ? 'disabled' : '' ?>>
            <label class="form-check-label" for="comp<?= $c['id'] ?>">
              <?= e($c['name']) ?>
              <?php if ($full): ?>
              <span class="badge bg-danger ms-1">voll</span>
              <?php elseif ($c['max_players']): ?>
              <span class="text-muted small ms-1">(<?= $comp_counts[$c['id']] ?>/<?= $c['max_players'] ?>)</span>
              <?php endif; ?>
            </label>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <p class="text-muted mb-0">Keine offenen Bewerbe verfügbar.</p>
          <?php endif; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-send me-1"></i>Nennung einreichen
      </button>
    </form>

    <div class="text-center mt-4 text-muted small">
      Bereits gemeldet?
      <a href="<?= url('nennung/link') ?>">Nennung verwalten (Bewerbe ändern / zurückziehen)</a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
