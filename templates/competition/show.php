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

<?php $settings_active = true; ?>
<?php if (can_edit()): ?>
<!-- ═══ Registerkarten: Einstellungen + Spielerliste ════════════════════════ -->
<ul class="nav nav-tabs mb-0" id="comp-tabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link<?= $settings_active ? ' active' : '' ?>" id="tab-settings-btn"
            data-bs-toggle="tab" data-bs-target="#tab-settings" type="button" role="tab">
      <i class="bi bi-gear me-1"></i>Einstellungen
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?= !$settings_active ? ' active' : '' ?>" id="tab-players-btn"
            data-bs-toggle="tab" data-bs-target="#tab-players" type="button" role="tab">
      <?php if ($is_doubles): ?>
      <i class="bi bi-people-fill me-1"></i>Doppel (<?= count($assigned_doubles) ?>)
      <?php else: ?>
      <i class="bi bi-people me-1"></i>Spieler (<?= count($assigned) ?>)
      <?php endif; ?>
      <?php if ($c['max_players']): ?><span class="text-muted small">/ <?= (int)$c['max_players'] ?></span><?php endif; ?>
    </button>
  </li>
</ul>
<div class="tab-content border border-top-0 rounded-bottom mb-4">

  <!-- Tab: Einstellungen -->
  <div class="tab-pane fade<?= $settings_active ? ' show active' : '' ?> p-3" id="tab-settings" role="tabpanel">
    <form method="post" action="<?= url('competition/'.$c['id'].'/settings') ?>" class="row g-3 align-items-end">
      <?= csrf_field() ?>
      <div class="col-auto">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control form-control-sm" style="min-width:180px"
               value="<?= e($c['name']) ?>" required>
      </div>
      <?php if (!in_array($c['mode'], ['ko_only', 'double_ko'])): ?>
      <?php if ($c['phase'] === 'setup'): ?>
      <div class="col-auto">
        <label class="form-label">Gruppengröße</label>
        <select name="group_size" class="form-select form-select-sm">
          <?php foreach ([3,4,5,6,7,8] as $s): ?>
          <option value="<?= $s ?>"<?= (int)$c['group_size'] === $s ? ' selected' : '' ?>><?= $s ?> Spieler</option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <label class="form-label">KO-Aufstieg</label>
        <select name="advance_count" id="advance_count" class="form-select form-select-sm"
                onchange="document.getElementById('third_place_wrap').style.display=this.value==='0'?'none':''">
          <option value="0"<?= (int)$c['advance_count'] === 0 ? ' selected' : '' ?>>Nur Gruppenphase</option>
          <option value="1"<?= (int)$c['advance_count'] === 1 ? ' selected' : '' ?>>Gruppenerste → KO</option>
          <option value="2"<?= (int)$c['advance_count'] === 2 ? ' selected' : '' ?>>Erste &amp; Zweite → KO</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto" id="third_place_wrap"<?= (!in_array($c['mode'], ['ko_only','double_ko']) && (int)$c['advance_count'] === 0) ? ' style="display:none"' : '' ?>>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="third_place" id="third_place"
                 <?= $c['third_place'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="third_place">Platz-3-Spiel</label>
        </div>
      </div>
      <?php if (in_array($c['mode'], ['ko_only', 'double_ko'])): ?>
      <div class="col-auto">
        <label class="form-label">Setzungsreihenfolge</label>
        <select name="seeding_order" class="form-select form-select-sm">
          <option value="desc"<?= ($c['seeding_order'] ?? 'desc') === 'desc' ? ' selected' : '' ?>>Höhere Stärke = stärker</option>
          <option value="asc"<?= ($c['seeding_order'] ?? 'desc') === 'asc'  ? ' selected' : '' ?>>Niedrigere Stärke = stärker (Tennis)</option>
        </select>
      </div>
      <div class="col-auto">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="show_seeding" id="show_seeding"
                 <?= ($c['show_seeding'] ?? 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="show_seeding">Setzungen anzeigen</label>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($c['phase'] === 'setup' && empty($is_doubles) && empty(count($assigned)) && empty(count($assigned_doubles))): ?>
      <div class="col-auto">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_doubles" id="is_doubles"
                 <?= ($c['is_doubles'] ?? 0) ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_doubles">Doppelbewerb</label>
        </div>
      </div>
      <?php elseif ($c['is_doubles'] ?? 0): ?>
      <div class="col-auto">
        <span class="badge bg-info text-dark fs-6"><i class="bi bi-people-fill me-1"></i>Doppelbewerb</span>
        <input type="hidden" name="is_doubles" value="1">
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="registrations_open" id="regs_open"
                 <?= $c['registrations_open'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="regs_open">Nennung offen</label>
        </div>
      </div>
      <div class="col-auto">
        <label class="form-label">Max. Teilnehmer</label>
        <input type="number" name="max_players" class="form-control form-control-sm" style="width:90px"
               value="<?= (int)$c['max_players'] ?>" min="0">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Speichern</button>
      </div>
    </form>
    <?php if ($c['phase'] === 'setup'): ?>
    <?php $draw_count = $is_doubles ? count($assigned_doubles) : count($assigned); ?>
    <?php if (!in_array($c['mode'], ['ko_only','double_ko']) && $draw_count >= 3): ?>
    <hr class="my-3">
    <form method="post" action="<?= url('competition/'.$c['id'].'/draw/groups') ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary btn-sm"><i class="bi bi-shuffle me-1"></i>Gruppen auslosen</button>
    </form>
    <?php endif; ?>
    <?php if ($c['mode'] === 'ko_only' && $draw_count >= 2): ?>
    <hr class="my-3">
    <form method="post" action="<?= url('competition/'.$c['id'].'/draw/ko-direct') ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary btn-sm"><i class="bi bi-shuffle me-1"></i>KO-Bracket auslosen</button>
    </form>
    <?php endif; ?>
    <?php if ($c['mode'] === 'double_ko' && $draw_count >= 2): ?>
    <hr class="my-3">
    <form method="post" action="<?= url('competition/'.$c['id'].'/draw/ko-direct') ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary btn-sm"><i class="bi bi-shuffle me-1"></i>Doppel-KO auslosen</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Tab: Spielerliste / Doppelliste -->
  <div class="tab-pane fade<?= !$settings_active ? ' show active' : '' ?> p-3" id="tab-players" role="tabpanel">
    <?php if ($is_doubles): ?>
    <!-- ── Doppel-Verwaltung ── -->
    <?php if ($assigned_doubles): ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0" data-sortable>
        <thead class="table-light">
          <tr>
            <th>Doppel</th><th>Spieler 1</th><th>Spieler 2</th><th class="text-center">St.</th><th>Angemeldet</th>
            <?php if ($c['phase'] === 'setup'): ?><th class="no-sort"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($assigned_doubles as $d): ?>
        <tr>
          <td class="small fw-semibold"><?= e($d['name']) ?></td>
          <td class="small text-muted"><?= e($d['p1name']) ?><?php if ($d['p1club']): ?> <small class="text-muted">(<?= e($d['p1club']) ?>)</small><?php endif; ?></td>
          <td class="small text-muted"><?= e($d['p2name']) ?><?php if ($d['p2club']): ?> <small class="text-muted">(<?= e($d['p2club']) ?>)</small><?php endif; ?></td>
          <td class="text-center" data-sort="<?= (float)$d['skill'] ?>">
            <?php if ($d['skill']): ?><span class="badge bg-secondary"><?= (int)$d['skill'] ?></span><?php endif; ?>
          </td>
          <td class="small text-muted" data-sort="<?= e($d['reg_date']) ?>"><?= e(fmtdate($d['reg_date'])) ?></td>
          <?php if ($c['phase'] === 'setup'): ?>
          <td>
            <form method="post" action="<?= url('competition/'.$c['id'].'/double/'.$d['id'].'/remove') ?>">
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
    <?php else: ?>
    <p class="text-muted small">Noch keine Doppel eingetragen.</p>
    <?php endif; ?>

    <?php if ($c['phase'] === 'setup' && !empty($confirmed_regs) && can_edit()): ?>
    <div class="mt-3 pt-3 border-top">
      <h6 class="mb-2"><i class="bi bi-person-check me-1"></i>Bestätigte Nennungen <span class="text-muted small fw-normal">(noch ohne Partner)</span></h6>
      <?php $pairable = array_filter($confirmed_regs, fn($r) => !empty($r['player_id'])); ?>
      <table class="table table-sm align-middle mb-2">
        <thead class="table-light"><tr><th>Name</th><th>Verein</th><th>Gewünschter Partner</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($confirmed_regs as $r): ?>
        <tr>
          <td class="small"><?= e($r['firstname'] . ' ' . $r['lastname']) ?></td>
          <td class="small text-muted"><?= e($r['club']) ?></td>
          <td class="small text-muted"><?= $r['partner_name'] ? e($r['partner_name']) : '<span class="text-muted">—</span>' ?></td>
          <td></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (count($pairable) >= 2): ?>
      <form method="post" action="<?= url('competition/'.$c['id'].'/double/pair') ?>" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col">
          <select name="player1_id" class="form-select form-select-sm" required>
            <option value="">Spieler 1 …</option>
            <?php foreach ($pairable as $r): ?>
            <option value="<?= $r['player_id'] ?>"><?= e($r['firstname'] . ' ' . $r['lastname']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col">
          <select name="player2_id" class="form-select form-select-sm" required>
            <option value="">Spieler 2 …</option>
            <?php foreach ($pairable as $r): ?>
            <option value="<?= $r['player_id'] ?>"><?= e($r['firstname'] . ' ' . $r['lastname']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <button class="btn btn-sm btn-success"><i class="bi bi-people-fill me-1"></i>Doppel bilden</button>
        </div>
      </form>
      <?php else: ?>
      <p class="text-muted small mb-0">Mindestens 2 Spieler müssen im Spielerregister vorhanden sein, um ein Doppel zu bilden.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($c['phase'] === 'setup'): ?>
    <?php if ($unassigned_doubles): ?>
    <div class="mt-3 pt-3 border-top">
      <form method="post" action="<?= url('competition/'.$c['id'].'/double/add') ?>">
        <?= csrf_field() ?>
        <select name="double_ids[]" class="form-select form-select-sm mb-2" multiple size="4">
          <?php foreach ($unassigned_doubles as $d): ?>
          <option value="<?= $d['id'] ?>">
            <?= e($d['name']) ?>
            <?php if ($d['skill']): ?>· <?= (int)$d['skill'] ?><?php endif; ?>
          </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus me-1"></i>Doppel hinzufügen</button>
      </form>
    </div>
    <?php elseif (!$unassigned_doubles && $t): ?>
    <div class="mt-3 pt-3 border-top">
      <p class="text-muted small mb-1">Alle vorhandenen Doppel sind bereits eingetragen.</p>
      <a href="<?= url('players#doppel') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-plus-circle me-1"></i>Doppel verwalten
      </a>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php else: ?>
    <!-- ── Spieler-Verwaltung (Einzelbewerb) ── -->
    <?php if ($assigned): ?>
    <div class="btn-group btn-group-sm mb-3">
      <a href="<?= url('competition/'.$c['id'].'/players/pdf') ?>" class="btn btn-outline-danger" target="_blank" title="PDF">
        <i class="bi bi-file-earmark-pdf"></i>
      </a>
      <a href="<?= url('competition/'.$c['id'].'/players/csv') ?>" class="btn btn-outline-success" title="CSV">
        <i class="bi bi-filetype-csv"></i>
      </a>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0" data-sortable>
        <thead class="table-light">
          <tr>
            <th>Name</th><th>Verein</th><th>Angemeldet</th><th class="text-center">St.</th>
            <?php if ($c['phase'] === 'setup'): ?><th class="no-sort"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($assigned as $pl): ?>
        <tr>
          <td class="small fw-semibold"><?= e(trim($pl['name'] . ' ' . ($pl['firstname'] ?? ''))) ?></td>
          <td class="small text-muted"><?= e($pl['club'] ?? '') ?></td>
          <td class="small text-muted text-nowrap" data-sort="<?= e($pl['reg_date'] ?? '') ?>">
            <?= $pl['reg_date'] ? date('d.m.Y', strtotime($pl['reg_date'])) : '—' ?>
          </td>
          <td class="text-center" data-sort="<?= $pl['skill'] ?? 0 ?>">
            <?php if ($pl['skill']):
              $sv = ($t['sport'] ?? '') === 'tennis' ? number_format((float)$pl['skill'], 1) : (int)$pl['skill']; ?>
            <span class="badge bg-secondary"><?= $sv ?></span>
            <?php endif; ?>
          </td>
          <?php if ($c['phase'] === 'setup'): ?>
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
    <?php if ($c['phase'] === 'setup' && $unassigned): ?>
    <div class="mt-3 pt-3 border-top">
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
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus me-1"></i>Hinzufügen</button>
      </form>
    </div>
    <?php endif; ?>
    <?php endif; /* end is_doubles/else */ ?>
  </div>

</div>
<?php endif; ?>

<!-- KO-Phase auslosen (standalone, Gruppenphase abgeschlossen) -->
<?php if (can_edit() && $c['phase'] === 'group' && $unplayed_group == 0 && (int)$c['advance_count'] > 0): ?>
<div class="card shadow-sm mb-4 border-primary">
  <div class="card-body">
    <form method="post" action="<?= url('competition/'.$c['id'].'/draw/ko') ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary w-100"><i class="bi bi-trophy me-1"></i>KO-Phase auslosen</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ═══ Gruppenphase / KO-Bracket (volle Breite) ════════════════════════════ -->
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
         class="align-items-center gap-2 mb-3 p-2 rounded border border-warning-subtle bg-warning-subtle">
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
              <tr<?= $i < (int)$c['advance_count'] ? ' class="table-success"' : '' ?>>
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
          <div class="mb-2" style="display:grid;grid-template-columns:1fr 56px 1.2rem 56px 1fr 30px;align-items:center;column-gap:4px">
            <span class="text-end small text-truncate"><?= e($m['p1name']) ?></span>
            <input type="number" name="matches[<?= $m['id'] ?>][score1]" min="0"
                   form="grp-form-<?= $g['id'] ?>"
                   class="form-control form-control-sm text-center"
                   value="<?= $m['played'] ? $m['score1'] : '' ?>">
            <span class="text-center">:</span>
            <input type="number" name="matches[<?= $m['id'] ?>][score2]" min="0"
                   form="grp-form-<?= $g['id'] ?>"
                   class="form-control form-control-sm text-center"
                   value="<?= $m['played'] ? $m['score2'] : '' ?>">
            <span class="small text-truncate"><?= e($m['p2name']) ?></span>
            <?php if ($m['played']): ?>
            <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-outline-danger p-0" style="width:30px;height:30px" title="Ergebnis löschen">
                <i class="bi bi-x-circle"></i>
              </button>
            </form>
            <?php else: ?>
            <div></div>
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
      <a href="<?= url('competition/'.$c['id'].'/pdf/match-cards') ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
        <i class="bi bi-card-text me-1"></i>Match-Cards
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
         class="align-items-center gap-2 mb-3 p-2 rounded border border-warning-subtle bg-warning-subtle">
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
      $bracket_w = count($ko_rounds) * 270;
    ?>

    <?php if (can_edit()): ?>
    <form id="ko-form" method="post" action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>

    <div style="overflow-x:auto; margin-bottom:4px">
      <!-- Round headers -->
      <div style="display:flex; width:<?= $bracket_w ?>px">
        <?php $last_ri = count($ko_rounds) - 1; ?>
        <?php foreach ($ko_rounds as $ri => $round): ?>
        <div style="width:270px; flex-shrink:0; text-align:center; font-weight:600; font-size:.8rem; padding:0 8px 4px;
                    color:<?= $ri === $last_ri ? '#856404' : '#6c757d' ?>">
          <?= $ri === $last_ri ? '🏆 ' : '' ?><?= e($round['name']) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <!-- Bracket body -->
      <?php $ko_match_num = 0; ?>
      <div id="ko-bracket-<?= $c['id'] ?>" style="display:flex; position:relative; height:<?= $bracket_h ?>px; width:<?= $bracket_w ?>px">
        <?php foreach ($ko_rounds as $ri => $round): ?>
        <div class="ko-round" style="display:flex;flex-direction:column;justify-content:space-around;width:270px;flex-shrink:0;height:100%;padding:0 8px">
          <?php foreach ($round['matches'] as $m): $ko_match_num++; ?>
          <div class="ko-match" style="border:1px solid #dee2e6;border-radius:6px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden">
            <div style="font-size:.65rem;color:#9e9e9e;padding:1px 6px;border-bottom:1px solid #f5f5f5;background:#fafafa">Spiel <?= $ko_match_num ?></div>
            <!-- Spieler 1 -->
            <?php $isFirstRound = ($ri === 0); ?>
            <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score1'] > $m['score2']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                 style="min-height:33px;border-bottom:1px solid #f0f0f0">
              <?php if (!empty($ko_seedings)): ?>
              <small class="ko-seed-badge text-muted" style="font-size:.6rem;white-space:nowrap;flex-shrink:0;min-width:34px"><?= ($m['player1_id'] && ($s = $ko_seedings[$m['player1_id']] ?? null)) ? '(' . e($s) . ')' : '' ?></small>
              <?php endif; ?>
              <span class="flex-grow-1 small text-truncate<?= ($ko_no_results && can_edit() && $isFirstRound) ? ' ko-slot' : '' ?>"
                    style="min-width:0"
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
                     form="ko-form"
                     class="form-control form-control-sm text-center ms-auto" style="width:40px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0"
                     value="<?= $m['played'] ? $m['score1'] : '' ?>">
              <?php elseif ($m['played']): ?>
              <span class="fw-bold small ms-auto" style="flex-shrink:0"><?= $m['score1'] ?></span>
              <?php endif; ?>
            </div>
            <!-- Spieler 2 -->
            <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score2'] > $m['score1']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                 style="min-height:33px">
              <?php if (!empty($ko_seedings)): ?>
              <small class="ko-seed-badge text-muted" style="font-size:.6rem;white-space:nowrap;flex-shrink:0;min-width:34px"><?= ($m['player2_id'] && ($s = $ko_seedings[$m['player2_id']] ?? null)) ? '(' . e($s) . ')' : '' ?></small>
              <?php endif; ?>
              <span class="flex-grow-1 small text-truncate<?= ($ko_no_results && can_edit() && $isFirstRound) ? ' ko-slot' : '' ?>"
                    style="min-width:0"
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
                     form="ko-form"
                     class="form-control form-control-sm text-center ms-auto" style="width:40px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0"
                     value="<?= $m['played'] ? $m['score2'] : '' ?>">
              <?php elseif ($m['played']): ?>
              <span class="fw-bold small ms-auto" style="flex-shrink:0"><?= $m['score2'] ?></span>
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
      <!-- Speichern-Button -->
      <?php if (can_edit()): ?>
      <div class="mt-2">
        <button form="ko-form" type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-save me-1"></i>Ergebnisse speichern
        </button>
      </div>
      <?php endif; ?>

      <?php if ($third_place_match): ?>
      <!-- Platz 3 – unter dem Finale ausgerichtet -->
      <div style="display:flex;width:<?= $bracket_w ?>px;margin-top:20px">
        <?php for ($i = 0; $i < count($ko_rounds) - 1; $i++): ?>
        <div style="width:270px;flex-shrink:0"></div>
        <?php endfor; ?>
        <div style="width:270px;flex-shrink:0;padding:0 8px">
          <div style="text-align:center;font-weight:600;font-size:.8rem;color:#856404;padding-bottom:4px">
            🥉 Spiel um Platz 3
          </div>
          <?php foreach ($third_place_match['matches'] as $m): ?>
          <div class="ko-match" style="border:1px solid #ffc107;border-radius:6px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden">
            <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score1'] > $m['score2']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                 style="min-height:33px;border-bottom:1px solid #f0f0f0">
              <span class="flex-grow-1 small text-truncate" style="min-width:0"
                    title="<?= e($m['p1name'] ?? '') ?>"><?= e($m['p1name'] ?? 'Freilos') ?></span>
              <?php if (can_edit() && $m['player1_id'] && $m['player2_id']): ?>
              <input type="number" name="matches[<?= $m['id'] ?>][score1]" min="0" form="ko-form"
                     class="form-control form-control-sm text-center ms-auto" style="width:40px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0"
                     value="<?= $m['played'] ? $m['score1'] : '' ?>">
              <?php elseif ($m['played']): ?><span class="fw-bold small ms-auto" style="flex-shrink:0"><?= $m['score1'] ?></span><?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score2'] > $m['score1']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                 style="min-height:33px">
              <span class="flex-grow-1 small text-truncate" style="min-width:0"
                    title="<?= e($m['p2name'] ?? '') ?>"><?= e($m['p2name'] ?? 'Freilos') ?></span>
              <?php if (can_edit() && $m['player1_id'] && $m['player2_id']): ?>
              <input type="number" name="matches[<?= $m['id'] ?>][score2]" min="0" form="ko-form"
                     class="form-control form-control-sm text-center ms-auto" style="width:40px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0"
                     value="<?= $m['played'] ? $m['score2'] : '' ?>">
              <?php elseif ($m['played']): ?><span class="fw-bold small ms-auto" style="flex-shrink:0"><?= $m['score2'] ?></span><?php endif; ?>
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
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

<?php if ($c['mode'] === 'double_ko' && ($dko_wb || $dko_lb || $dko_gf)): ?>
<!-- ═══ Doppel-KO-Bracket ════════════════════════════════════════════════════ -->
<div class="d-flex gap-2 align-items-center mb-3">
  <a href="<?= url('competition/'.$c['id'].'/pdf/ko') ?>" class="btn btn-outline-danger btn-sm" target="_blank">
    <i class="bi bi-file-earmark-pdf me-1"></i>KO PDF
  </a>
  <a href="<?= url('competition/'.$c['id'].'/pdf/match-cards') ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
    <i class="bi bi-card-text me-1"></i>Match-Cards
  </a>
</div>
<?php $wb_num_map = []; // wird im WB-Block befüllt; hier vorbelegen für LB-Block

// Helper: render one DKO match card
function _dko_match_card(array $m, string $form_id, bool $editable, ?int $match_num = null, ?string $p1ph = null, ?string $p2ph = null, array $seedings = []): string {
    $p1 = $m['p1name'] ?? null;
    $p2 = $m['p2name'] ?? null;
    $has_both = $m['player1_id'] && $m['player2_id'];
    $p1win = $m['played'] && $m['score1'] > $m['score2'];
    $p2win = $m['played'] && $m['score2'] > $m['score1'];
    $o = '<div class="ko-match" style="border:1px solid #dee2e6;border-radius:6px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden;min-width:150px">';
    if ($match_num !== null) {
        $o .= '<div style="font-size:.65rem;color:#9e9e9e;padding:1px 6px;border-bottom:1px solid #f5f5f5;background:#fafafa">Spiel ' . $match_num . '</div>';
    }
    $nm_w = ''; // max-width entfernt; name wächst auf verfügbaren Platz
    foreach ([1,2] as $slot) {
        $name  = $slot === 1 ? $p1 : $p2;
        $score = $slot === 1 ? $m['score1'] : $m['score2'];
        $won   = $slot === 1 ? $p1win : $p2win;
        $sid   = $slot === 1 ? $m['player1_id'] : $m['player2_id'];
        $ph    = $slot === 1 ? $p1ph : $p2ph;
        $border = $slot === 1 ? 'border-bottom:1px solid #f0f0f0;' : '';
        $bg = $won ? 'background:#d1fae5;' : '';
        $fw = $won ? 'font-weight:600;' : '';
        $seed_lbl = ($sid && isset($seedings[$sid])) ? $seedings[$sid] : null;
        $o .= "<div class=\"d-flex align-items-center gap-1 px-2\" style=\"min-height:30px;{$border}{$bg}\">";
        if (!empty($seedings)) {
            $seed_txt = $seed_lbl ? '(' . e($seed_lbl) . ')' : '';
            $o .= '<small class="ko-seed-badge text-muted" style="font-size:.6rem;white-space:nowrap;flex-shrink:0;min-width:34px">' . $seed_txt . '</small>';
        }
        if ($name) {
            $o .= '<span class="flex-grow-1 small text-truncate" style="min-width:0;' . $fw . '" title="' . e($name) . '">' . e($name) . '</span>';
        } elseif ($ph) {
            $o .= '<span class="flex-grow-1 text-truncate text-muted fst-italic" style="min-width:0;font-size:.7rem" title="' . e($ph) . '">' . e($ph) . '</span>';
        } else {
            $o .= '<span class="flex-grow-1 small text-muted fst-italic">—</span>';
        }
        if ($editable && $has_both && !$m['played']) {
            $o .= '<input type="number" name="matches[' . $m['id'] . '][score' . $slot . ']" min="0" form="' . e($form_id) . '"'
                . ' class="form-control form-control-sm text-center ms-auto" style="width:38px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0">';
        } elseif ($editable && $has_both && $m['played']) {
            $o .= '<input type="number" name="matches[' . $m['id'] . '][score' . $slot . ']" min="0" form="' . e($form_id) . '"'
                . ' class="form-control form-control-sm text-center ms-auto" style="width:38px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0"'
                . ' value="' . (int)$score . '">';
        } elseif ($m['played'] && $sid) {
            $o .= '<span class="fw-bold small ms-auto" style="flex-shrink:0">' . (int)$score . '</span>';
        }
        $o .= '</div>';
    }
    if ($editable && $m['played'] && $has_both) {
        $o .= '<div style="border-top:1px solid #f0f0f0;padding:0 4px 1px;text-align:right">'
            . '<form method="post" action="' . url('match/' . $m['id'] . '/result/clear') . '" style="display:inline">'
            . csrf_field()
            . '<button class="btn btn-link text-danger p-0" style="font-size:.7rem" title="Ergebnis löschen"><i class="bi bi-x-circle"></i></button>'
            . '</form></div>';
    }
    $o .= '</div>';
    return $o;
}
?>

<?php if ($dko_wb):
  $dko_wb_arr  = array_values($dko_wb);
  $dko_first_n = count(reset($dko_wb_arr)['matches']);
  $dko_slot_h  = 100;
  $dko_wb_h    = $dko_first_n * $dko_slot_h;
  $dko_wb_w    = count($dko_wb_arr) * 270;
  // Sequentielle Spielnummern für WB (Runde 1 top→bottom, Runde 2 top→bottom, ...)
  $wb_num_map = []; $wb_n = 0;
  foreach ($dko_wb_arr as $rd) {
      foreach ($rd['matches'] as $wm) {
          $wb_n++;
          $wb_num_map[(int)$wm['ko_round']][(int)$wm['ko_position']] = $wb_n;
      }
  }
?>
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold"><i class="bi bi-trophy me-1 text-warning"></i>Winners Bracket</div>
  <div class="card-body p-3">
    <?php if (can_edit()): ?>
    <form id="dko-wb-form" method="post" action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>
    <div style="overflow-x:auto">
      <!-- Rundenüberschriften -->
      <div style="display:flex;width:<?= $dko_wb_w ?>px">
        <?php $last_wb_ri = count($dko_wb_arr) - 1; $ri = 0; foreach ($dko_wb as $rd): ?>
        <div style="width:270px;flex-shrink:0;text-align:center;font-weight:600;font-size:.8rem;padding:0 8px 4px;
                    color:<?= $ri === $last_wb_ri ? '#856404' : '#6c757d' ?>">
          <?= $ri === $last_wb_ri ? '🏆 ' : '' ?><?= e($rd['name']) ?>
        </div>
        <?php $ri++; endforeach; ?>
      </div>
      <!-- Bracket-Körper (exakt wie normaler KO) -->
      <div id="dko-wb-bracket-<?= $c['id'] ?>" style="display:flex;position:relative;height:<?= $dko_wb_h ?>px;width:<?= $dko_wb_w ?>px">
        <?php foreach ($dko_wb as $rd): ?>
        <div class="ko-round" style="display:flex;flex-direction:column;justify-content:space-around;width:270px;flex-shrink:0;height:100%;padding:0 8px">
          <?php foreach ($rd['matches'] as $m): ?>
          <?= _dko_match_card($m, 'dko-wb-form', can_edit(), $wb_num_map[(int)$m['ko_round']][(int)$m['ko_position']] ?? null, null, null, $ko_seedings) ?>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <svg id="dko-wb-svg-<?= $c['id'] ?>" style="position:absolute;top:0;left:0;pointer-events:none;overflow:visible"
             width="<?= $dko_wb_w ?>" height="<?= $dko_wb_h ?>"></svg>
      </div>
      <?php if (can_edit()): ?>
      <div class="mt-2">
        <button form="dko-wb-form" type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-save me-1"></i>Ergebnisse speichern
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($dko_lb):
  // Platzhalter: LB-Slot → "Verlierer Spiel N" (aus WB)
  $lb_ph = [];
  if (!empty($wb_num_map)) {
      $n_wb1 = $dko_cap >> 1; // Anzahl WB-R1-Matches
      // LB R1: beide Slots kommen aus WB R1
      for ($p = 0; $p < ($n_wb1 >> 1); $p++) {
          $lb_ph[1][$p][1] = 'Verlierer Spiel ' . ($wb_num_map[1][$p] ?? '?');
          $lb_ph[1][$p][2] = 'Verlierer Spiel ' . ($wb_num_map[1][$n_wb1 - 1 - $p] ?? '?');
      }
      // LB gerade Runden: Slot 2 kommt aus WB-Runde (lb_r/2 + 1)
      for ($lb_r = 2; $lb_r <= $dko_lb_total; $lb_r += 2) {
          $wb_r       = (int)($lb_r / 2) + 1;
          $wb_r_count = $dko_cap >> $wb_r;
          $lb_r_count = count($dko_lb[$lb_r]['matches'] ?? []);
          for ($p = 0; $p < $lb_r_count; $p++) {
              $wb_pos = $wb_r_count - 1 - $p;
              $lb_ph[$lb_r][$p][2] = 'Verlierer Spiel ' . ($wb_num_map[$wb_r][$wb_pos] ?? '?');
          }
      }
  }
  $lb_match_num = $wb_n ?? 0;
  $lb_rd_arr   = array_values($dko_lb);
  $lb_r1_count = count(reset($lb_rd_arr)['matches']);
  $lb_slot_h   = 100;
  $lb_total_h  = max(1, $lb_r1_count) * $lb_slot_h;
  $lb_col_w    = 220;
  $lb_total_w  = count($lb_rd_arr) * $lb_col_w;
?>
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold"><i class="bi bi-arrow-down-circle me-1 text-danger"></i>Losers Bracket</div>
  <div class="card-body p-3">
    <?php if (can_edit()): ?>
    <form id="dko-lb-form" method="post" action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>
    <div style="overflow-x:auto;margin-bottom:4px">
      <!-- Rundenüberschriften -->
      <div style="display:flex;width:<?= $lb_total_w ?>px">
        <?php foreach ($lb_rd_arr as $rd): ?>
        <div style="width:<?= $lb_col_w ?>px;flex-shrink:0;text-align:center;font-weight:600;font-size:.8rem;padding:0 8px 4px;color:#6c757d">
          <?= e($rd['name']) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <!-- Bracket-Körper -->
      <div id="dko-lb-bracket-<?= $c['id'] ?>" style="display:flex;position:relative;height:<?= $lb_total_h ?>px;width:<?= $lb_total_w ?>px">
        <?php foreach ($lb_rd_arr as $rd): ?>
        <div class="lb-round" style="display:flex;flex-direction:column;justify-content:space-around;width:<?= $lb_col_w ?>px;flex-shrink:0;height:100%;padding:0 8px">
          <?php foreach ($rd['matches'] as $m): $lb_match_num++; $lr = (int)$m['ko_round']; $lp = (int)$m['ko_position']; ?>
          <?= _dko_match_card(
              $m, 'dko-lb-form', can_edit(), $lb_match_num,
              !$m['player1_id'] ? ($lb_ph[$lr][$lp][1] ?? null) : null,
              !$m['player2_id'] ? ($lb_ph[$lr][$lp][2] ?? null) : null,
              []
          ) ?>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <svg id="dko-lb-svg-<?= $c['id'] ?>" style="position:absolute;top:0;left:0;pointer-events:none;overflow:visible"
             width="<?= $lb_total_w ?>" height="<?= $lb_total_h ?>"></svg>
      </div>
    </div>
    <?php if (can_edit()): ?>
    <div class="mt-2">
      <button form="dko-lb-form" type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-save me-1"></i>LB Ergebnisse speichern
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($dko_gf): ?>
<div class="card shadow-sm mb-4 border-primary">
  <div class="card-header fw-semibold text-primary"><i class="bi bi-star-fill me-1 text-warning"></i>Großes Finale</div>
  <div class="card-body p-3">
    <?php if (can_edit()): ?>
    <form id="dko-gf-form" method="post" action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>
    <div class="d-flex gap-3 align-items-start">
      <div style="min-width:200px;max-width:300px">
        <?= _dko_match_card($dko_gf, 'dko-gf-form', can_edit(), null, null, null, []) ?>
      </div>
      <div class="small text-muted align-self-center">
        <div><strong>Spieler 1:</strong> WB-Sieger (ungeschlagen)</div>
        <div><strong>Spieler 2:</strong> LB-Sieger (1 Niederlage)</div>
      </div>
    </div>
    <?php if (can_edit()): ?>
    <div class="mt-3">
      <button form="dko-gf-form" type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-save me-1"></i>Finale speichern
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php elseif ($c['mode'] === 'double_ko' && $c['phase'] === 'setup'): ?>
<!-- DKO: noch nicht ausgelost -->
<div class="alert alert-info">
  <i class="bi bi-info-circle me-1"></i>
  Doppel-KO-Bracket noch nicht ausgelost. Spieler hinzufügen und im Tab <strong>Einstellungen</strong> auslosen.
</div>
<?php endif; ?>

<?php
$extra_js = <<<'JS'
<script>
// Aktiven Tab aus URL-Hash wiederherstellen
(function() {
  if (window.location.hash === '#spieler') {
    var btn = document.getElementById('tab-players-btn');
    if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
  }
})();

// ── Group Drag-and-Drop ──────────────────────────────────────────
var grpDragEl = null, grpEditActive = false;
function toggleGrpEdit() {
  grpEditActive = !grpEditActive;
  document.querySelectorAll('.grp-normal-view').forEach(function(el) { el.style.display = grpEditActive ? 'none' : ''; });
  document.querySelectorAll('.grp-edit-panel').forEach(function(el) { el.style.display = grpEditActive ? '' : 'none'; });
  var tb = document.getElementById('grp-edit-toolbar'); if (tb) tb.style.display = grpEditActive ? 'flex' : 'none';
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
  var tb = document.getElementById('ko-edit-toolbar'); if (tb) tb.style.display = koEditActive ? 'flex' : 'none';
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
      // swap seeding badges
      var db = koDragEl.parentElement.querySelector('.ko-seed-badge');
      var sb = slot.parentElement.querySelector('.ko-seed-badge');
      var dt = db ? db.textContent : '', st = sb ? sb.textContent : '';
      function _mkBadge(txt) { var b = document.createElement('small'); b.className = 'ko-seed-badge text-muted'; b.style.cssText = 'font-size:.6rem;white-space:nowrap;flex-shrink:0'; b.textContent = txt; return b; }
      if (db && st) { db.textContent = st; } else if (!db && st) { koDragEl.parentElement.insertBefore(_mkBadge(st), koDragEl); }
      else if (db && !st) { db.remove(); }
      if (sb && dt) { sb.textContent = dt; } else if (!sb && dt) { slot.parentElement.insertBefore(_mkBadge(dt), slot); }
      else if (sb && !dt) { sb.remove(); }
    }
  }
});

// ── KO Bracket SVG ───────────────────────────────────────────────
function drawBracketEl(bracket, svg) {
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
function drawBracket(cid) {
  drawBracketEl(
    document.getElementById('ko-bracket-' + cid),
    document.getElementById('bracket-svg-' + cid)
  );
}
function bLine(svg, x1, y1, x2, y2) {
  var l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
  l.setAttribute('x1', x1); l.setAttribute('y1', y1);
  l.setAttribute('x2', x2); l.setAttribute('y2', y2);
  l.setAttribute('stroke', '#adb5bd'); l.setAttribute('stroke-width', '2');
  svg.appendChild(l);
}
// ── LB Verbindungslinien ─────────────────────────────────────────
function drawLbBracket(bracket, svg) {
  if (!bracket || !svg) return;
  svg.innerHTML = '';
  var bRect  = bracket.getBoundingClientRect();
  var rounds = bracket.querySelectorAll('.lb-round');
  for (var ri = 0; ri < rounds.length - 1; ri++) {
    var cur  = rounds[ri].querySelectorAll('.ko-match');
    var nxt  = rounds[ri + 1].querySelectorAll('.ko-match');
    if (ri % 2 === 0) {
      // Minor → Major: 1:1, gleiche Position → rechtwinkliger Verbinder
      for (var i = 0; i < cur.length; i++) {
        var ms = cur[i], mn = nxt[i];
        if (!ms || !mn) continue;
        var rs = ms.getBoundingClientRect(), rn = mn.getBoundingClientRect();
        var x1 = rs.right - bRect.left, y1 = (rs.top + rs.bottom) / 2 - bRect.top;
        var x2 = rn.left  - bRect.left, y2 = (rn.top + rn.bottom) / 2 - bRect.top;
        var xm = (x1 + x2) / 2;
        bLine(svg, x1, y1, xm, y1);
        bLine(svg, xm, y1, xm, y2);
        bLine(svg, xm, y2, x2, y2);
      }
    } else {
      // Major → Minor: 2:1 → WB-Stil (zwei Matches → eines)
      for (var ni = 0; ni < nxt.length; ni++) {
        var m1 = cur[ni * 2], m2 = cur[ni * 2 + 1], mn = nxt[ni];
        if (!m1 || !m2 || !mn) continue;
        var r1 = m1.getBoundingClientRect(), r2 = m2.getBoundingClientRect(), rn = mn.getBoundingClientRect();
        var x1 = r1.right - bRect.left, y1 = (r1.top + r1.bottom) / 2 - bRect.top;
        var x2 = r2.right - bRect.left, y2 = (r2.top + r2.bottom) / 2 - bRect.top;
        var xn = rn.left  - bRect.left, yn = (rn.top + rn.bottom) / 2 - bRect.top;
        var xm = (x1 + xn) / 2;
        bLine(svg, x1, y1, xm, y1);
        bLine(svg, x2, y2, xm, y2);
        bLine(svg, xm, y1, xm, y2);
        bLine(svg, xm, yn, xn, yn);
      }
    }
  }
}

function drawAllBrackets() {
  document.querySelectorAll('[id^="ko-bracket-"]').forEach(function(el) {
    drawBracket(el.id.replace('ko-bracket-', ''));
  });
  document.querySelectorAll('[id^="dko-wb-bracket-"]').forEach(function(el) {
    var cid = el.id.replace('dko-wb-bracket-', '');
    drawBracketEl(el, document.getElementById('dko-wb-svg-' + cid));
  });
  document.querySelectorAll('[id^="dko-lb-bracket-"]').forEach(function(el) {
    var cid = el.id.replace('dko-lb-bracket-', '');
    drawLbBracket(el, document.getElementById('dko-lb-svg-' + cid));
  });
}
document.addEventListener('DOMContentLoaded', drawAllBrackets);
window.addEventListener('resize', drawAllBrackets);
</script>
JS;
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
