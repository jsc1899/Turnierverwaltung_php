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

    <?php if (can_edit() && ($group_no_results ?? false)): ?>
    <div class="d-flex align-items-center mb-3">
      <button type="button" id="grp-edit-btn" onclick="toggleGrpEdit()"
              class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrows-move me-1"></i>Umstellen
      </button>
    </div>
    <div id="grp-edit-toolbar" style="display:none"
         class="d-flex align-items-center gap-2 mb-3 p-2 rounded border border-warning-subtle bg-warning-subtle">
      <i class="bi bi-info-circle text-warning-emphasis"></i>
      <span class="small">Spieler per Drag &amp; Drop zwischen Gruppen verschieben.</span>
      <div class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-success btn-sm" onclick="saveGrpReorder()">
          <i class="bi bi-save me-1"></i>Speichern
        </button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleGrpEdit()">Abbrechen</button>
      </div>
    </div>
    <form id="grp-reorder-form" method="post" action="<?= url('competition/'.$c['id'].'/groups/reorder') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>

    <?php foreach ($groups as $gi): $g = $gi['group']; $standings = $gi['standings']; $matches = $gi['matches']; ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header fw-semibold"><i class="bi bi-people me-1"></i><?= e($g['name']) ?></div>
      <div class="card-body p-0">
        <div class="grp-normal-view">
        <!-- Standings table -->
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr><th>#</th><th>Spieler</th><th class="text-center">Sp</th><th class="text-center">S</th><th class="text-center">U</th><th class="text-center">N</th><th class="text-center">V</th><th class="text-center">+/-</th><th class="text-center">Pkt</th></tr>
            </thead>
            <tbody>
              <?php foreach ($standings as $i => $pl): ?>
              <tr<?= $i === 0 ? ' class="table-success"' : '' ?>>
                <td><strong><?= $i+1 ?></strong></td>
                <td>
                  <?= e($pl['name']) ?>
                  <small class="text-muted"><?= e($pl['club']) ?></small>
                  <?php if (($t['show_skill'] ?? 0) && $pl['skill']):
                    $sv = ($t['sport'] ?? '') === 'tennis' ? number_format((float)$pl['skill'], 1) : (int)$pl['skill']; ?>
                  <span class="badge bg-secondary ms-1"><?= $sv ?></span>
                  <?php endif; ?>
                </td>
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
          <h6 class="text-muted mb-2">Spielergebnisse</h6>
          <form id="grp-form-<?= $g['id'] ?>" method="post"
                action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
            <?= csrf_field() ?>
          </form>
          <?php foreach ($matches as $m): ?>
          <?php if (!$m['player1_id'] || !$m['player2_id']) continue; ?>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="flex-grow-1 text-end small"><?= e($m['p1name']) ?></span>
            <input type="number" name="matches[<?= $m['id'] ?>][score1]" min="0"
                   form="grp-form-<?= $g['id'] ?>"
                   class="form-control form-control-sm text-center" style="width:60px"
                   value="<?= $m['played'] ? $m['score1'] : '' ?>">
            <span>:</span>
            <input type="number" name="matches[<?= $m['id'] ?>][score2]" min="0"
                   form="grp-form-<?= $g['id'] ?>"
                   class="form-control form-control-sm text-center" style="width:60px"
                   value="<?= $m['played'] ? $m['score2'] : '' ?>">
            <span class="flex-grow-1 small"><?= e($m['p2name']) ?></span>
            <?php if ($m['played']): ?>
            <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-outline-danger" title="Ergebnis löschen">
                <i class="bi bi-x-circle"></i>
              </button>
            </form>
            <?php else: ?>
            <div style="width:34px"></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <button form="grp-form-<?= $g['id'] ?>" type="submit"
                  class="btn btn-primary btn-sm mt-2 w-100">
            <i class="bi bi-save me-1"></i>Alle speichern
          </button>
        </div>
        <?php endif; ?>
        </div><!-- /.grp-normal-view -->
        <?php if (can_edit() && ($group_no_results ?? false)): ?>
        <div class="grp-edit-panel p-2" data-gid="<?= $g['id'] ?>"
             style="display:none; min-height:80px; border:2px dashed #dee2e6; border-radius:4px; transition:background .1s">
          <?php foreach ($standings as $pl): ?>
          <div class="grp-player-pill d-flex align-items-center gap-2 px-2 py-1 mb-1 rounded"
               style="background:#f8f9fa; border:1px solid #dee2e6; cursor:grab; user-select:none"
               draggable="true" data-pid="<?= (int)$pl['id'] ?>"
               ondragstart="grpDragStart(event)" ondragend="grpDragEnd(event)">
            <i class="bi bi-grip-vertical text-muted"></i>
            <span class="small fw-semibold"><?= e($pl['name']) ?></span>
            <?php if ($pl['club']): ?><span class="text-muted small"><?= e($pl['club']) ?></span><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($ko_rounds || $third_place_match): ?>
    <!-- KO-Phase -->
    <div class="d-flex gap-2 align-items-center mb-3">
      <a href="<?= url('competition/'.$c['id'].'/pdf/ko') ?>" class="btn btn-outline-danger btn-sm" target="_blank">
        <i class="bi bi-file-earmark-pdf me-1"></i>KO PDF
      </a>
      <?php if (can_edit() && $ko_no_results): ?>
      <button type="button" id="ko-edit-btn" onclick="toggleKoEdit()"
              class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrows-move me-1"></i>Umstellen
      </button>
      <?php endif; ?>
    </div>
    <?php if (can_edit() && $ko_no_results): ?>
    <div id="ko-edit-toolbar" style="display:none"
         class="d-flex align-items-center gap-2 mb-3 p-2 rounded border border-warning-subtle bg-warning-subtle">
      <i class="bi bi-info-circle text-warning-emphasis"></i>
      <span class="small">Spieler im 1. Runde per Drag &amp; Drop tauschen.</span>
      <div class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-success btn-sm" onclick="saveKoReorder()">
          <i class="bi bi-save me-1"></i>Speichern
        </button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleKoEdit()">Abbrechen</button>
      </div>
    </div>
    <form id="ko-reorder-form" method="post" action="<?= url('competition/'.$c['id'].'/ko/reorder') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>

    <?php if ($ko_rounds):
      $first_count = count($ko_rounds[0]['matches']);
      $slot_h = 100;
      $bracket_h = $first_count * $slot_h;
      $bracket_w = count($ko_rounds) * 230;
    ?>

    <?php if (can_edit()): ?>
    <?php foreach ($ko_rounds as $ri => $round): ?>
    <form id="ko-form-<?= $ri ?>" method="post" action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endforeach; ?>
    <?php if ($third_place_match): ?>
    <form id="ko-form-p3" method="post" action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>
    <?php endif; ?>

    <div style="overflow-x:auto; margin-bottom:4px">
      <!-- Round headers -->
      <div style="display:flex; width:<?= $bracket_w ?>px">
        <?php $last_ri = count($ko_rounds) - 1; ?>
        <?php foreach ($ko_rounds as $ri => $round): ?>
        <div style="width:230px; flex-shrink:0; text-align:center; font-weight:600; font-size:.8rem; padding:0 8px 4px;
                    color:<?= $ri === $last_ri ? '#856404' : '#6c757d' ?>">
          <?= $ri === $last_ri ? '🏆 ' : '' ?><?= e($round['name']) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <!-- Bracket body -->
      <div id="ko-bracket-<?= $c['id'] ?>" style="display:flex; position:relative; height:<?= $bracket_h ?>px; width:<?= $bracket_w ?>px">
        <?php foreach ($ko_rounds as $ri => $round): ?>
        <div class="ko-round" style="display:flex;flex-direction:column;justify-content:space-around;width:230px;flex-shrink:0;height:100%;padding:0 8px">
          <?php foreach ($round['matches'] as $m): ?>
          <div class="ko-match" style="border:1px solid #dee2e6;border-radius:6px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden">
            <!-- Spieler 1 -->
            <?php $isFirstRound = ($ri === 0); ?>
            <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score1'] > $m['score2']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                 style="min-height:33px;border-bottom:1px solid #f0f0f0">
              <span class="flex-grow-1 small text-truncate<?= ($ko_no_results && can_edit() && $isFirstRound) ? ' ko-slot' : '' ?>"
                    style="max-width:130px"
                    data-pid="<?= (int)($m['player1_id'] ?? 0) ?>"
                    data-name="<?= e($m['p1name'] ?? '') ?>"
                    title="<?= e($m['p1name'] ?? '') ?>"
                    <?php if ($ko_no_results && can_edit() && $isFirstRound): ?>
                    ondragstart="koSlotDragStart(event)" ondragend="koSlotDragEnd(event)"
                    <?php endif; ?>>
                <?= $m['player1_id'] ? e($m['p1name']) : '<span class="text-muted fst-italic">—</span>' ?>
              </span>
              <?php if (can_edit() && $m['player1_id'] && $m['player2_id']): ?>
              <input type="number" name="matches[<?= $m['id'] ?>][score1]" min="0"
                     form="ko-form-<?= $ri ?>"
                     class="form-control form-control-sm text-center" style="width:40px;height:24px;padding:0 2px;font-size:.8rem"
                     value="<?= $m['played'] ? $m['score1'] : '' ?>">
              <?php elseif ($m['played']): ?>
              <span class="fw-bold small"><?= $m['score1'] ?></span>
              <?php endif; ?>
            </div>
            <!-- Spieler 2 -->
            <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score2'] > $m['score1']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                 style="min-height:33px">
              <span class="flex-grow-1 small text-truncate<?= ($ko_no_results && can_edit() && $isFirstRound) ? ' ko-slot' : '' ?>"
                    style="max-width:130px"
                    data-pid="<?= (int)($m['player2_id'] ?? 0) ?>"
                    data-name="<?= e($m['p2name'] ?? '') ?>"
                    title="<?= e($m['p2name'] ?? '') ?>"
                    <?php if ($ko_no_results && can_edit() && $isFirstRound): ?>
                    ondragstart="koSlotDragStart(event)" ondragend="koSlotDragEnd(event)"
                    <?php endif; ?>>
                <?= $m['player2_id'] ? e($m['p2name']) : '<span class="text-muted fst-italic">—</span>' ?>
              </span>
              <?php if (can_edit() && $m['player1_id'] && $m['player2_id']): ?>
              <input type="number" name="matches[<?= $m['id'] ?>][score2]" min="0"
                     form="ko-form-<?= $ri ?>"
                     class="form-control form-control-sm text-center" style="width:40px;height:24px;padding:0 2px;font-size:.8rem"
                     value="<?= $m['played'] ? $m['score2'] : '' ?>">
              <?php elseif ($m['played']): ?>
              <span class="fw-bold small"><?= $m['score2'] ?></span>
              <?php endif; ?>
            </div>
            <?php if (can_edit() && $m['played'] && $m['player1_id'] && $m['player2_id']): ?>
            <div style="border-top:1px solid #f0f0f0;padding:0 4px 1px;text-align:right">
              <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn btn-link text-danger p-0" style="font-size:.7rem;line-height:1.4" title="Ergebnis löschen">
                  <i class="bi bi-x-circle"></i>
                </button>
              </form>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <!-- SVG Verbindungslinien -->
        <svg id="bracket-svg-<?= $c['id'] ?>" style="position:absolute;top:0;left:0;pointer-events:none;overflow:visible"
             width="<?= $bracket_w ?>" height="<?= $bracket_h ?>"></svg>
      </div>
      <!-- Speichern-Buttons -->
      <?php if (can_edit()): ?>
      <div style="display:flex;width:<?= $bracket_w ?>px;margin-top:6px">
        <?php foreach ($ko_rounds as $ri => $round): ?>
        <div style="width:230px;flex-shrink:0;padding:0 8px">
          <button form="ko-form-<?= $ri ?>" type="submit" class="btn btn-primary btn-sm w-100">
            <i class="bi bi-save me-1"></i>Speichern
          </button>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($third_place_match): ?>
      <!-- Platz 3 – unter dem Finale ausgerichtet -->
      <div style="display:flex;width:<?= $bracket_w ?>px;margin-top:20px">
        <?php for ($i = 0; $i < count($ko_rounds) - 1; $i++): ?>
        <div style="width:230px;flex-shrink:0"></div>
        <?php endfor; ?>
        <div style="width:230px;flex-shrink:0;padding:0 8px">
          <div style="text-align:center;font-weight:600;font-size:.8rem;color:#856404;padding-bottom:4px">
            🥉 Spiel um Platz 3
          </div>
          <?php foreach ($third_place_match['matches'] as $m): ?>
          <div class="ko-match" style="border:1px solid #ffc107;border-radius:6px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden">
            <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score1'] > $m['score2']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                 style="min-height:33px;border-bottom:1px solid #f0f0f0">
              <span class="flex-grow-1 small text-truncate" style="max-width:130px"
                    title="<?= e($m['p1name'] ?? '') ?>"><?= e($m['p1name'] ?? 'Freilos') ?></span>
              <?php if (can_edit() && $m['player1_id'] && $m['player2_id']): ?>
              <input type="number" name="matches[<?= $m['id'] ?>][score1]" min="0" form="ko-form-p3"
                     class="form-control form-control-sm text-center" style="width:40px;height:24px;padding:0 2px;font-size:.8rem"
                     value="<?= $m['played'] ? $m['score1'] : '' ?>">
              <?php elseif ($m['played']): ?><span class="fw-bold small"><?= $m['score1'] ?></span><?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score2'] > $m['score1']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                 style="min-height:33px">
              <span class="flex-grow-1 small text-truncate" style="max-width:130px"
                    title="<?= e($m['p2name'] ?? '') ?>"><?= e($m['p2name'] ?? 'Freilos') ?></span>
              <?php if (can_edit() && $m['player1_id'] && $m['player2_id']): ?>
              <input type="number" name="matches[<?= $m['id'] ?>][score2]" min="0" form="ko-form-p3"
                     class="form-control form-control-sm text-center" style="width:40px;height:24px;padding:0 2px;font-size:.8rem"
                     value="<?= $m['played'] ? $m['score2'] : '' ?>">
              <?php elseif ($m['played']): ?><span class="fw-bold small"><?= $m['score2'] ?></span><?php endif; ?>
            </div>
            <?php if (can_edit() && $m['played'] && $m['player1_id'] && $m['player2_id']): ?>
            <div style="border-top:1px solid #f0f0f0;padding:0 4px 1px;text-align:right">
              <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn btn-link text-danger p-0" style="font-size:.7rem;line-height:1.4" title="Ergebnis löschen">
                  <i class="bi bi-x-circle"></i>
                </button>
              </form>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if (can_edit()): ?>
          <div class="mt-1">
            <button form="ko-form-p3" type="submit" class="btn btn-primary btn-sm w-100">
              <i class="bi bi-save me-1"></i>Speichern
            </button>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
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
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" data-sortable>
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Verein</th>
              <th>Angemeldet</th>
              <th class="text-center">St.</th>
              <?php if (can_edit() && $c['phase'] === 'setup'): ?><th class="no-sort"></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($assigned as $pl): ?>
          <tr>
            <td class="small fw-semibold"><?= e(trim($pl['name'] . ' ' . ($pl['firstname'] ?? ''))) ?></td>
            <td class="small text-muted"><?= e($pl['club'] ?? '') ?></td>
            <td class="small text-muted text-nowrap"
                data-sort="<?= e($pl['reg_date'] ?? '') ?>">
              <?= $pl['reg_date'] ? date('d.m.Y', strtotime($pl['reg_date'])) : '—' ?>
            </td>
            <td class="text-center" data-sort="<?= $pl['skill'] ?? 0 ?>">
              <?php if ($pl['skill']):
                $sv = ($t['sport'] ?? '') === 'tennis' ? number_format((float)$pl['skill'], 1) : (int)$pl['skill']; ?>
              <span class="badge bg-secondary"><?= $sv ?></span>
              <?php endif; ?>
            </td>
            <?php if (can_edit() && $c['phase'] === 'setup'): ?>
            <td>
              <form method="post" action="<?= url('competition/'.$c['id'].'/player/'.$pl['id'].'/remove') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-x"></i></button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
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
$extra_js = <<<'JS'
<script>
// ── Group Drag-and-Drop ──────────────────────────────────────────
var grpDragEl = null, grpEditActive = false;
function toggleGrpEdit() {
  grpEditActive = !grpEditActive;
  document.querySelectorAll('.grp-normal-view').forEach(function(el) { el.style.display = grpEditActive ? 'none' : ''; });
  document.querySelectorAll('.grp-edit-panel').forEach(function(el) { el.style.display = grpEditActive ? '' : 'none'; });
  var tb = document.getElementById('grp-edit-toolbar'); if (tb) tb.style.display = grpEditActive ? '' : 'none';
  var btn = document.getElementById('grp-edit-btn');
  if (btn) btn.innerHTML = grpEditActive ? '<i class="bi bi-x me-1"></i>Abbrechen' : '<i class="bi bi-arrows-move me-1"></i>Umstellen';
}
function grpDragStart(e) {
  grpDragEl = e.currentTarget; e.dataTransfer.effectAllowed = 'move';
  setTimeout(function() { if (grpDragEl) grpDragEl.style.opacity = '0.4'; }, 0);
}
function grpDragEnd() { if (grpDragEl) grpDragEl.style.opacity = ''; grpDragEl = null; }
function saveGrpReorder() {
  var form = document.getElementById('grp-reorder-form');
  form.querySelectorAll('input[name^="groups"]').forEach(function(el) { el.remove(); });
  document.querySelectorAll('.grp-edit-panel').forEach(function(zone) {
    var gid = zone.dataset.gid;
    zone.querySelectorAll('.grp-player-pill').forEach(function(pill) {
      var inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'groups[' + gid + '][]'; inp.value = pill.dataset.pid; form.appendChild(inp);
    });
  });
  form.submit();
}

// ── KO Drag-and-Drop ─────────────────────────────────────────────
var koDragEl = null, koEditActive = false;
function toggleKoEdit() {
  koEditActive = !koEditActive;
  document.querySelectorAll('.ko-slot').forEach(function(s) {
    var has = s.dataset.pid && s.dataset.pid !== '0';
    s.setAttribute('draggable', koEditActive && has ? 'true' : 'false');
    s.style.background = koEditActive && has ? '#fff3cd' : '';
    s.style.cursor = koEditActive && has ? 'grab' : '';
  });
  var tb = document.getElementById('ko-edit-toolbar'); if (tb) tb.style.display = koEditActive ? '' : 'none';
  var btn = document.getElementById('ko-edit-btn');
  if (btn) btn.innerHTML = koEditActive ? '<i class="bi bi-x me-1"></i>Abbrechen' : '<i class="bi bi-arrows-move me-1"></i>Umstellen';
}
function koSlotDragStart(e) {
  if (!koEditActive) { e.preventDefault(); return; }
  var slot = e.currentTarget;
  if (!slot.dataset.pid || slot.dataset.pid === '0') { e.preventDefault(); return; }
  koDragEl = slot; e.dataTransfer.effectAllowed = 'move';
  setTimeout(function() { if (koDragEl) koDragEl.style.opacity = '0.4'; }, 0);
}
function koSlotDragEnd() { if (koDragEl) koDragEl.style.opacity = ''; koDragEl = null; }
function saveKoReorder() {
  var form = document.getElementById('ko-reorder-form');
  form.querySelectorAll('input[name^="bracket"]').forEach(function(el) { el.remove(); });
  document.querySelectorAll('.ko-slot').forEach(function(slot) {
    var inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'bracket[]'; inp.value = slot.dataset.pid || '0'; form.appendChild(inp);
  });
  form.submit();
}

// ── Event-Delegation für Drop-Zonen ──────────────────────────────
document.addEventListener('dragover', function(e) {
  if (grpDragEl) {
    var panel = e.target.closest('.grp-edit-panel'); if (panel) { e.preventDefault(); panel.style.borderColor = '#0d6efd'; panel.style.background = '#e8f0fe'; }
  }
  if (koDragEl) {
    var slot = e.target.closest('.ko-slot'); if (slot && slot !== koDragEl) { e.preventDefault(); slot.style.outline = '2px solid #0d6efd'; }
  }
});
document.addEventListener('dragleave', function(e) {
  if (grpDragEl) {
    var panel = e.target.closest('.grp-edit-panel');
    if (panel && !panel.contains(e.relatedTarget)) { panel.style.borderColor = '#dee2e6'; panel.style.background = ''; }
  }
  if (koDragEl) {
    var slot = e.target.closest('.ko-slot'); if (slot && !slot.contains(e.relatedTarget)) slot.style.outline = '';
  }
});
document.addEventListener('drop', function(e) {
  if (grpDragEl) {
    var panel = e.target.closest('.grp-edit-panel');
    if (panel) {
      e.preventDefault(); panel.style.borderColor = '#dee2e6'; panel.style.background = '';
      if (grpDragEl.parentNode !== panel) panel.appendChild(grpDragEl);
    }
  }
  if (koDragEl) {
    var slot = e.target.closest('.ko-slot');
    if (slot && slot !== koDragEl) {
      e.preventDefault(); slot.style.outline = '';
      var sp = koDragEl.dataset.pid, sn = koDragEl.dataset.name, tp = slot.dataset.pid, tn = slot.dataset.name;
      koDragEl.dataset.pid = tp; koDragEl.dataset.name = tn; koDragEl.textContent = tn || '—';
      koDragEl.style.background = tp !== '0' ? '#fff3cd' : ''; koDragEl.setAttribute('draggable', tp !== '0' ? 'true' : 'false');
      slot.dataset.pid = sp; slot.dataset.name = sn; slot.textContent = sn || '—';
      slot.style.background = sp !== '0' ? '#fff3cd' : ''; slot.setAttribute('draggable', sp !== '0' ? 'true' : 'false');
    }
  }
});

// ── KO Bracket SVG ───────────────────────────────────────────────
function drawBracket(cid) {
  var bracket = document.getElementById('ko-bracket-' + cid);
  var svg = document.getElementById('bracket-svg-' + cid);
  if (!bracket || !svg) return;
  svg.innerHTML = '';
  var bRect = bracket.getBoundingClientRect();
  var rounds = bracket.querySelectorAll('.ko-round');
  for (var ri = 0; ri < rounds.length - 1; ri++) {
    var thisMatches = rounds[ri].querySelectorAll('.ko-match');
    var nextMatches = rounds[ri + 1].querySelectorAll('.ko-match');
    for (var ni = 0; ni < nextMatches.length; ni++) {
      var m1 = thisMatches[ni * 2];
      var m2 = thisMatches[ni * 2 + 1];
      var mn = nextMatches[ni];
      if (!m1 || !m2 || !mn) continue;
      var r1 = m1.getBoundingClientRect();
      var r2 = m2.getBoundingClientRect();
      var rn = mn.getBoundingClientRect();
      var x1 = r1.right  - bRect.left, y1 = (r1.top + r1.bottom) / 2 - bRect.top;
      var x2 = r2.right  - bRect.left, y2 = (r2.top + r2.bottom) / 2 - bRect.top;
      var xn = rn.left   - bRect.left, yn = (rn.top + rn.bottom) / 2 - bRect.top;
      var xm = (x1 + xn) / 2;
      bLine(svg, x1, y1, xm, y1);
      bLine(svg, x2, y2, xm, y2);
      bLine(svg, xm, y1, xm, y2);
      bLine(svg, xm, yn, xn, yn);
    }
  }
}
function bLine(svg, x1, y1, x2, y2) {
  var l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
  l.setAttribute('x1', x1); l.setAttribute('y1', y1);
  l.setAttribute('x2', x2); l.setAttribute('y2', y2);
  l.setAttribute('stroke', '#adb5bd'); l.setAttribute('stroke-width', '2');
  svg.appendChild(l);
}
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[id^="ko-bracket-"]').forEach(function(el) {
    drawBracket(el.id.replace('ko-bracket-', ''));
  });
});
window.addEventListener('resize', function() {
  document.querySelectorAll('[id^="ko-bracket-"]').forEach(function(el) {
    drawBracket(el.id.replace('ko-bracket-', ''));
  });
});
</script>
JS;
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
