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
    <?php if ($t['registrations_open']): ?>
    <div class="mt-2">
      <a href="<?= url('tournament/' . $t['id'] . '/register') ?>" target="_blank"
         class="btn btn-success btn-sm">
        <i class="bi bi-box-arrow-in-right me-1"></i>Spielernennung für das Turnier abgeben
      </a>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($t['banner_image']): ?>
  <img src="<?= url('uploads/' . $t['banner_image']) ?>"
       style="height:150px;width:auto;max-width:200px;object-fit:contain;border-radius:6px;cursor:pointer;flex-shrink:0"
       onclick="openImageModal('<?= url('uploads/' . $t['banner_image']) ?>')" alt="">
  <?php endif; ?>
</div>

<!-- ═══ Registerkarten ═══════════════════════════════════════════════════════ -->
<?php
$pending_count = count($registrations ?? []);
$change_count  = count($change_requests ?? []);
$nennung_badge = $pending_count + $change_count;
?>
<ul class="nav nav-tabs mb-0" id="tournament-tabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-competitions-btn"
            data-bs-toggle="tab" data-bs-target="#tab-competitions" type="button" role="tab">
      <i class="bi bi-diagram-3 me-1"></i>Bewerbe
      <?php if ($comp_info): ?>
      <span class="badge bg-secondary ms-1"><?= count($comp_info) ?></span>
      <?php endif; ?>
    </button>
  </li>
  <?php if (can_edit()): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-registrations-btn"
            data-bs-toggle="tab" data-bs-target="#tab-registrations" type="button" role="tab">
      <i class="bi bi-person-lines-fill me-1"></i>Nennungen
      <?php if ($nennung_badge > 0): ?>
      <span class="badge bg-warning text-dark ms-1"><?= $nennung_badge ?></span>
      <?php endif; ?>
    </button>
  </li>
  <?php endif; ?>
  <?php if (can_edit()): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-settings-btn"
            data-bs-toggle="tab" data-bs-target="#tab-settings" type="button" role="tab">
      <i class="bi bi-gear me-1"></i>Einstellungen
    </button>
  </li>
  <?php endif; ?>
</ul>

<div class="tab-content border border-top-0 rounded-bottom mb-4">

  <!-- ── Tab: Bewerbe ──────────────────────────────────────────────────────── -->
  <div class="tab-pane fade show active p-3" id="tab-competitions" role="tabpanel">
    <div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
      <?php if (can_edit()): ?>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newCompetitionModal">
        <i class="bi bi-plus-circle me-1"></i>Neuer Bewerb
      </button>
      <?php endif; ?>
      <?php if (can_edit() && $comp_info): ?>
      <div class="btn-group btn-group-sm ms-auto">
        <span class="btn btn-sm btn-outline-secondary disabled pe-none" style="cursor:default">Spielerliste</span>
        <a href="<?= url('tournament/' . $t['id'] . '/players/pdf') ?>" class="btn btn-outline-danger" target="_blank" title="PDF">
          <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
        <a href="<?= url('tournament/' . $t['id'] . '/players/csv') ?>" class="btn btn-outline-success" title="CSV">
          <i class="bi bi-filetype-csv me-1"></i>CSV
        </a>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($comp_info): ?>
    <?php if (can_edit()): ?>
    <div class="d-flex align-items-center gap-2 mb-2">
      <button id="comp-sort-toggle" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrows-move me-1"></i>Reihenfolge ändern
      </button>
      <span id="comp-sort-saved" class="d-none badge bg-success"><i class="bi bi-check2 me-1"></i>Reihenfolge gespeichert</span>
    </div>
    <?php endif; ?>
    <div class="row g-3" id="comp-list" data-reorder-url="<?= url('tournament/'.$t['id'].'/competitions/reorder') ?>">
      <?php foreach ($comp_info as $ci): $c = $ci['comp']; ?>
      <div class="col-md-6" data-id="<?= $c['id'] ?>">
        <div class="card shadow-sm h-100">
          <?php if (can_edit()): ?>
          <div class="drag-handle d-flex justify-content-center align-items-center py-1 bg-light border-bottom"
               style="cursor:grab;border-radius:calc(var(--bs-card-border-radius) - 1px) calc(var(--bs-card-border-radius) - 1px) 0 0;user-select:none">
            <i class="bi bi-grip-horizontal text-muted"></i>
          </div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h6 class="card-title mb-1"><?= e($c['name']) ?></h6>
            <div class="text-muted small mb-2">
              <?php $picon = !empty($c['is_team']) ? 'shield-fill' : (!empty($c['is_doubles']) ? 'people-fill' : 'people'); ?>
              <?php $plabel = !empty($c['is_team']) ? 'Teams' : (!empty($c['is_doubles']) ? 'Doppel' : 'Spieler'); ?>
              <i class="bi bi-<?= $picon ?> me-1"></i><?= $ci['player_count'] ?><?= $c['max_players'] ? '/' . (int)$c['max_players'] : '' ?> <?= $plabel ?>
              &nbsp;·&nbsp;
              <?php if ($c['mode'] === 'ko_only'): ?>
              KO-Runde
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
                    data-confirm="Bewerb wirklich löschen?">
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
    <p class="text-muted mb-0">Noch keine Bewerbe angelegt.</p>
    <?php endif; ?>
  </div><!-- /tab-competitions -->

  <?php if (can_edit()): ?>
  <!-- ── Tab: Nennungen ────────────────────────────────────────────────────── -->
  <div class="tab-pane fade p-3" id="tab-registrations" role="tabpanel">
    <?php if ($registrations || $change_requests || $history): ?>
    <?php include __DIR__ . '/_registrations_panel.php'; ?>
    <?php else: ?>
    <p class="text-muted mb-0">Noch keine Nennungen vorhanden.</p>
    <?php endif; ?>
  </div><!-- /tab-registrations -->
  <?php endif; ?>

  <?php if (can_edit()): ?>
  <!-- ── Tab: Einstellungen ─────────────────────────────────────────────────── -->
  <div class="tab-pane fade p-3" id="tab-settings" role="tabpanel">
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
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check me-1"></i>Speichern</button>
      </div>
    </form>

    <hr class="my-4">

    <div>
      <h6 class="text-danger mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Turnier löschen</h6>
      <p class="text-muted small mb-2">Das Turnier und alle zugehörigen Bewerbe, Spieler und Ergebnisse werden unwiderruflich gelöscht.</p>
      <form method="post" action="<?= url('tournament/' . $t['id'] . '/delete') ?>"
            data-confirm="Turnier wirklich löschen?">
        <?= csrf_field() ?>
        <button class="btn btn-outline-danger btn-sm">
          <i class="bi bi-trash me-1"></i>Turnier löschen
        </button>
      </form>
    </div>
  </div><!-- /tab-settings -->
  <?php endif; ?>

</div><!-- /tab-content -->

<?php if (can_edit()): ?>
<!-- Neuer Bewerb Modal -->
<div class="modal fade" id="newCompetitionModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Neuer Bewerb</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?= url('tournament/' . $t['id'] . '/competition/new') ?>">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">Bewerbsname <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="z.B. Herren A" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Bewerbstyp</label>
              <select name="comp_type" class="form-select" id="new-comp-type-select" onchange="toggleTeamSize()">
                <option value="single" selected>Einzelbewerb</option>
                <option value="doubles">Doppelbewerb</option>
                <option value="team">Teambewerb</option>
              </select>
            </div>
            <div class="col-sm-6" id="new-team-size-wrap" style="display:none">
              <label class="form-label">Spiele pro Team</label>
              <input type="number" name="team_size" class="form-control" value="0" min="0" max="20">
            </div>
            <div class="col-sm-6">
              <label class="form-label">Spielmodus</label>
              <select name="mode" class="form-select" id="comp-mode-select" onchange="toggleGroupSettings()">
                <option value="groups_ko" selected>Gruppenphase + KO</option>
                <option value="ko_only">Nur KO</option>
                <option value="double_ko">Doppel-KO (mit Loser-Bracket)</option>
              </select>
            </div>
            <div class="col-sm-3" id="new-group-size-wrap">
              <label class="form-label">Gruppengröße</label>
              <select name="group_size" class="form-select">
                <option value="3">3 Teilnehmer</option>
                <option value="4" selected>4 Teilnehmer</option>
                <option value="5">5 Teilnehmer</option>
                <option value="6">6 Teilnehmer</option>
                <option value="7">7 Teilnehmer</option>
                <option value="8">8 Teilnehmer</option>
              </select>
            </div>
            <div class="col-sm-3" id="new-advance-wrap">
              <label class="form-label">KO-Aufstieg</label>
              <select name="advance_count" id="new-advance-count" class="form-select"
                      onchange="newToggleThirdPlace()">
                <option value="0">Nur Gruppenphase</option>
                <option value="1">Gruppenerste → KO</option>
                <option value="2" selected>Erste &amp; Zweite → KO</option>
              </select>
            </div>
            <div class="col-sm-6 d-flex align-items-end pb-1" id="new-third-place-wrap">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="third_place" id="new_third_place">
                <label class="form-check-label" for="new_third_place">Platz-3-Spiel</label>
              </div>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Setzungsreihenfolge</label>
              <select name="seeding_order" class="form-select">
                <option value="desc" selected>Höhere Stärke = stärker</option>
                <option value="asc">Niedrigere Stärke = stärker (Tennis)</option>
              </select>
            </div>
            <div class="col-sm-3 d-flex align-items-end pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="show_skill" id="new_show_skill">
                <label class="form-check-label" for="new_show_skill">Spielstärke anzeigen (Gruppe)</label>
              </div>
            </div>
            <div class="col-sm-3 d-flex align-items-end pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="show_seeding" id="new_show_seeding" checked>
                <label class="form-check-label" for="new_show_seeding">Setzungen anzeigen (KO)</label>
              </div>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Max. Teilnehmer <span class="text-muted small">(0 = unbegrenzt)</span></label>
              <input type="number" name="max_players" class="form-control" value="0" min="0">
            </div>
            <div class="col-sm-6 d-flex align-items-end pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="registrations_open" id="new_regs_open" checked>
                <label class="form-check-label" for="new_regs_open">Nennung offen</label>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2 justify-content-end mt-3">
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
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function() {
  var list = document.getElementById('comp-list');
  if (!list || !list.querySelector('.drag-handle')) return;
  var toggleBtn = document.getElementById('comp-sort-toggle');
  var active = false;
  list.querySelectorAll('.drag-handle').forEach(function(h) { h.classList.add('d-none'); });
  var sortable = Sortable.create(list, {
    handle: '.drag-handle',
    animation: 150,
    ghostClass: 'sortable-ghost',
    disabled: true,
    onEnd: function() {
      var ids = Array.from(list.querySelectorAll('[data-id]'))
                     .map(function(el) { return el.dataset.id; });
      var fd = new FormData();
      fd.append('csrf_token', document.querySelector('meta[name=csrf-token]')?.content || '');
      ids.forEach(function(id) { fd.append('ids[]', id); });
      fetch(list.dataset.reorderUrl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (d.ok) {
            var el = document.getElementById('comp-sort-saved');
            if (el) { el.classList.remove('d-none'); setTimeout(function(){ el.classList.add('d-none'); }, 1800); }
          }
        });
    }
  });
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function() {
      active = !active;
      sortable.option('disabled', !active);
      list.querySelectorAll('.drag-handle').forEach(function(h) {
        h.classList.toggle('d-none', !active);
      });
      toggleBtn.innerHTML = active
        ? '<i class="bi bi-check2 me-1"></i>Fertig'
        : '<i class="bi bi-arrows-move me-1"></i>Reihenfolge ändern';
      toggleBtn.classList.toggle('btn-outline-secondary', !active);
      toggleBtn.classList.toggle('btn-outline-success', active);
    });
  }
})();
function openImageModal(src) {
  document.getElementById('imageModalImg').src = src;
  new bootstrap.Modal(document.getElementById('imageModal')).show();
}
function toggleTeamSize() {
  var sel  = document.getElementById('new-comp-type-select');
  var wrap = document.getElementById('new-team-size-wrap');
  if (wrap) wrap.style.display = (sel && sel.value === 'team') ? '' : 'none';
}
function toggleGroupSettings() {
  var sel  = document.getElementById('comp-mode-select');
  var mode = sel ? sel.value : 'groups_ko';
  var isKo = (mode === 'ko_only' || mode === 'double_ko');
  var gsWrap  = document.getElementById('new-group-size-wrap');
  var advWrap = document.getElementById('new-advance-wrap');
  if (gsWrap)  gsWrap.style.display  = isKo ? 'none' : '';
  if (advWrap) advWrap.style.display = isKo ? 'none' : '';
  newToggleThirdPlace();
}
function newToggleThirdPlace() {
  var sel    = document.getElementById('comp-mode-select');
  var adv    = document.getElementById('new-advance-count');
  var wrap   = document.getElementById('new-third-place-wrap');
  if (!wrap) return;
  var mode   = sel  ? sel.value  : 'groups_ko';
  var advVal = adv  ? adv.value  : '2';
  var show   = (mode === 'ko_only' || mode === 'double_ko') || advVal !== '0';
  wrap.style.display = show ? '' : 'none';
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
