<?php
$sport_icons = ['tischtennis'=>'🏓','tennis'=>'🎾','fussball'=>'⚽','cornhole'=>null];
ob_start(); ?>
<h2 class="mb-1"><i class="bi bi-person-lines-fill me-2"></i>Spielerregister</h2>
<p class="text-muted small mb-4">Stammdaten und Spielstärken aller registrierten Spieler.</p>

<div class="row g-4">
  <div class="col-xl-9">
    <?php if ($players): ?>
    <div class="d-flex align-items-center mb-2">
      <span class="text-muted small"><?= count($players) ?> Einträge</span>
      <div class="btn-group btn-group-sm ms-auto">
        <a href="<?= url('players/pdf') ?>" class="btn btn-outline-danger" target="_blank">
          <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
        <a href="<?= url('players/csv') ?>" class="btn btn-outline-success">
          <i class="bi bi-filetype-csv me-1"></i>CSV
        </a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Nachname</th><th>Vorname</th><th class="text-center">G</th>
            <th>Verein</th><th>Pass-Nr.</th><th>E-Mail</th><th>Spielstärke</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($players as $p): $ps = $player_skills[$p['id']] ?? []; ?>
          <tr>
            <td class="fw-semibold"><?= e($p['name']) ?></td>
            <td><?= e($p['firstname'] ?? '') ?></td>
            <td class="text-center">
              <?php if ($p['gender']): ?>
              <span class="badge <?= $p['gender'] === 'm' ? 'bg-primary' : 'bg-danger' ?>"><?= e($p['gender']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e($p['club'] ?? '') ?></td>
            <td><span class="text-muted"><?= e($p['pass_nr'] ?? '') ?></span></td>
            <td><span class="text-muted small"><?= e($p['email'] ?? '') ?></span></td>
            <td>
              <?php foreach ($sports_list as [$sk, $sl, $se]):
                if (!isset($ps[$sk])) continue; ?>
              <span class="badge bg-secondary me-1" title="<?= e($sl) ?>">
                <?php if ($se): echo $se; else: ?>
                <img src="<?= url('static/cornhole_icon.svg') ?>" height="12" alt="Cornhole">
                <?php endif; ?>
                <?= $ps[$sk] ?>
              </span>
              <?php endforeach; ?>
            </td>
            <td class="text-end">
              <?php if (can_edit()): ?>
              <button class="btn btn-outline-secondary btn-sm"
                      data-bs-toggle="modal" data-bs-target="#editModal<?= $p['id'] ?>">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" action="<?= url('player/' . $p['id'] . '/delete') ?>"
                    class="d-inline ms-1"
                    onsubmit="return confirm('Spieler <?= e($p['firstname'].' '.$p['name']) ?> wirklich löschen?')">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php if (can_edit()): ?>
          <div class="modal fade" id="editModal<?= $p['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <form method="post" action="<?= url('player/' . $p['id'] . '/edit') ?>">
                  <?= csrf_field() ?>
                  <div class="modal-header">
                    <h5 class="modal-title">Spieler bearbeiten — <?= e($p['name'].' '.($p['firstname']??'')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body row g-3">
                    <div class="col-6">
                      <label class="form-label">Nachname <span class="text-danger">*</span></label>
                      <input type="text" name="name" class="form-control" value="<?= e($p['name']) ?>" required>
                    </div>
                    <div class="col-6">
                      <label class="form-label">Vorname <span class="text-danger">*</span></label>
                      <input type="text" name="firstname" class="form-control" value="<?= e($p['firstname']??'') ?>" required>
                    </div>
                    <div class="col-6">
                      <label class="form-label">Verein</label>
                      <input type="text" name="club" class="form-control" value="<?= e($p['club']??'') ?>">
                    </div>
                    <div class="col-3">
                      <label class="form-label">Geschlecht</label>
                      <select name="gender" class="form-select">
                        <option value="">—</option>
                        <option value="m"<?= ($p['gender']??'')==='m'?' selected':'' ?>>m</option>
                        <option value="w"<?= ($p['gender']??'')==='w'?' selected':'' ?>>w</option>
                      </select>
                    </div>
                    <div class="col-3">
                      <label class="form-label">Pass-Nr. <span class="text-danger">*</span></label>
                      <input type="text" name="pass_nr" class="form-control" value="<?= e($p['pass_nr']??'') ?>" required>
                    </div>
                    <div class="col-12">
                      <label class="form-label">E-Mail <span class="text-danger">*</span></label>
                      <input type="email" name="email" class="form-control" value="<?= e($p['email']??'') ?>" required>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Spielstärken</label>
                      <div class="row g-2">
                        <?php foreach ($sports_list as [$sk, $sl, $se]): ?>
                        <div class="col-3">
                          <label class="form-label small"><?= $se ? $se : '<img src="'.url('static/cornhole_icon.svg').'" height="14">' ?> <?= e($sl) ?></label>
                          <input type="number" step="0.5" min="0" name="skill_<?= $sk ?>"
                                 class="form-control form-control-sm"
                                 value="<?= $ps[$sk] ?? '' ?>" placeholder="0">
                        </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted">Noch keine Spieler im Register.</p>
    <?php endif; ?>
  </div>

  <?php if (can_edit()): ?>
  <div class="col-xl-3">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="bi bi-person-plus me-1"></i>Neuer Spieler</div>
      <div class="card-body">
        <form method="post" action="<?= url('player/new') ?>">
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Nachname <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control form-control-sm" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Vorname <span class="text-danger">*</span></label>
            <input type="text" name="firstname" class="form-control form-control-sm" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Verein</label>
            <input type="text" name="club" class="form-control form-control-sm">
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Geschlecht</label>
              <select name="gender" class="form-select form-select-sm">
                <option value="">—</option>
                <option value="m">m</option>
                <option value="w">w</option>
              </select>
            </div>
            <div class="col">
              <label class="form-label">Pass-Nr. <span class="text-danger">*</span></label>
              <input type="text" name="pass_nr" class="form-control form-control-sm" required>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">E-Mail <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control form-control-sm" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Spielstärken</label>
            <?php foreach ($sports_list as [$sk, $sl, $se]): ?>
            <div class="input-group input-group-sm mb-1">
              <span class="input-group-text"><?= $se ?: '<img src="'.url('static/cornhole_icon.svg').'" height="12">' ?></span>
              <input type="number" step="0.5" min="0" name="skill_<?= $sk ?>" class="form-control" placeholder="<?= e($sl) ?>">
            </div>
            <?php endforeach; ?>
          </div>
          <button class="btn btn-primary w-100 btn-sm">Hinzufügen</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
