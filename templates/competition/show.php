<?php
$phase_labels = ['setup'=>'Einrichtung','group'=>'Gruppenphase','ko'=>'KO-Phase','done'=>'Beendet'];
$phase_colors = ['setup'=>'bg-secondary','group'=>'bg-warning text-dark','ko'=>'bg-info text-dark','done'=>'bg-success'];
ob_start(); ?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url() ?>">Turniere</a></li>
    <?php if ($t): ?>
    <li class="breadcrumb-item"><a href="<?= url('tournament/' . $t['id']) ?>"><?= e($t['name']) ?></a></li>
    <?php endif; ?>
    <li class="breadcrumb-item active"><?= e($c['name']) ?></li>
  </ol>
</nav>

<!-- Header -->
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <h2 class="mb-0"><i class="bi bi-diagram-3 me-2"></i><?= e($c['name']) ?></h2>
  <span class="badge fs-6 <?= $phase_colors[$c['phase']] ?? 'bg-secondary' ?>">
    <?= $phase_labels[$c['phase']] ?? e($c['phase']) ?>
  </span>
  <?php if ($t && $t['registrations_open'] && $c['registrations_open']): ?>
  <span class="badge bg-success-subtle text-success border border-success-subtle fs-6">
    <i class="bi bi-unlock"></i> offen
  </span>
  <?php else: ?>
  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-6">
    <i class="bi bi-lock"></i> geschlossen
  </span>
  <?php endif; ?>

  <?php if (can_edit()): ?>
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <?php if (in_array($c['phase'], ['group','ko'], true)): ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/settings') ?>?action=done">
      <?= csrf_field() ?><input type="hidden" name="mark_done" value="1">
      <button class="btn btn-success btn-sm"><i class="bi bi-check-circle me-1"></i>Als beendet markieren</button>
    </form>
    <?php endif; ?>
    <?php if ($c['phase'] === 'done'): ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/settings') ?>?action=reopen">
      <?= csrf_field() ?><input type="hidden" name="reopen" value="1">
      <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Wieder öffnen</button>
    </form>
    <?php endif; ?>
    <?php if ($c['phase'] === 'group'): ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/reset/groups') ?>"
          onsubmit="return confirm('Alle Gruppenspiel-Ergebnisse löschen?')">
      <?= csrf_field() ?>
      <button class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Gruppe zurücksetzen</button>
    </form>
    <?php endif; ?>
    <?php if ($c['phase'] === 'ko'): ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/reset/ko') ?>"
          onsubmit="return confirm('KO-Phase zurücksetzen?')">
      <?= csrf_field() ?>
      <button class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>KO zurücksetzen</button>
    </form>
    <?php endif; ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/delete') ?>"
          onsubmit="return confirm('Bewerb wirklich löschen?')">
      <?= csrf_field() ?>
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Löschen</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php if ($places): ?>
<!-- Endplatzierung -->
<div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#fff9db,#fff);">
  <div class="card-body">
    <h5 class="card-title mb-3"><i class="bi bi-trophy-fill text-warning me-2"></i>Endplatzierung</h5>
    <div class="d-flex flex-wrap gap-3">
      <?php foreach ($places as $pl): ?>
      <div class="text-center p-3 border rounded bg-white">
        <div class="fs-2"><?= match($pl['rank']) { 1=>'🥇', 2=>'🥈', 3=>'🥉', default=>$pl['rank'].'.' } ?></div>
        <div class="fw-bold"><?= e($pl['name']) ?></div>
        <?php if ($pl['club']): ?><div class="text-muted small"><?= e($pl['club']) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (can_edit()): ?>
<!-- Einstellungen -->
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold"><i class="bi bi-gear me-1"></i>Einstellungen</div>
  <div class="card-body">
    <form method="post" action="<?= url('competition/'.$c['id'].'/settings') ?>" class="row g-3 align-items-end">
      <?= csrf_field() ?>
      <?php if ($c['mode'] !== 'ko_only'): ?>
      <?php if ($c['phase'] === 'setup'): ?>
      <div class="col-auto">
        <label class="form-label">Gruppengröße</label>
        <select name="group_size" class="form-select">
          <?php foreach ([3,4,5,6] as $s): ?>
          <option value="<?= $s ?>"<?= (int)$c['group_size'] === $s ? ' selected' : '' ?>><?= $s ?> Spieler</option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <label class="form-label">KO-Aufstieg</label>
        <select name="advance_count" class="form-select">
          <option value="0"<?= (int)$c['advance_count'] === 0 ? ' selected' : '' ?>>Nur Gruppenphase</option>
          <option value="1"<?= (int)$c['advance_count'] === 1 ? ' selected' : '' ?>>Gruppenerste → KO</option>
          <option value="2"<?= (int)$c['advance_count'] === 2 ? ' selected' : '' ?>>Erste &amp; Zweite → KO</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="third_place" id="third_place"
                 <?= $c['third_place'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="third_place">Platz-3-Spiel</label>
        </div>
      </div>
      <div class="col-auto">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="registrations_open" id="regs_open"
                 <?= $c['registrations_open'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="regs_open">Nennung offen</label>
        </div>
      </div>
      <div class="col-auto">
        <label class="form-label">Max. Spieler</label>
        <input type="number" name="max_players" class="form-control" style="width:90px"
               value="<?= (int)$c['max_players'] ?>" min="0">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Speichern</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="row g-4">
  <!-- ═══ LINKS: Gruppenphase / KO ══════════════════════════════════════════ -->
  <div class="col-lg-8">

    <?php if ($groups): ?>
    <!-- Export-Links -->
    <div class="d-flex gap-2 mb-3">
      <a href="<?= url('competition/'.$c['id'].'/pdf/groups') ?>" class="btn btn-outline-danger btn-sm" target="_blank">
        <i class="bi bi-file-earmark-pdf me-1"></i>Gruppen PDF
      </a>
      <a href="<?= url('competition/'.$c['id'].'/pdf/match-cards') ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
        <i class="bi bi-card-text me-1"></i>Match-Cards
      </a>
    </div>

    <?php foreach ($groups as $gi): $g = $gi['group']; $standings = $gi['standings']; $matches = $gi['matches']; ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header fw-semibold"><i class="bi bi-people me-1"></i><?= e($g['name']) ?></div>
      <div class="card-body p-0">
        <!-- Standings table -->
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr><th>#</th><th>Spieler</th><th class="text-center">Sp</th><th class="text-center">S</th><th class="text-center">U</th><th class="text-center">N</th><th class="text-center">Tore</th><th class="text-center">TD</th><th class="text-center">Pkt</th></tr>
            </thead>
            <tbody>
              <?php foreach ($standings as $i => $pl): ?>
              <tr<?= $i === 0 ? ' class="table-success"' : '' ?>>
                <td><strong><?= $i+1 ?></strong></td>
                <td><?= e($pl['name']) ?> <small class="text-muted"><?= e($pl['club']) ?></small></td>
                <td class="text-center"><?= $pl['played'] ?></td>
                <td class="text-center"><?= $pl['wins'] ?></td>
                <td class="text-center"><?= $pl['draws'] ?></td>
                <td class="text-center"><?= $pl['losses'] ?></td>
                <td class="text-center"><?= $pl['goals_for'] ?>:<?= $pl['goals_against'] ?></td>
                <td class="text-center"><?= $pl['goal_diff'] >= 0 ? '+' . $pl['goal_diff'] : $pl['goal_diff'] ?></td>
                <td class="text-center"><strong><?= $pl['points'] ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <!-- Match results -->
        <?php if (can_edit()): ?>
        <div class="p-3 border-top">
          <h6 class="text-muted mb-3">Spielergebnisse</h6>
          <?php foreach ($matches as $m): ?>
          <?php if (!$m['player1_id'] || !$m['player2_id']) continue; ?>
          <form method="post" action="<?= url('match/'.$m['id'].'/result') ?>" class="d-flex align-items-center gap-2 mb-2">
            <?= csrf_field() ?>
            <span class="flex-grow-1 text-end small"><?= e($m['p1name']) ?></span>
            <input type="number" name="score1" min="0" class="form-control form-control-sm text-center" style="width:60px"
                   value="<?= $m['played'] ? $m['score1'] : '' ?>" required>
            <span>:</span>
            <input type="number" name="score2" min="0" class="form-control form-control-sm text-center" style="width:60px"
                   value="<?= $m['played'] ? $m['score2'] : '' ?>" required>
            <span class="flex-grow-1 small"><?= e($m['p2name']) ?></span>
            <button class="btn btn-sm <?= $m['played'] ? 'btn-success' : 'btn-primary' ?>">
              <i class="bi bi-<?= $m['played'] ? 'check-circle' : 'save' ?>"></i>
            </button>
          </form>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($ko_rounds || $third_place_match): ?>
    <!-- KO-Phase -->
    <div class="d-flex gap-2 mb-3">
      <a href="<?= url('competition/'.$c['id'].'/pdf/ko') ?>" class="btn btn-outline-danger btn-sm" target="_blank">
        <i class="bi bi-file-earmark-pdf me-1"></i>KO PDF
      </a>
    </div>
    <?php foreach ($ko_rounds as $round): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold"><?= e($round['name']) ?></div>
      <div class="card-body p-0">
        <?php foreach ($round['matches'] as $m): ?>
        <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
          <span class="flex-grow-1 text-end <?= ($m['played'] && $m['score1'] > $m['score2']) ? 'fw-bold' : '' ?>">
            <?= e($m['p1name'] ?? 'Freilos') ?>
          </span>
          <?php if (can_edit() && $m['player1_id'] && $m['player2_id']): ?>
          <form method="post" action="<?= url('match/'.$m['id'].'/result') ?>" class="d-flex align-items-center gap-1">
            <?= csrf_field() ?>
            <input type="number" name="score1" min="0" class="form-control form-control-sm text-center" style="width:55px"
                   value="<?= $m['played'] ? $m['score1'] : '' ?>" required>
            <span>:</span>
            <input type="number" name="score2" min="0" class="form-control form-control-sm text-center" style="width:55px"
                   value="<?= $m['played'] ? $m['score2'] : '' ?>" required>
            <button class="btn btn-sm <?= $m['played'] ? 'btn-success' : 'btn-primary' ?>">
              <i class="bi bi-save"></i>
            </button>
          </form>
          <?php elseif ($m['played']): ?>
          <span class="badge bg-light text-dark border"><?= $m['score1'] ?>:<?= $m['score2'] ?></span>
          <?php else: ?>
          <span class="text-muted">—:—</span>
          <?php endif; ?>
          <span class="flex-grow-1 <?= ($m['played'] && $m['score2'] > $m['score1']) ? 'fw-bold' : '' ?>">
            <?= e($m['p2name'] ?? 'Freilos') ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if ($third_place_match): ?>
    <div class="card shadow-sm mb-3 border-warning">
      <div class="card-header fw-semibold text-warning-emphasis bg-warning-subtle">Spiel um Platz 3</div>
      <div class="card-body p-0">
        <?php foreach ($third_place_match['matches'] as $m): ?>
        <div class="d-flex align-items-center gap-3 px-3 py-2">
          <span class="flex-grow-1 text-end"><?= e($m['p1name'] ?? 'Freilos') ?></span>
          <?php if (can_edit() && $m['player1_id'] && $m['player2_id']): ?>
          <form method="post" action="<?= url('match/'.$m['id'].'/result') ?>" class="d-flex align-items-center gap-1">
            <?= csrf_field() ?>
            <input type="number" name="score1" min="0" class="form-control form-control-sm text-center" style="width:55px"
                   value="<?= $m['played'] ? $m['score1'] : '' ?>">
            <span>:</span>
            <input type="number" name="score2" min="0" class="form-control form-control-sm text-center" style="width:55px"
                   value="<?= $m['played'] ? $m['score2'] : '' ?>">
            <button class="btn btn-sm btn-primary"><i class="bi bi-save"></i></button>
          </form>
          <?php elseif ($m['played']): ?>
          <span class="badge bg-light text-dark border"><?= $m['score1'] ?>:<?= $m['score2'] ?></span>
          <?php else: ?>
          <span class="text-muted">—:—</span>
          <?php endif; ?>
          <span class="flex-grow-1"><?= e($m['p2name'] ?? 'Freilos') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- ═══ RECHTS: Spielerliste + Aktionen ══════════════════════════════════ -->
  <div class="col-lg-4">

    <?php if (can_edit() && $c['phase'] === 'setup'): ?>
    <!-- Gruppenauslosung -->
    <?php if ($c['mode'] !== 'ko_only' && count($assigned) >= 3): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="post" action="<?= url('competition/'.$c['id'].'/draw/groups') ?>">
          <?= csrf_field() ?>
          <button class="btn btn-primary w-100">
            <i class="bi bi-shuffle me-1"></i>Gruppen auslosen
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <!-- KO-Direktauslosung -->
    <?php if ($c['mode'] === 'ko_only' && count($assigned) >= 2): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="post" action="<?= url('competition/'.$c['id'].'/draw/ko-direct') ?>">
          <?= csrf_field() ?>
          <button class="btn btn-primary w-100">
            <i class="bi bi-shuffle me-1"></i>KO-Bracket auslosen
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (can_edit() && $c['phase'] === 'group' && $unplayed_group == 0 && (int)$c['advance_count'] > 0): ?>
    <!-- KO-Auslosung -->
    <div class="card shadow-sm mb-3 border-primary">
      <div class="card-body">
        <form method="post" action="<?= url('competition/'.$c['id'].'/draw/ko') ?>">
          <?= csrf_field() ?>
          <button class="btn btn-primary w-100">
            <i class="bi bi-trophy me-1"></i>KO-Phase auslosen
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Spielerliste -->
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <span><i class="bi bi-people me-1"></i>Spieler (<?= count($assigned) ?>)
        <?php if ($c['max_players']): ?><span class="text-muted small">/ <?= (int)$c['max_players'] ?></span><?php endif; ?>
        </span>
        <?php if ($assigned): ?>
        <div class="btn-group btn-group-sm ms-auto">
          <a href="<?= url('competition/'.$c['id'].'/players/pdf') ?>" class="btn btn-outline-danger" target="_blank" title="PDF">
            <i class="bi bi-file-earmark-pdf"></i>
          </a>
          <a href="<?= url('competition/'.$c['id'].'/players/csv') ?>" class="btn btn-outline-success" title="CSV">
            <i class="bi bi-filetype-csv"></i>
          </a>
        </div>
        <?php endif; ?>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($assigned as $i => $pl): ?>
        <div class="list-group-item py-1 d-flex align-items-center gap-2">
          <span class="text-muted small me-1"><?= $i+1 ?>.</span>
          <span class="flex-grow-1 small">
            <?= e(trim($pl['name'] . ' ' . ($pl['firstname'] ?? ''))) ?>
            <?php if ($pl['club']): ?><br><span class="text-muted" style="font-size:.75rem"><?= e($pl['club']) ?></span><?php endif; ?>
          </span>
          <?php if ($pl['skill']): ?><span class="badge bg-secondary"><?= $pl['skill'] ?></span><?php endif; ?>
          <?php if (can_edit() && $c['phase'] === 'setup'): ?>
          <form method="post" action="<?= url('competition/'.$c['id'].'/player/'.$pl['id'].'/remove') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-x"></i></button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (can_edit() && $c['phase'] === 'setup' && $unassigned): ?>
      <div class="card-footer">
        <form method="post" action="<?= url('competition/'.$c['id'].'/player/add') ?>">
          <?= csrf_field() ?>
          <select name="player_ids[]" class="form-select form-select-sm mb-2" multiple size="4">
            <?php foreach ($unassigned as $pl): ?>
            <option value="<?= $pl['id'] ?>">
              <?= e(trim($pl['name'] . ' ' . ($pl['firstname'] ?? ''))) ?>
              <?php if ($pl['club']): ?>(<?= e($pl['club']) ?>)<?php endif; ?>
              <?php if ($unassigned_skills[$pl['id']] ?? 0): ?>· <?= $unassigned_skills[$pl['id']] ?><?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-primary w-100">
            <i class="bi bi-plus me-1"></i>Hinzufügen
          </button>
        </form>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
