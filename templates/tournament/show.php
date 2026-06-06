<?php
$sport_icons  = ['tischtennis'=>'🏓','tennis'=>'🎾','fussball'=>'⚽','cornhole'=>'🫘'];
$sport_labels = ['tischtennis'=>'Tischtennis','tennis'=>'Tennis','fussball'=>'Fußball','cornhole'=>'Cornhole'];
ob_start(); ?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url() ?>">Turniere</a></li>
    <li class="breadcrumb-item active"><?= e($t['name']) ?></li>
  </ol>
</nav>

<!-- Header -->
<div class="d-flex align-items-start gap-3 mb-4 flex-wrap">
  <div class="flex-grow-1">
    <h2 class="mb-1">
      <?php if (!empty($t['sport']) && isset($sport_labels[$t['sport']])): ?>
        <?php if ($t['sport'] === 'cornhole'): ?>
        <img src="<?= url('static/cornhole_icon.svg') ?>" height="52" class="me-2" style="vertical-align:middle" alt="Cornhole">
        <?php else: ?>
        <span class="me-2" style="font-size:3rem;vertical-align:middle;line-height:1"
              title="<?= e($sport_labels[$t['sport']]) ?>"><?= $sport_icons[$t['sport']] ?></span>
        <?php endif; ?>
      <?php endif; ?>
      <?= e($t['name']) ?>
    </h2>
    <div class="d-flex flex-wrap gap-3 text-muted small align-items-center">
      <?php if ($t['organizer']): ?><span><i class="bi bi-building me-1"></i><?= e($t['organizer']) ?></span><?php endif; ?>
      <?php if ($t['event_date']): ?><span><i class="bi bi-calendar-event me-1"></i><?= fmtdate($t['event_date']) ?></span><?php endif; ?>
      <?php if ($t['ausschreibung']): ?>
      <a href="<?= url('tournament/' . $t['id'] . '/ausschreibung') ?>" target="_blank" class="text-decoration-none">
        <i class="bi bi-file-earmark-pdf me-1 text-danger"></i>Ausschreibung
      </a>
      <?php endif; ?>
      <?php if ($t['info_url']): ?>
      <a href="<?= e($t['info_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
        <i class="bi bi-globe me-1"></i>Weitere Informationen
      </a>
      <?php endif; ?>
      <a href="<?= url('tournament/' . $t['id'] . '/aushang') ?>" target="_blank" class="text-decoration-none">
        <i class="bi bi-printer me-1"></i>Aushang
      </a>
      <?php if ($t['is_done']): ?>
      <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
        <i class="bi bi-flag-fill"></i> beendet
      </span>
      <?php endif; ?>
      <?php if (can_edit() && !$t['is_public']): ?>
      <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
        <i class="bi bi-eye-slash"></i> privat
      </span>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($t['banner_image']): ?>
  <img src="<?= url('uploads/' . $t['banner_image']) ?>"
       style="height:150px;width:auto;max-width:200px;object-fit:contain;border-radius:6px;cursor:pointer;flex-shrink:0"
       onclick="openImageModal('<?= url('uploads/' . $t['banner_image']) ?>')" alt="">
  <?php endif; ?>
  <?php if (can_edit()): ?>
  <button class="btn btn-outline-secondary btn-sm"
          data-bs-toggle="collapse" data-bs-target="#settings-panel">
    <i class="bi bi-gear me-1"></i>Einstellungen
  </button>
  <form method="post" action="<?= url('tournament/' . $t['id'] . '/delete') ?>"
        onsubmit="return confirm('Turnier wirklich löschen?')">
    <?= csrf_field() ?>
    <button class="btn btn-outline-danger btn-sm">
      <i class="bi bi-trash me-1"></i>Löschen
    </button>
  </form>
  <?php endif; ?>
</div>

<?php if (can_edit()): ?>
<!-- Settings Panel -->
<div class="collapse mb-4" id="settings-panel">
  <div class="card shadow-sm">
    <div class="card-header fw-semibold"><i class="bi bi-gear me-1"></i>Turnier-Einstellungen</div>
    <div class="card-body">
      <form method="post" action="<?= url('tournament/' . $t['id'] . '/settings') ?>"
            enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-6">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="<?= e($t['name']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Veranstalter</label>
          <input type="text" name="organizer" class="form-control" value="<?= e($t['organizer'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Sportart</label>
          <?php $sport_current = $t['sport'] ?? ''; include __DIR__ . '/../_sport_picker.php'; ?>
        </div>
        <div class="col-md-3">
          <label class="form-label">Termin</label>
          <input type="date" name="event_date" class="form-control" value="<?= e($t['event_date'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Max. Bewerbe je Spieler</label>
          <select name="max_competitions" class="form-select">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <option value="<?= $i ?>"<?= (int)($t['max_competitions'] ?? 1) === $i ? ' selected' : '' ?>><?= $i ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Weitere Informationen (URL)</label>
          <input type="url" name="info_url" class="form-control"
                 value="<?= e($t['info_url'] ?? '') ?>" placeholder="https://…">
        </div>
        <div class="col-md-6">
          <label class="form-label">Ausschreibung (PDF)</label>
          <?php if ($t['ausschreibung']): ?>
          <div class="d-flex align-items-center gap-2 mb-2">
            <a href="<?= url('tournament/' . $t['id'] . '/ausschreibung') ?>" target="_blank" class="small text-decoration-none">
              <i class="bi bi-file-earmark-pdf text-danger"></i> aktuell
            </a>
            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" name="remove_ausschreibung" id="remove_ausschreibung">
              <label class="form-check-label text-danger small" for="remove_ausschreibung">Entfernen</label>
            </div>
          </div>
          <?php endif; ?>
          <input type="file" name="ausschreibung_file" class="form-control" accept=".pdf">
        </div>
        <div class="col-md-6">
          <label class="form-label">Turnierbild (JPG, PNG, GIF, WebP)</label>
          <?php if ($t['banner_image']): ?>
          <div class="d-flex align-items-center gap-2 mb-2">
            <img src="<?= url('uploads/' . $t['banner_image']) ?>"
                 height="48" class="rounded border" style="object-fit:cover;cursor:pointer"
                 onclick="openImageModal('<?= url('uploads/' . $t['banner_image']) ?>')">
            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" name="remove_banner" id="remove_banner">
              <label class="form-check-label text-danger small" for="remove_banner">Bild entfernen</label>
            </div>
          </div>
          <?php endif; ?>
          <input type="file" name="banner_file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
        </div>
        <div class="col-md-3">
          <label class="form-label">Turnierstatus</label>
          <select name="tournament_status" class="form-select">
            <option value="open"<?= ($t['registrations_open'] && !$t['is_done']) ? ' selected' : '' ?>>offen</option>
            <option value="closed"<?= (!$t['registrations_open'] && !$t['is_done']) ? ' selected' : '' ?>>geschlossen</option>
            <option value="done"<?= $t['is_done'] ? ' selected' : '' ?>>beendet</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Sichtbarkeit</label>
          <select name="is_public" class="form-select">
            <option value="1"<?= ($t['is_public'] != 0) ? ' selected' : '' ?>>öffentlich</option>
            <option value="0"<?= ($t['is_public'] == 0) ? ' selected' : '' ?>>nur Admins/Editoren</option>
          </select>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="show_skill" id="show_skill" value="1"
                   <?= ($t['show_skill'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label" for="show_skill">Spielstärke in Tabellen anzeigen</label>
          </div>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check me-1"></i>Speichern</button>
          <button type="button" class="btn btn-secondary btn-sm"
                  data-bs-toggle="collapse" data-bs-target="#settings-panel">Abbrechen</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($t['registrations_open']): ?>
<div class="alert alert-info d-flex align-items-center gap-3 mb-4 py-2">
  <i class="bi bi-link-45deg fs-5"></i>
  <div class="flex-grow-1">
    <div class="fw-semibold small mb-1">Nennungs-Link für Spieler</div>
    <?php $reg_link = url('tournament/' . $t['id'] . '/register'); ?>
    <a id="reg-link" href="<?= e($reg_link) ?>" target="_blank" class="text-break small"><?= e($reg_link) ?></a>
  </div>
  <button class="btn btn-outline-secondary btn-sm" onclick="copyRegLink()">
    <i class="bi bi-clipboard"></i>
  </button>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-12">
    <div class="d-flex align-items-center mb-3">
      <h5 class="mb-0">Bewerbe</h5>
      <?php if (can_edit()): ?>
      <button class="btn btn-primary btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#newCompetitionModal">
        <i class="bi bi-plus-circle me-1"></i>Neuer Bewerb
      </button>
      <?php endif; ?>
      <?php if (can_edit() && $comp_info): ?>
      <div class="btn-group btn-group-sm ms-auto">
        <span class="btn btn-sm btn-outline-secondary disabled pe-none" style="cursor:default">Spielerliste</span>
        <a href="<?= url('tournament/' . $t['id'] . '/players/pdf') ?>" class="btn btn-outline-danger" target="_blank" title="PDF exportieren">
          <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
        <a href="<?= url('tournament/' . $t['id'] . '/players/csv') ?>" class="btn btn-outline-success" title="CSV exportieren">
          <i class="bi bi-filetype-csv me-1"></i>CSV
        </a>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($comp_info): ?>
    <div class="row g-3 mb-4">
      <?php foreach ($comp_info as $ci): $c = $ci['comp']; ?>
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <h6 class="card-title mb-1"><?= e($c['name']) ?></h6>
            <div class="text-muted small mb-2">
              <i class="bi bi-people me-1"></i><?= $ci['player_count'] ?><?= $c['max_players'] ? '/' . $c['max_players'] : '' ?> Spieler
              &nbsp;·&nbsp;
              <?php if ($c['mode'] === 'ko_only'): ?>
              Nur KO
              <?php elseif ($c['mode'] === 'double_ko'): ?>
              Doppel-KO
              <?php else: ?>
              Gruppen à <?= $c['group_size'] ?>
              &nbsp;·&nbsp;
              <?php if ($c['advance_count'] == 0): ?>Nur Gruppen
              <?php elseif ($c['advance_count'] == 1): ?>Top 1 → KO
              <?php else: ?>Top 2 → KO<?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2 mb-3">
              <?php $phase_labels = ['setup'=>'Einrichtung','group'=>'Gruppenphase','ko'=>'KO-Phase','done'=>'Beendet']; ?>
              <?php $phase_colors = ['setup'=>'bg-secondary','group'=>'bg-warning text-dark','ko'=>'bg-info text-dark','done'=>'bg-success']; ?>
              <span class="badge <?= $phase_colors[$c['phase']] ?? 'bg-secondary' ?>">
                <?= $phase_labels[$c['phase']] ?? e($c['phase']) ?>
              </span>
              <?php if ($t['registrations_open'] && $c['registrations_open']): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle">
                <i class="bi bi-unlock"></i> offen
              </span>
              <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                <i class="bi bi-lock"></i> geschlossen
              </span>
              <?php endif; ?>
            </div>
            <div class="mt-auto d-flex gap-2">
              <a href="<?= url('competition/' . $c['id']) ?>" class="btn btn-primary btn-sm flex-grow-1">
                <i class="bi bi-arrow-right-circle me-1"></i>Öffnen
              </a>
              <?php if (can_edit()): ?>
              <form method="post" action="<?= url('competition/' . $c['id'] . '/delete') ?>"
                    onsubmit="return confirm('Bewerb wirklich löschen?')">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-muted">Noch keine Bewerbe angelegt.</p>
    <?php endif; ?>

    <?php if (can_edit() && ($registrations || $change_requests || $history)): ?>
    <?php include __DIR__ . '/_registrations_panel.php'; ?>
    <?php endif; ?>
  </div>
</div>

<?php if (can_edit()): ?>
<!-- Neuer Bewerb Modal -->
<div class="modal fade" id="newCompetitionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Neuer Bewerb</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?= url('tournament/' . $t['id'] . '/competition/new') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" placeholder="z.B. Herren A" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Modus</label>
            <select name="mode" class="form-select" id="comp-mode-select" onchange="toggleGroupSettings()">
              <option value="groups_ko" selected>Gruppenphase + KO</option>
              <option value="ko_only">Nur KO-Runde</option>
              <option value="double_ko">Doppel-KO (mit Loser-Bracket)</option>
            </select>
          </div>
          <div id="group-settings">
            <div class="mb-3">
              <label class="form-label">Gruppengröße</label>
              <select name="group_size" class="form-select">
                <option value="3">3 Spieler</option>
                <option value="4" selected>4 Spieler</option>
                <option value="5">5 Spieler</option>
                <option value="6">6 Spieler</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">KO-Aufstieg</label>
              <select name="advance_count" class="form-select">
                <option value="0">Nur Gruppenphase</option>
                <option value="1" selected>Gruppenerste → KO</option>
                <option value="2">Erste &amp; Zweite → KO</option>
              </select>
            </div>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-plus me-1"></i>Erstellen
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-transparent border-0">
      <div class="modal-body text-center p-0">
        <img id="imageModalImg" src="" class="img-fluid rounded" alt="">
      </div>
    </div>
  </div>
</div>

<?php
$extra_js = <<<'JS'
<script>
function openImageModal(src) {
  document.getElementById('imageModalImg').src = src;
  new bootstrap.Modal(document.getElementById('imageModal')).show();
}
function copyRegLink() {
  var link = document.getElementById('reg-link').href;
  navigator.clipboard.writeText(link).then(function() {
    var btn = event.target.closest('button');
    btn.innerHTML = '<i class="bi bi-check"></i>';
    setTimeout(function() { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1500);
  });
}
function toggleGroupSettings() {
  var sel = document.getElementById('comp-mode-select');
  var grp = document.getElementById('group-settings');
  if (grp) grp.style.display = (sel && (sel.value === 'ko_only' || sel.value === 'double_ko')) ? 'none' : '';
}
function setSport(btn) {
  var picker = btn.closest('.sport-picker');
  picker.querySelectorAll('.sport-opt').forEach(function(b) {
    b.classList.remove('btn-primary');
    b.classList.add('btn-outline-secondary');
  });
  btn.classList.remove('btn-outline-secondary');
  btn.classList.add('btn-primary');
  picker.querySelector('.sport-val').value = btn.dataset.val;
}
</script>
JS;
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
