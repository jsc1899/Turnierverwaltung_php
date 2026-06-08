<?php ob_start(); $doubles_pairs = []; ?>
<div class="row justify-content-center">
  <div class="col-lg-8">
    <h2 class="mb-1"><i class="bi bi-person-gear me-2"></i>Meine Nennungen</h2>
    <p class="text-muted mb-4 small">
      <i class="bi bi-envelope me-1"></i><?= e($email) ?>
    </p>

    <?php if (empty($items)): ?>
    <div class="alert alert-info">
      Keine aktiven Nennungen für diese E-Mail-Adresse gefunden.
    </div>

    <?php else: foreach ($items as $item): $r = $item['reg']; ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header fw-semibold">
        <i class="bi bi-trophy me-1"></i><?= e($r['tname']) ?>
      </div>
      <div class="card-body">

        <div class="mb-3">
          <strong><?= e($r['lastname']) ?> <?= e($r['firstname']) ?></strong>
          <?php if ($r['club']): ?>
          <span class="text-muted ms-2"><?= e($r['club']) ?></span>
          <?php endif; ?>
        </div>

        <!-- Aktuell zugeteilte Bewerbe (aus competition_player) -->
        <?php if ($item['competitions']): ?>
        <div class="mb-3">
          <div class="text-muted small mb-1">Aktuell zugeteilte Bewerbe:</div>
          <?php foreach ($item['competitions'] as $c): ?>
          <span class="badge <?= !empty($c['is_doubles']) ? 'bg-info text-dark' : 'bg-secondary' ?> me-1">
            <?php if (!empty($c['is_doubles'])): ?><i class="bi bi-people me-1"></i><?php endif; ?>
            <?= e($c['name']) ?>
          </span>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="mb-3 text-muted small">Derzeit keinen Bewerben zugeordnet.</div>
        <?php endif; ?>

        <!-- Aktionen -->
        <?php if ($item['pending_req']): ?>
        <div class="alert alert-warning py-2 mb-0">
          <i class="bi bi-clock me-1"></i>
          Offener Antrag (<?= $item['pending_req']['request_type'] === 'withdraw' ? 'Rückzug' : 'Änderung' ?>) — wird vom Veranstalter bearbeitet.
        </div>

        <?php else: ?>
        <div class="d-flex gap-2 flex-wrap">

          <form method="post"
                action="<?= url('nennung/verwalten/' . urlencode($token) . '/withdraw/' . $r['id']) ?>"
                onsubmit="return confirm('Nennung wirklich zurückziehen?')">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm">
              <i class="bi bi-x-circle me-1"></i>Zurückziehen
            </button>
          </form>

          <?php if ($r['t_regs_open']): ?>
          <button class="btn btn-outline-primary btn-sm"
                  data-bs-toggle="collapse"
                  data-bs-target="#change-panel-<?= $r['id'] ?>">
            <i class="bi bi-arrow-repeat me-1"></i>Bewerbe ändern
          </button>
          <?php endif; ?>

        </div>

        <?php if ($r['t_regs_open']): ?>
        <div class="collapse mt-3" id="change-panel-<?= $r['id'] ?>">
          <div class="card card-body bg-light">
            <form method="post"
                  action="<?= url('nennung/verwalten/' . urlencode($token) . '/change/' . $r['id']) ?>">
              <?= csrf_field() ?>
              <p class="text-muted small mb-2">
                Wähle die gewünschten Bewerbe (max. <?= (int)($r['max_competitions'] ?: 1) ?>).
                Die Änderung muss vom Veranstalter bestätigt werden.
              </p>
              <?php
              $current_cids = array_column($item['competitions'], 'id');
              foreach ($item['all_comps'] as $ac):
              ?>
              <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox"
                       name="competition_ids[]" value="<?= $ac['id'] ?>"
                       id="chcomp-<?= $r['id'] ?>-<?= $ac['id'] ?>"
                       <?= in_array($ac['id'], $current_cids) ? 'checked' : '' ?>
                       <?= !$ac['registrations_open'] ? 'disabled' : '' ?>>
                <label class="form-check-label" for="chcomp-<?= $r['id'] ?>-<?= $ac['id'] ?>">
                  <?= e($ac['name']) ?>
                  <?php if (!empty($ac['is_doubles'])): ?>
                  <span class="badge bg-info text-dark ms-1">Doppelbewerb</span>
                  <?php endif; ?>
                  <?php if (!$ac['registrations_open']): ?>
                  <span class="badge bg-secondary ms-1" style="font-size:.7rem">geschlossen</span>
                  <?php endif; ?>
                </label>
                <?php if (!empty($ac['is_doubles']) && $ac['registrations_open']): ?>
                <?php
                $is_current  = in_array($ac['id'], $current_cids);
                $existing_pn = $ac['partner_name'] ?? '';
                $doubles_pairs[] = [$r['id'], $ac['id'], $is_current || $existing_pn !== ''];
                ?>
                <div id="mg-partner-<?= $r['id'] ?>-<?= $ac['id'] ?>"
                     class="ms-4 mt-1"
                     style="<?= ($is_current || $existing_pn !== '') ? '' : 'display:none' ?>">
                  <input type="text" name="partner_name[<?= $ac['id'] ?>]"
                         class="form-control form-control-sm"
                         placeholder="Gewünschter Doppelpartner (optional)"
                         maxlength="255"
                         value="<?= e($existing_pn) ?>">
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
              <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                  Änderung beantragen
                </button>
                <button type="button" class="btn btn-secondary btn-sm"
                        data-bs-toggle="collapse"
                        data-bs-target="#change-panel-<?= $r['id'] ?>">
                  Abbrechen
                </button>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <?php endif; // pending_req ?>

      </div>
    </div>
    <?php endforeach; endif; ?>

    <div class="text-center mt-3">
      <a href="<?= url('nennung/link') ?>" class="text-muted small">
        <i class="bi bi-envelope me-1"></i>Neuen Link anfordern
      </a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
if ($doubles_pairs) {
    $pairs_json = json_encode(array_values($doubles_pairs));
    $extra_js = '<script>
document.addEventListener("DOMContentLoaded", function() {
  var pairs = ' . $pairs_json . ';
  pairs.forEach(function(p) {
    var cb  = document.getElementById("chcomp-" + p[0] + "-" + p[1]);
    var box = document.getElementById("mg-partner-" + p[0] + "-" + p[1]);
    if (!cb || !box) return;
    cb.addEventListener("change", function() {
      box.style.display = cb.checked ? "block" : "none";
    });
  });
});
</script>';
}
require __DIR__ . '/../_base.php';
