<?php
$sport_icons  = ['tischtennis'=>'🏓','tennis'=>'🎾','fussball'=>'⚽','cornhole'=>'🫘'];
$sport_labels = ['tischtennis'=>'Tischtennis','tennis'=>'Tennis','fussball'=>'Fußball','cornhole'=>'Cornhole'];

ob_start(); ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
      <h2 class="mb-0"><i class="bi bi-trophy me-2"></i>Turniere</h2>
      <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
        <div class="btn-group btn-group-sm" id="status-filter">
          <button class="btn btn-outline-secondary active" data-filter="all">Alle</button>
          <button class="btn btn-outline-secondary" data-filter="open">Offen</button>
          <button class="btn btn-outline-secondary" data-filter="closed">Geschlossen</button>
          <button class="btn btn-outline-secondary" data-filter="done">Beendet</button>
        </div>
        <div class="btn-group btn-group-sm" id="sport-filter">
          <button class="btn btn-outline-secondary active" data-sport="all" title="Alle Sportarten">Alle</button>
          <button class="btn btn-outline-secondary" data-sport="tischtennis" title="Tischtennis"
                  style="font-size:1.1rem;padding:.1rem .4rem;line-height:1.4">🏓</button>
          <button class="btn btn-outline-secondary" data-sport="tennis" title="Tennis"
                  style="font-size:1.1rem;padding:.1rem .4rem;line-height:1.4">🎾</button>
          <button class="btn btn-outline-secondary" data-sport="fussball" title="Fußball"
                  style="font-size:1.1rem;padding:.1rem .4rem;line-height:1.4">⚽</button>
          <button class="btn btn-outline-secondary" data-sport="cornhole" title="Cornhole"
                  style="padding:.1rem .4rem">
            <img src="<?= url('static/cornhole_icon.svg') ?>" height="18" alt="Cornhole">
          </button>
        </div>
      </div>
    </div>

    <?php if ($tournaments): ?>
    <div class="row g-3" id="tournament-list">
      <?php foreach ($tournaments as $t):
        $t_status = $t['is_done'] ? 'done' : ($t['registrations_open'] ? 'open' : 'closed');
      ?>
      <div class="col-md-6 tournament-item"
           data-status="<?= e($t_status) ?>"
           data-sport="<?= e($t['sport'] ?? '') ?>">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-start gap-2 mb-1">
              <div class="flex-grow-1">
                <h5 class="card-title mb-1">
                  <?php if (!empty($t['sport']) && isset($sport_labels[$t['sport']])): ?>
                    <?php if ($t['sport'] === 'cornhole'): ?>
                    <img src="<?= url('static/cornhole_icon.svg') ?>" height="38"
                         class="me-1" style="vertical-align:middle" alt="Cornhole">
                    <?php else: ?>
                    <span class="me-1" style="font-size:2.5rem;vertical-align:middle;line-height:1"
                          title="<?= e($sport_labels[$t['sport']]) ?>"><?= $sport_icons[$t['sport']] ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?= e($t['name']) ?>
                </h5>
                <div class="text-muted small mb-2">
                  <?php if ($t['organizer']): ?>
                  <div><i class="bi bi-building me-1"></i><?= e($t['organizer']) ?></div>
                  <?php endif; ?>
                  <?php if ($t['event_date']): ?>
                  <div><i class="bi bi-calendar-event me-1"></i><?= fmtdate($t['event_date']) ?></div>
                  <?php endif; ?>
                  <div><i class="bi bi-diagram-3 me-1"></i>Max. <?= (int)($t['max_competitions'] ?: 1) ?> Bewerb<?= ($t['max_competitions'] ?: 1) > 1 ? 'e' : '' ?></div>
                  <?php if ($t['ausschreibung']): ?>
                  <div>
                    <a href="<?= url('tournament/' . $t['id'] . '/ausschreibung') ?>" target="_blank" class="text-decoration-none">
                      <i class="bi bi-file-earmark-pdf me-1 text-danger"></i>Ausschreibung
                    </a>
                  </div>
                  <?php endif; ?>
                  <?php if ($t['info_url']): ?>
                  <div>
                    <a href="<?= e($t['info_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                      <i class="bi bi-globe me-1"></i>Weitere Informationen
                    </a>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($t['banner_image']): ?>
              <img src="<?= url('uploads/' . $t['banner_image']) ?>"
                   style="height:120px;width:auto;max-width:160px;object-fit:contain;border-radius:4px;cursor:pointer;flex-shrink:0"
                   onclick="openImageModal('<?= url('uploads/' . $t['banner_image']) ?>')" alt="">
              <?php endif; ?>
            </div>
            <div class="mb-3 d-flex flex-wrap gap-1">
              <?php if ($t['is_done']): ?>
              <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                <i class="bi bi-flag-fill"></i> beendet
              </span>
              <?php elseif ($t['registrations_open']): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle">
                <i class="bi bi-unlock"></i> offen
              </span>
              <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                <i class="bi bi-lock"></i> geschlossen
              </span>
              <?php endif; ?>
              <?php if (can_edit() && !$t['is_public']): ?>
              <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                <i class="bi bi-eye-slash"></i> privat
              </span>
              <?php endif; ?>
            </div>
            <div class="mt-auto d-flex gap-2 pt-3">
              <a href="<?= url('tournament/' . $t['id']) ?>" class="btn btn-primary btn-sm flex-grow-1">
                <i class="bi bi-arrow-right-circle me-1"></i>Öffnen
              </a>
              <?php if (can_edit()): ?>
              <form method="post" action="<?= url('tournament/' . $t['id'] . '/delete') ?>"
                    onsubmit="return confirm('Turnier wirklich löschen?')">
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
    <div class="text-muted">Noch keine Turniere angelegt.</div>
    <?php endif; ?>
  </div>

  <?php if (can_edit()): ?>
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">
        <i class="bi bi-plus-circle me-1"></i>Neues Turnier
      </div>
      <div class="card-body">
        <form method="post" action="<?= url('tournament/new') ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Turniername <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="z.B. Stadtturnier 2026" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label">Veranstalter</label>
              <input type="text" name="organizer" class="form-control" placeholder="z.B. UTTV Musterstadt">
            </div>
            <div class="col-auto">
              <label class="form-label">Sportart</label>
              <?php include __DIR__ . '/../_sport_picker.php'; ?>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label">Termin</label>
              <input type="date" name="event_date" class="form-control">
            </div>
            <div class="col-auto">
              <label class="form-label">Max. Bewerbe</label>
              <select name="max_competitions" class="form-select">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <option value="<?= $i ?>"<?= $i === 1 ? ' selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label">Status</label>
              <select name="tournament_status" class="form-select">
                <option value="open" selected>offen</option>
                <option value="closed">geschlossen</option>
                <option value="done">beendet</option>
              </select>
            </div>
            <div class="col">
              <label class="form-label">Sichtbarkeit</label>
              <select name="is_public" class="form-select">
                <option value="1" selected>öffentlich</option>
                <option value="0">nur Admins/Editoren</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Ausschreibung (PDF)</label>
            <input type="file" name="ausschreibung_file" class="form-control" accept=".pdf">
          </div>
          <div class="mb-3">
            <label class="form-label">Turnierbild (JPG, PNG, GIF, WebP)</label>
            <input type="file" name="banner_file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-plus me-1"></i>Erstellen
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

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
(function() {
  var items = document.querySelectorAll('.tournament-item');
  var activeStatus = 'all', activeSport = 'all';
  function applyFilters() {
    items.forEach(function(item) {
      var okStatus = activeStatus === 'all' || item.dataset.status === activeStatus;
      var okSport  = activeSport  === 'all' || item.dataset.sport  === activeSport;
      item.style.display = (okStatus && okSport) ? '' : 'none';
    });
  }
  document.querySelectorAll('#status-filter button').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('#status-filter button').forEach(function(b) { b.classList.remove('active'); });
      btn.classList.add('active');
      activeStatus = btn.dataset.filter;
      applyFilters();
    });
  });
  document.querySelectorAll('#sport-filter button').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('#sport-filter button').forEach(function(b) { b.classList.remove('active'); });
      btn.classList.add('active');
      activeSport = btn.dataset.sport;
      applyFilters();
    });
  });
})();
function openImageModal(src) {
  document.getElementById('imageModalImg').src = src;
  new bootstrap.Modal(document.getElementById('imageModal')).show();
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
