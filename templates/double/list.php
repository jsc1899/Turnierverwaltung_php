<?php
ob_start(); ?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url() ?>">Turniere</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tournament/' . $t['id']) ?>"><?= e($t['name']) ?></a></li>
    <li class="breadcrumb-item active">Doppel</li>
  </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-4">
  <h2 class="mb-0"><i class="bi bi-people-fill me-2"></i>Doppel</h2>
  <span class="text-muted small"><?= e($t['name']) ?></span>
</div>

<?php if ($doubles): ?>
<div class="card shadow-sm mb-4">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Spieler 1</th>
            <th>Spieler 2</th>
            <th class="text-center">Stärke</th>
            <th class="no-sort"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($doubles as $d): ?>
        <tr>
          <td class="fw-semibold"><?= e($d['name']) ?></td>
          <td class="text-muted small">
            <?= e($d['p1name']) ?>
            <?php if ($d['p1club']): ?><span class="text-muted"> (<?= e($d['p1club']) ?>)</span><?php endif; ?>
          </td>
          <td class="text-muted small">
            <?= e($d['p2name']) ?>
            <?php if ($d['p2club']): ?><span class="text-muted"> (<?= e($d['p2club']) ?>)</span><?php endif; ?>
          </td>
          <td class="text-center">
            <?php if ($d['skill']): ?>
            <span class="badge bg-secondary"><?= (int)$d['skill'] ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <form method="post" action="<?= url('tournament/'.$t['id'].'/doppel/'.$d['id'].'/loeschen') ?>"
                  onsubmit="return confirm('Doppel „<?= e($d['name']) ?>" wirklich löschen?')">
              <?= csrf_field() ?>
              <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Löschen">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
<p class="text-muted">Noch keine Doppel gebildet.</p>
<?php endif; ?>

<?php if ($players && count($players) >= 2): ?>
<div class="card shadow-sm">
  <div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Neues Doppel bilden</h5></div>
  <div class="card-body">
    <form method="post" action="<?= url('tournament/'.$t['id'].'/doppel/neu') ?>" class="row g-3 align-items-end">
      <?= csrf_field() ?>
      <div class="col-sm-4">
        <label class="form-label">Spieler 1</label>
        <select name="player1_id" class="form-select form-select-sm" required>
          <option value="">— auswählen —</option>
          <?php foreach ($players as $pl): ?>
          <option value="<?= $pl['id'] ?>"><?= e($pl['fullname']) ?><?php if ($pl['club']): ?> (<?= e($pl['club']) ?>)<?php endif; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4">
        <label class="form-label">Spieler 2</label>
        <select name="player2_id" class="form-select form-select-sm" required>
          <option value="">— auswählen —</option>
          <?php foreach ($players as $pl): ?>
          <option value="<?= $pl['id'] ?>"><?= e($pl['fullname']) ?><?php if ($pl['club']): ?> (<?= e($pl['club']) ?>)<?php endif; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label">Stärke <span class="text-muted small">(opt.)</span></label>
        <input type="number" name="skill" class="form-control form-control-sm" min="0" step="0.1" placeholder="auto">
      </div>
      <div class="col-sm-2">
        <button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus me-1"></i>Erstellen</button>
      </div>
    </form>
  </div>
</div>
<?php else: ?>
<div class="alert alert-info">
  <i class="bi bi-info-circle me-2"></i>
  Um Doppel bilden zu können, müssen zuerst mindestens 2 Spieler einem Bewerb dieses Turniers zugeordnet sein.
  <a href="<?= url('tournament/'.$t['id']) ?>" class="alert-link">Zum Turnier</a>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
