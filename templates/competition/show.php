<?php
$phase_labels = ['setup'=>'Einrichtung','group'=>'Gruppenphase','ko'=>'KO-Phase','done'=>'Beendet'];
$phase_colors = ['setup'=>'bg-secondary','group'=>'bg-warning text-dark','ko'=>'bg-info text-dark','done'=>'bg-success'];
$locked = ($t && (int)($t['is_done'] ?? 0) === 1) || $c['phase'] === 'done';
$court_sg = court_label($t['sport'] ?? '');          // Spielplatz-Bezeichnung je Sportart (Singular)
$court_pl = court_label($t['sport'] ?? '', true);    // Plural (Einstellungen)
$sport_icons  = ['tischtennis'=>'🏓','tennis'=>'🎾','fussball'=>'⚽','cornhole'=>'🫘'];
$sport_labels = ['tischtennis'=>'Tischtennis','tennis'=>'Tennis','fussball'=>'Fußball','cornhole'=>'Cornhole'];
// Abschnittsüberschriften (Gruppen / KO-Runde / Kreuzspiele) nur zeigen, wenn der Bewerb
// mehr als eine Phase gleichzeitig darstellt (Gruppen UND Finalrunde).
$show_section_titles = !empty($groups)
    && (!empty($ko_rounds) || !empty($third_place_match) || !empty($cross_blocks));

ob_start(); ?>
<div class="d-flex justify-content-end mb-3">
  <a href="<?= url($t ? 'tournament/' . $t['id'] : '') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Zurück
  </a>
</div>
<!-- Titel: Turniername (verlinkt, mit Sportart-Symbol) – Bewerbname -->
<h2 class="mb-4 d-flex align-items-center flex-wrap gap-2">
  <?php if ($t): ?>
  <a href="<?= url('tournament/' . $t['id']) ?>" class="text-decoration-none text-reset d-inline-flex align-items-center">
    <?php if (!empty($t['sport']) && isset($sport_labels[$t['sport']])): ?>
      <?php if ($t['sport'] === 'cornhole'): ?>
      <img src="<?= url('static/cornhole_icon.svg') ?>" height="52" class="me-2" style="vertical-align:middle" alt="Cornhole">
      <?php else: ?>
      <span class="me-2" style="font-size:3rem;line-height:1" title="<?= e($sport_labels[$t['sport']]) ?>"><?= $sport_icons[$t['sport']] ?></span>
      <?php endif; ?>
    <?php endif; ?>
    <?= e($t['name']) ?>
  </a>
  <span class="text-muted">-</span>
  <?php endif; ?>
  <span><?= e($c['name']) ?></span>
</h2>

<!-- Header -->
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <?php if (can_edit()): ?>
  <span class="badge fs-6 <?= $phase_colors[$c['phase']] ?? 'bg-secondary' ?>">
    <?= e(phase_label($c['phase'], $c['mode'] ?? null)) ?>
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
  <?php endif; ?>

  <?php if (can_edit()): ?>
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <a href="<?= url('competition/'.$c['id'].'/monitor') ?>" target="_blank"
       class="btn btn-outline-secondary btn-sm" title="Monitoransicht (Vollbild für Anzeige/Beamer)">
      <i class="bi bi-display"></i>
    </a>
    <?php if (!$locked && in_array($c['phase'], ['group','ko'], true)): ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/settings') ?>?action=done">
      <?= csrf_field() ?><input type="hidden" name="mark_done" value="1">
      <button class="btn btn-success btn-sm"><i class="bi bi-check-circle me-1"></i>Als beendet markieren</button>
    </form>
    <?php endif; ?>
    <?php if ($c['phase'] === 'done' && !($t && (int)($t['is_done'] ?? 0) === 1)): ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/settings') ?>?action=reopen">
      <?= csrf_field() ?><input type="hidden" name="reopen" value="1">
      <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Wieder öffnen</button>
    </form>
    <?php endif; ?>
    <?php if (!$locked && $c['phase'] === 'group'): ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/reset/groups') ?>"
          data-confirm="Alle Gruppenspiel-Ergebnisse löschen?">
      <?= csrf_field() ?>
      <button class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Gruppe zurücksetzen</button>
    </form>
    <?php endif; ?>
    <?php if (!$locked && $c['phase'] === 'ko'): ?>
    <?php $reset_ko_lbl = $c['mode'] === 'groups_cross' ? 'Kreuzspiele zurücksetzen' : 'KO zurücksetzen'; ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/reset/ko') ?>"
          data-confirm="<?= e($reset_ko_lbl) ?>?">
      <?= csrf_field() ?>
      <button class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i><?= e($reset_ko_lbl) ?></button>
    </form>
    <?php endif; ?>
    <?php if (is_admin() && !$locked): ?>
    <span id="test-tools" data-duels-bulk-url="<?= e(url('competition/'.$c['id'].'/duels/bulk')) ?>" class="d-inline-flex gap-2">
      <button type="button" class="btn btn-outline-warning btn-sm" onclick="fillTestResults(true)" title="Testergebnisse eintragen &amp; speichern">
        <i class="bi bi-dice-5"></i>
      </button>
      <form method="post" action="<?= url('competition/'.$c['id'].'/results/clear-phase') ?>"
            data-confirm="Alle eingetragenen Ergebnisse der aktuellen Phase entfernen?" class="m-0">
        <?= csrf_field() ?>
        <button class="btn btn-outline-danger btn-sm" title="Testergebnisse entfernen"><i class="bi bi-eraser"></i></button>
      </form>
    </span>
    <?php endif; ?>
    <?php if (!$locked): ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/delete') ?>"
          data-confirm="Bewerb wirklich löschen?">
      <?= csrf_field() ?>
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Löschen</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($places && !empty($comp_complete)): ?>
<!-- Endplatzierung (erst wenn kein offenes Spiel mehr im gesamten Bewerb) -->
<div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#fff9db,#fff);">
  <div class="card-body">
    <h5 class="card-title mb-3"><i class="bi bi-trophy-fill text-warning me-2"></i>Endplatzierung</h5>
    <div class="d-flex flex-wrap gap-3">
      <?php foreach ($places as $pl): if ($pl['rank'] > 4) continue; ?>
      <div class="text-center p-3 border rounded bg-white">
        <div class="fs-2"><?= match($pl['rank']) { 1=>'🥇', 2=>'🥈', 3=>'🥉', default=>$pl['rank'].'.' } ?></div>
        <div class="fw-bold"><?= e($pl['name']) ?></div>
        <?php if ($pl['club']): ?><div class="text-muted small"><?= e($pl['club']) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php $rest_places = array_filter($places, fn($p) => $p['rank'] > 4); ?>
    <?php if ($rest_places): ?>
    <style>
      .more-places-btn .lbl-hide { display:none; }
      .more-places-btn:not(.collapsed) .lbl-show { display:none; }
      .more-places-btn:not(.collapsed) .lbl-hide { display:inline; }
    </style>
    <button class="btn btn-sm btn-outline-secondary mt-3 collapsed more-places-btn" type="button"
            data-bs-toggle="collapse" data-bs-target="#more-places-<?= $c['id'] ?>"
            aria-expanded="false" aria-controls="more-places-<?= $c['id'] ?>">
      <span class="lbl-show"><i class="bi bi-chevron-down me-1"></i>Weitere Plätze anzeigen (<?= count($rest_places) ?>)</span>
      <span class="lbl-hide"><i class="bi bi-chevron-up me-1"></i>Weitere Plätze ausblenden</span>
    </button>
    <div class="collapse" id="more-places-<?= $c['id'] ?>">
      <ul class="list-group list-group-flush mt-2 mb-0">
        <?php foreach ($rest_places as $pl): ?>
        <li class="list-group-item bg-transparent px-0 py-1 d-flex align-items-baseline">
          <span class="fw-semibold text-muted text-end me-2" style="min-width:2.4rem"><?= (int)$pl['rank'] ?>.</span>
          <span class="fw-semibold"><?= e($pl['name']) ?></span>
          <?php if ($pl['club']): ?><span class="text-muted small ms-2"><?= e($pl['club']) ?></span><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ═══ Registerkarten: Bewerb + Spieler [+ Einstellungen] ═════════════════ -->
<?php if (can_edit()): /* Gäste/Reader haben nur den Bewerb-Tab → Leiste ausblenden, Inhalt direkt zeigen */ ?>
<ul class="nav nav-tabs mb-0" id="comp-tabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-competition-btn"
            data-bs-toggle="tab" data-bs-target="#tab-competition" type="button" role="tab">
      <i class="bi bi-diagram-3 me-1"></i>Bewerb
    </button>
  </li>
  <?php if (can_edit()): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-players-btn"
            data-bs-toggle="tab" data-bs-target="#tab-players" type="button" role="tab">
      <?php if ($is_team): ?>
      <i class="bi bi-shield-fill me-1"></i>Teams (<?= count($assigned_teams) ?>)
      <?php elseif ($is_doubles): ?>
      <i class="bi bi-people-fill me-1"></i>Doppel (<?= count($assigned_doubles) ?>)
      <?php else: ?>
      <i class="bi bi-people me-1"></i>Spieler (<?= count($assigned) ?>)
      <?php endif; ?>
      <?php if ($c['max_players']): ?><span class="text-muted small">/ <?= (int)$c['max_players'] ?></span><?php endif; ?>
    </button>
  </li>
  <?php endif; ?>
  <?php if ((can_edit() && !$locked)): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-settings-btn"
            data-bs-toggle="tab" data-bs-target="#tab-settings" type="button" role="tab">
      <i class="bi bi-gear me-1"></i>Einstellungen
    </button>
  </li>
  <?php endif; ?>
  <?php if (can_edit()): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-monitor-btn"
            data-bs-toggle="tab" data-bs-target="#tab-monitor" type="button" role="tab">
      <i class="bi bi-display me-1"></i>Monitor
    </button>
  </li>
  <?php endif; ?>
</ul>
<?php endif; ?>
<div class="tab-content<?= can_edit() ? ' border border-top-0 rounded-bottom' : '' ?> mb-4">

  <?php if (can_edit()): ?>
  <!-- Tab: Monitor -->
  <div class="tab-pane fade p-3" id="tab-monitor" role="tabpanel">
    <form method="post" action="<?= url('competition/'.$c['id'].'/monitor-settings') ?>" class="row g-3 align-items-end">
      <?= csrf_field() ?>
      <div class="col-12">
        <a href="<?= url('competition/'.$c['id'].'/monitor') ?>" target="_blank" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-display me-1"></i>Monitoransicht öffnen
        </a>
      </div>
      <div class="col-auto d-flex align-items-end pb-1">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="monitor_show_schedule" id="monitor_show_schedule"
                 <?= !empty($c['monitor_show_schedule']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="monitor_show_schedule">Spielplan in der Gruppenphase anzeigen</label>
        </div>
      </div>
      <div class="col-auto">
        <label class="form-label">Scrollgeschwindigkeit</label>
        <select name="monitor_scroll_speed" class="form-select form-select-sm">
          <option value="slow"  <?= ($c['monitor_scroll_speed'] ?? 'medium') === 'slow'   ? ' selected' : '' ?>>Langsam</option>
          <option value="medium"<?= ($c['monitor_scroll_speed'] ?? 'medium') === 'medium' ? ' selected' : '' ?>>Mittel</option>
          <option value="fast"  <?= ($c['monitor_scroll_speed'] ?? 'medium') === 'fast'   ? ' selected' : '' ?>>Schnell</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label">Scrollmodus</label>
        <select name="monitor_scroll_mode" class="form-select form-select-sm" id="monitor_scroll_mode">
          <option value="smooth"<?= ($c['monitor_scroll_mode'] ?? 'smooth') === 'smooth' ? ' selected' : '' ?>>Gleichmäßig</option>
          <option value="block" <?= ($c['monitor_scroll_mode'] ?? 'smooth') === 'block'  ? ' selected' : '' ?>>Blockweise</option>
        </select>
      </div>
      <div class="col-auto" id="field-block-pause"<?= ($c['monitor_scroll_mode'] ?? 'smooth') !== 'block' ? ' style="display:none"' : '' ?>>
        <label class="form-label">Verweildauer je Block (Sek.)</label>
        <input type="number" name="monitor_block_pause" class="form-control form-control-sm" style="width:120px"
               min="1" max="120" value="<?= (int)($c['monitor_block_pause'] ?? 5) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label">Gruppentabellen nebeneinander</label>
        <select name="monitor_max_cols" class="form-select form-select-sm">
          <option value="0"<?= (int)($c['monitor_max_cols'] ?? 0) === 0 ? ' selected' : '' ?>>Automatisch</option>
          <?php for ($n = 1; $n <= 8; $n++): ?>
          <option value="<?= $n ?>"<?= (int)($c['monitor_max_cols'] ?? 0) === $n ? ' selected' : '' ?>>max. <?= $n ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Speichern</button>
      </div>
    </form>
    <div class="form-text mt-2">
      Beim <strong>blockweisen</strong> Scrollen wird nacheinander zu jedem Abschnitt (Endplatzierung,
      Gruppen, KO-/Finalrunden-Bereiche) gescrollt und dort die eingestellte Zeit verweilt.
      <br><strong>Gruppentabellen nebeneinander</strong>: begrenzt die Anzahl der Tabellen pro Reihe;
      bei weniger Gruppen werden die Tabellen nicht breiter dargestellt. „Automatisch" füllt die volle Breite.
    </div>
    <script>
    (function() {
      var sel = document.getElementById('monitor_scroll_mode');
      var fld = document.getElementById('field-block-pause');
      if (!sel || !fld) return;
      sel.addEventListener('change', function() { fld.style.display = this.value === 'block' ? '' : 'none'; });
    })();
    </script>
  </div>
  <?php endif; ?>

  <?php if ((can_edit() && !$locked)): ?>
  <!-- Tab: Einstellungen -->
  <div class="tab-pane fade p-3" id="tab-settings" role="tabpanel">
    <?php
      $can_change_type = $c['phase'] === 'setup'
          && count($assigned) === 0 && count($assigned_doubles) === 0 && count($assigned_teams) === 0;
      $comp_type = !empty($c['is_team']) ? 'team' : (!empty($c['is_doubles']) ? 'doubles' : 'single');
    ?>
    <?php
      $ui_mode    = $c['mode'] === 'groups_cross' ? 'groups_ko' : $c['mode'];
      $finalrunde = $c['mode'] === 'groups_cross' ? 'cross' : ((int)$c['advance_count'] >= 1 ? 'ko' : 'none');
      $ts_editable = ($c['phase'] === 'setup') || !empty($group_no_results);
      $is_ko_mode  = in_array($c['mode'], ['ko_only', 'double_ko'], true);
    ?>
    <style>
      #tab-settings .opt-section { margin-top:1.5rem; }
      #tab-settings .opt-section:first-of-type { margin-top:.25rem; }
      #tab-settings .opt-section > .opt-head {
        font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em;
        color:#6c757d; border-bottom:1px solid var(--bs-border-color); padding-bottom:.25rem; margin-bottom:.1rem;
      }
    </style>
    <form method="post" action="<?= url('competition/'.$c['id'].'/settings') ?>" class="row g-3 align-items-end">
      <?= csrf_field() ?>

      <!-- ── Allgemein ── -->
      <div class="col-12 opt-section"><div class="opt-head"><i class="bi bi-info-circle me-1"></i>Allgemein</div></div>
      <div class="col-auto">
        <label class="form-label">Bewerbsname</label>
        <input type="text" name="name" class="form-control form-control-sm" style="min-width:180px"
               value="<?= e($c['name']) ?>" required>
      </div>
      <div class="col-auto">
        <label class="form-label">Bewerbstyp</label>
        <select name="comp_type" class="form-select form-select-sm"<?= !$can_change_type ? ' disabled' : '' ?>>
          <option value="single"<?= $comp_type === 'single'  ? ' selected' : '' ?>>Einzelbewerb</option>
          <option value="doubles"<?= $comp_type === 'doubles' ? ' selected' : '' ?>>Doppelbewerb</option>
          <option value="team"<?= $comp_type === 'team'    ? ' selected' : '' ?>>Teambewerb</option>
        </select>
        <?php if (!$can_change_type): ?>
        <input type="hidden" name="comp_type" value="<?= $comp_type ?>">
        <?php endif; ?>
      </div>
      <div class="col-auto">
        <label class="form-label">Max. Teilnehmer</label>
        <input type="number" name="max_players" class="form-control form-control-sm" style="width:90px"
               value="<?= (int)$c['max_players'] ?>" min="0">
      </div>
      <div class="col-auto d-flex align-items-end pb-1">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="registrations_open" id="regs_open"
                 <?= $c['registrations_open'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="regs_open">Nennung offen</label>
        </div>
      </div>

      <!-- ── Spielmodus & Format ── -->
      <div class="col-12 opt-section"><div class="opt-head"><i class="bi bi-diagram-3 me-1"></i>Spielmodus &amp; Format</div></div>
      <div class="col-auto">
        <label class="form-label">Spielmodus</label>
        <select name="mode" class="form-select form-select-sm" id="comp-mode-edit"<?= $c['phase'] !== 'setup' ? ' disabled' : '' ?>
                onchange="editToggleCross()">
          <option value="groups_ko"<?= $ui_mode === 'groups_ko' ? ' selected' : '' ?>>Gruppenphase</option>
          <option value="ko_only"<?= $ui_mode === 'ko_only'   ? ' selected' : '' ?>>KO-Modus</option>
          <option value="double_ko"<?= $ui_mode === 'double_ko' ? ' selected' : '' ?>>Doppel-KO Modus</option>
        </select>
        <?php if ($c['phase'] !== 'setup'): ?>
        <input type="hidden" name="mode" value="<?= e($ui_mode) ?>">
        <?php endif; ?>
      </div>
      <?php if (!$is_ko_mode): ?>
      <div class="col-auto">
        <label class="form-label">Gruppengröße</label>
        <select name="group_size" class="form-select form-select-sm"<?= $c['phase'] !== 'setup' ? ' disabled' : '' ?>>
          <?php foreach (range(3, 24) as $s): ?>
          <option value="<?= $s ?>"<?= (int)$c['group_size'] === $s ? ' selected' : '' ?>><?= $s ?> Teilnehmer</option>
          <?php endforeach; ?>
        </select>
        <?php if ($c['phase'] !== 'setup'): ?>
        <input type="hidden" name="group_size" value="<?= (int)$c['group_size'] ?>">
        <?php endif; ?>
      </div>
      <div class="col-auto" id="finalrunde-wrap">
        <label class="form-label">Finalrunde</label>
        <select name="finalrunde" id="finalrunde-edit" class="form-select form-select-sm" onchange="editToggleCross()">
          <option value="none"<?= $finalrunde === 'none' ? ' selected' : '' ?>>nur Gruppenphase</option>
          <option value="ko"<?= $finalrunde === 'ko'   ? ' selected' : '' ?>>KO-Runde</option>
          <option value="cross"<?= $finalrunde === 'cross' ? ' selected' : '' ?>>Kreuzspiele</option>
        </select>
      </div>
      <div class="col-auto" id="advance-wrap-edit" style="display:<?= $finalrunde === 'ko' ? '' : 'none' ?>">
        <label class="form-label">Aufsteiger</label>
        <select name="advance_count" id="advance_count" class="form-select form-select-sm">
          <option value="1"<?= (int)$c['advance_count'] !== 2 ? ' selected' : '' ?>>1 (Gruppenerste)</option>
          <option value="2"<?= (int)$c['advance_count'] === 2 ? ' selected' : '' ?>>2 (Erste &amp; Zweite)</option>
        </select>
      </div>
      <div class="col-auto" id="field-round-limit" style="display:<?= $finalrunde === 'none' ? '' : 'none' ?>">
        <label class="form-label">Rundenanzahl <span class="text-muted small">(0 = alle)</span></label>
        <input type="number" name="round_limit" class="form-control form-control-sm" style="width:110px"
               min="0" max="50" value="<?= (int)($c['round_limit'] ?? 0) ?: '' ?>" placeholder="alle"
               title="Spielplan nur für so viele Runden erstellen (leer/0 = vollständiger Spielplan).">
      </div>
      <?php
        $gs_cc = (int)$c['group_size']; $ntiers_cc = max(1, (int)ceil($gs_cc / 2));
        $cfg_cc = ($c['cross_config'] ?? '') !== '' ? explode(',', $c['cross_config']) : [];
      ?>
      <div class="col-12" id="cross-config-wrap" style="display:<?= $finalrunde === 'cross' ? '' : 'none' ?>">
        <label class="form-label">Kreuzspiele – Paarungen je Rang</label>
        <div class="d-flex flex-wrap gap-3">
          <?php for ($t = 1; $t <= $ntiers_cc; $t++):
            $a = 2 * $t - 1; $b = 2 * $t;
            $rl  = $b <= $gs_cc ? "Rang {$a}+{$b}" : "Rang {$a}";
            $val = $cfg_cc[$t - 1] ?? 'x'; ?>
          <div>
            <div class="small text-muted mb-1"><?= $rl ?></div>
            <select name="cross_config[]" class="form-select form-select-sm" style="width:auto">
              <option value="x"<?= $val !== 's' ? ' selected' : '' ?>>über Kreuz</option>
              <option value="s"<?= $val === 's' ? ' selected' : '' ?>>getrennt</option>
            </select>
          </div>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="col-auto d-flex align-items-end pb-1"
           id="third_place_wrap"<?= ($is_ko_mode || $finalrunde === 'ko') ? '' : ' style="display:none !important"' ?>>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="third_place" id="third_place"
                 <?= $c['third_place'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="third_place">Platz-3-Spiel</label>
        </div>
      </div>
      <div class="col-auto d-flex align-items-end pb-1" id="show-seeding-wrap"<?= $is_ko_mode ? '' : ' style="display:none !important"' ?>>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="show_seeding" id="show_seeding"
                 <?= ($c['show_seeding'] ?? 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="show_seeding">Setzungen anzeigen (KO)</label>
        </div>
      </div>

      <!-- ── Mannschaft (Teambewerb) ── -->
      <div class="col-12 opt-section" id="sec-team-hd"<?= $comp_type !== 'team' ? ' style="display:none"' : '' ?>>
        <div class="opt-head"><i class="bi bi-people me-1"></i>Mannschaft</div>
      </div>
      <div class="col-auto" id="field-team-size"<?= $comp_type !== 'team' ? ' style="display:none"' : '' ?>>
        <label class="form-label">Spiele pro Team</label>
        <input type="number" name="team_size" class="form-control form-control-sm" style="width:90px"
               min="0" max="20" value="<?= (int)($c['team_size'] ?? 0) ?>"<?= !$ts_editable ? ' disabled' : '' ?>>
        <?php if (!$ts_editable): ?>
        <input type="hidden" name="team_size" value="<?= (int)($c['team_size'] ?? 0) ?>">
        <?php endif; ?>
      </div>
      <div class="col-auto" id="field-team-result"<?= $comp_type !== 'team' ? ' style="display:none"' : '' ?>>
        <label class="form-label">Begegnungsergebnis</label>
        <select name="team_result_mode" class="form-select form-select-sm">
          <option value="wins"<?= ($c['team_result_mode'] ?? 'wins') === 'wins' ? ' selected' : '' ?>>Je Einzelsieg 1 Punkt</option>
          <option value="sum" <?= ($c['team_result_mode'] ?? 'wins') === 'sum'  ? ' selected' : '' ?>>Einzelergebnisse aufsummieren</option>
          <option value="total"<?= ($c['team_result_mode'] ?? 'wins') === 'total' ? ' selected' : '' ?>>Nur Gesamtergebnis eingeben</option>
        </select>
      </div>
      <div class="col-auto d-flex align-items-end pb-1" id="field-kickoff"<?= $comp_type !== 'team' ? ' style="display:none"' : '' ?>>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="kickoff_enabled" id="kickoff_enabled"
                 <?= !empty($c['kickoff_enabled']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="kickoff_enabled">Anwurf auslosen</label>
        </div>
      </div>
      <div class="col-auto" id="field-match-card"<?= $comp_type !== 'team' ? ' style="display:none"' : '' ?>>
        <label class="form-label">Match-Cards</label>
        <select name="match_card_mode" class="form-select form-select-sm">
          <option value="fields" <?= ($c['match_card_mode'] ?? 'fields') === 'fields'  ? ' selected' : '' ?>>mit Spielerfelder</option>
          <option value="compact"<?= ($c['match_card_mode'] ?? 'fields') === 'compact' ? ' selected' : '' ?>>ohne Spielerfelder</option>
        </select>
      </div>

      <!-- ── Wertung & Setzung ── -->
      <div class="col-12 opt-section"><div class="opt-head"><i class="bi bi-trophy me-1"></i>Wertung &amp; Setzung</div></div>
      <?php if (!$is_team): ?>
      <div class="col-auto">
        <label class="form-label">Ergebniserfassung</label>
        <select name="score_mode" class="form-select form-select-sm">
          <option value="match"   <?= ($c['score_mode'] ?? 'match') === 'match'    ? ' selected' : '' ?>>Spielergebnis</option>
          <option value="sets"    <?= ($c['score_mode'] ?? 'match') === 'sets'     ? ' selected' : '' ?>>Satzergebnisse</option>
          <option value="sets_grp"<?= ($c['score_mode'] ?? 'match') === 'sets_grp' ? ' selected' : '' ?>>Gruppe Sätze, KO Spielergebnis</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <label class="form-label">Setzungsreihenfolge</label>
        <select name="seeding_order" class="form-select form-select-sm">
          <option value="desc"<?= ($c['seeding_order'] ?? 'desc') === 'desc' ? ' selected' : '' ?>>Höhere Spielstärke = stärker</option>
          <option value="asc"<?= ($c['seeding_order'] ?? 'desc') === 'asc'  ? ' selected' : '' ?>>Niedrigere Spielstärke = stärker</option>
          <option value="random"<?= ($c['seeding_order'] ?? 'desc') === 'random' ? ' selected' : '' ?>>Zufällig (keine Setzung)</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label">Tabellenreihung</label>
        <select name="standings_order" class="form-select form-select-sm">
          <option value="h2h"<?= ($c['standings_order'] ?? 'h2h') === 'h2h'  ? ' selected' : '' ?>>Punkte – Direktes Duell – Differenz</option>
          <option value="diff"<?= ($c['standings_order'] ?? 'h2h') === 'diff' ? ' selected' : '' ?>>Punkte – Differenz – Direktes Duell</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label">Punktevergabe</label>
        <select name="points_mode" class="form-select form-select-sm">
          <option value="2-1-0"<?= ($c['points_mode'] ?? '2-1-0') === '2-1-0' ? ' selected' : '' ?>>Sieg 2 – Unentsch. 1 – Niederl. 0</option>
          <option value="3-1-0"<?= ($c['points_mode'] ?? '2-1-0') === '3-1-0' ? ' selected' : '' ?>>Sieg 3 – Unentsch. 1 – Niederl. 0</option>
          <option value="3-2-1"<?= ($c['points_mode'] ?? '2-1-0') === '3-2-1' ? ' selected' : '' ?>>Sieg 3 – Unentsch. 2 – Niederl. 1</option>
        </select>
      </div>

      <!-- ── Spielplan & Zeit ── -->
      <div class="col-12 opt-section"><div class="opt-head"><i class="bi bi-calendar3 me-1"></i>Spielplan &amp; Zeit</div></div>
      <?php if (!$is_ko_mode): ?>
      <div class="col-auto d-flex align-items-end pb-1">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="show_byes" id="show_byes"
                 <?= !empty($c['show_byes']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="show_byes">Spielrunden anzeigen</label>
        </div>
      </div>
      <div class="col-auto d-flex align-items-end pb-1">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="force_byes" id="force_byes"
                 <?= !empty($c['force_byes']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="force_byes" title="Wirkt erst bei der nächsten Auslosung. Bei gerader Anzahl wird der Spielplan um eine Runde länger.">Spielfreie Runde garantieren</label>
        </div>
      </div>
      <div class="col-auto">
        <label class="form-label">Spielplan-Erstellung</label>
        <select name="schedule_mode" class="form-select form-select-sm">
          <option value="random"<?= ($c['schedule_mode'] ?? 'random') === 'random' ? ' selected' : '' ?>>Zufällig</option>
          <option value="position"<?= ($c['schedule_mode'] ?? 'random') === 'position' ? ' selected' : '' ?>>Nach Position</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <label class="form-label"><?= e($court_pl) ?> <span class="text-muted small">(0 = aus)</span></label>
        <input type="number" name="num_courts" class="form-control form-control-sm" style="width:90px"
               value="<?= (int)($c['num_courts'] ?? 0) ?>" min="0" max="20">
      </div>
      <div class="col-auto">
        <label class="form-label">ab Nr. <span class="text-muted small">(Start)</span></label>
        <input type="number" name="court_start" class="form-control form-control-sm" style="width:90px"
               value="<?= (int)($c['court_start'] ?? 1) ?>" min="1" max="200"
               title="Ab welcher <?= e($court_sg) ?>-Nummer gezählt wird (Standard 1).">
      </div>
      <div class="col-auto d-flex align-items-end pb-1">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="schedule_enabled" id="schedule_enabled"
                 <?= !empty($c['schedule_enabled']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="schedule_enabled"
                 title="Nur verfügbar, wenn Spielplätze und Spielrunden aktiv sind.">Zeitplan</label>
        </div>
      </div>
      <div class="col-auto" id="schedule-fields-wrap" style="display:<?= !empty($c['schedule_enabled']) ? '' : 'none' ?>">
        <div class="row g-2 align-items-end">
          <div class="col-auto">
            <label class="form-label">Spieldauer/Runde <span class="text-muted small">(Min.)</span></label>
            <input type="number" name="schedule_duration" id="schedule_duration" class="form-control form-control-sm" style="width:100px"
                   value="<?= (int)($c['schedule_duration'] ?? 0) ?: '' ?>" min="1" max="600">
          </div>
          <div class="col-auto">
            <label class="form-label">Startzeit</label>
            <input type="time" name="schedule_start" id="schedule_start" class="form-control form-control-sm" style="width:110px"
                   value="<?= e($c['schedule_start'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- ── Anzeige & Druck ── -->
      <div class="col-12 opt-section"><div class="opt-head"><i class="bi bi-printer me-1"></i>Anzeige &amp; Druck</div></div>
      <div class="col-auto d-flex align-items-end pb-1">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="show_skill" id="show_skill"
                 <?= ($c['show_skill'] ?? 0) ? 'checked' : '' ?>>
          <label class="form-check-label" for="show_skill">Spielstärke anzeigen (Gruppe)</label>
        </div>
      </div>
      <div class="col-auto d-flex align-items-end pb-1">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="mc_separate_page" id="mc_separate_page"
                 <?= !empty($c['mc_separate_page']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="mc_separate_page"
                 title="Match-Cards, Teampläne und Bahnpläne: jede Karte bzw. Übersicht auf einer eigenen Seite.">separate Seite für Match-Cards</label>
        </div>
      </div>

      <div class="col-12">
        <button class="btn btn-primary btn-sm">Speichern</button>
      </div>
    </form>
    <?php if ((int)($c['num_courts'] ?? 0) > 0 && !empty($groups)):
      $cstart = max(1, (int)($c['court_start'] ?? 1));
      $chi    = $cstart + (int)$c['num_courts'] - 1;
      $cex    = $cstart . ',' . ($cstart + 1);
    ?>
    <div class="card shadow-sm mt-3">
      <div class="card-header fw-semibold py-2">
        <i class="bi bi-geo-alt me-1"></i><?= e($court_pl) ?> pro Gruppe
        <span class="text-muted small fw-normal">(Nr. <?= $cstart ?>–<?= $chi ?> · z.B. „<?= e($cex) ?>")</span>
      </div>
      <div class="card-body py-2">
        <form method="post" action="<?= url('competition/'.$c['id'].'/courts') ?>" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <?php foreach ($groups as $gi2): $g2 = $gi2['group']; ?>
          <div class="col-auto">
            <label class="form-label small mb-0"><?= e($g2['name']) ?></label>
            <input type="text" name="courts[<?= (int)$g2['id'] ?>]" class="form-control form-control-sm" style="width:110px"
                   value="<?= e($g2['courts'] ?? '') ?>" placeholder="z.B. <?= e($cex) ?>">
          </div>
          <?php endforeach; ?>
          <div class="col-auto">
            <button class="btn btn-primary btn-sm"><?= e($court_pl) ?> speichern</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($c['schedule_enabled']) && !empty($c['show_byes']) && !empty($groups)): ?>
    <div class="card shadow-sm mt-3">
      <div class="card-header fw-semibold py-2">
        <i class="bi bi-pause-circle me-1"></i>Pause pro Gruppe
        <span class="text-muted small fw-normal">(Zeitpunkt + Dauer in Min.; leer = keine Pause)</span>
      </div>
      <div class="card-body py-2">
        <form method="post" action="<?= url('competition/'.$c['id'].'/pauses') ?>" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <?php foreach ($groups as $gi3): $g3 = $gi3['group']; ?>
          <div class="col-auto">
            <label class="form-label small mb-0"><?= e($g3['name']) ?></label>
            <div class="d-flex gap-1">
              <input type="time" name="pause_start[<?= (int)$g3['id'] ?>]" class="form-control form-control-sm" style="width:105px"
                     value="<?= e($g3['pause_start'] ?? '') ?>" title="Pausen-Zeitpunkt">
              <input type="number" name="pause_dur[<?= (int)$g3['id'] ?>]" class="form-control form-control-sm" style="width:78px"
                     min="0" max="600" value="<?= (int)($g3['pause_duration'] ?? 0) ?: '' ?>" placeholder="Min." title="Pausendauer (Min.)">
            </div>
          </div>
          <?php endforeach; ?>
          <div class="col-auto">
            <button class="btn btn-primary btn-sm">Pausen speichern</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <?php if (empty($c['schedule_enabled']) && !empty($groups)): ?>
    <div class="card shadow-sm mt-3">
      <div class="card-header fw-semibold py-2">
        <i class="bi bi-pause-circle me-1"></i>Pause pro Gruppe
        <span class="text-muted small fw-normal">(Spielrunde, nach der die Pause sein soll; leer = keine Pause. Optionaler Text, sonst „Pause")</span>
      </div>
      <div class="card-body py-2">
        <form method="post" action="<?= url('competition/'.$c['id'].'/round-pauses') ?>" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <?php foreach ($groups as $gi4): $g4 = $gi4['group'];
            $max_round = 0;
            foreach (($gi4['matches'] ?? []) as $mm4) { $max_round = max($max_round, (int)($mm4['round_no'] ?? 0)); }
          ?>
          <div class="col-auto">
            <label class="form-label small mb-0"><?= e($g4['name']) ?></label>
            <div class="d-flex gap-1">
              <div class="input-group input-group-sm" style="width:150px">
                <span class="input-group-text">nach Runde</span>
                <input type="number" name="pause_after_round[<?= (int)$g4['id'] ?>]" class="form-control form-control-sm"
                       min="1" max="<?= $max_round > 0 ? $max_round - 1 : 100 ?>"
                       value="<?= (int)($g4['pause_after_round'] ?? 0) ?: '' ?>" placeholder="–"
                       title="Pause nach dieser Spielrunde<?= $max_round > 0 ? ' (1–'.($max_round-1).')' : '' ?>">
              </div>
              <input type="text" name="pause_label[<?= (int)$g4['id'] ?>]" class="form-control form-control-sm" style="width:140px"
                     value="<?= e($g4['pause_label'] ?? '') ?>" placeholder="Text (optional)" maxlength="255"
                     title="Anzeigetext der Pause (leer = „Pause")">
            </div>
          </div>
          <?php endforeach; ?>
          <div class="col-auto">
            <button class="btn btn-primary btn-sm">Pausen speichern</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- /tab-settings -->
  <?php endif; ?>

  <!-- Tab: Spielerliste / Doppelliste -->
  <div class="tab-pane fade p-3" id="tab-players" role="tabpanel">
    <?php if ($is_doubles): ?>
    <!-- ── Doppel-Verwaltung ── -->
    <?php if ($assigned_doubles): ?>
    <div class="btn-group btn-group-sm mb-3">
      <a href="<?= url('competition/'.$c['id'].'/players/pdf') ?>" class="btn btn-outline-danger" target="_blank" title="PDF">
        <i class="bi bi-file-earmark-pdf"></i>
      </a>
      <a href="<?= url('competition/'.$c['id'].'/players/csv') ?>" class="btn btn-outline-success" title="CSV">
        <i class="bi bi-filetype-csv"></i>
      </a>
    </div>
    <h6 class="mb-2"><i class="bi bi-people me-1 text-secondary"></i>Zugeteilte Doppel</h6>
    <div class="mb-2 d-flex align-items-center gap-2">
      <input type="search" class="form-control form-control-sm table-filter" style="max-width:220px"
             placeholder="Filtern…" data-target="tbl-comp-doubles" aria-label="Doppel filtern">
      <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked)): ?>
      <form method="post" action="<?= url('competition/'.$c['id'].'/doubles/remove-all') ?>"
            class="js-ajax" data-refresh="#tab-players"
            data-confirm="Alle Doppel aus dem Bewerb entfernen?">
        <?= csrf_field() ?>
        <button class="btn btn-outline-danger btn-sm text-nowrap">
          <i class="bi bi-x-circle me-1"></i>Alle entfernen
        </button>
      </form>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0" data-sortable id="tbl-comp-doubles">
        <thead class="table-light">
          <tr>
            <th>Doppel</th><th>Spieler 1</th><th>Spieler 2</th><th class="text-center">Spielstärke</th><th>Angemeldet</th>
            <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked)): ?><th class="no-sort"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($assigned_doubles as $d): ?>
        <tr>
          <td class="small fw-semibold"><?= e($d['name']) ?></td>
          <td class="small text-muted"><?= e($d['p1name']) ?><?php if ($d['p1club']): ?> <small class="text-muted">(<?= e($d['p1club']) ?>)</small><?php endif; ?></td>
          <td class="small text-muted"><?= e($d['p2name']) ?><?php if ($d['p2club']): ?> <small class="text-muted">(<?= e($d['p2club']) ?>)</small><?php endif; ?></td>
          <td class="text-center" data-sort="<?= (float)$d['skill'] ?>">
            <?php
              $d_comp  = (float)($d['skill'] ?? 0);
              $d_reg   = (float)($d['registry_skill'] ?? 0);
              $d_diff  = in_array($t['sport'] ?? '', ['tischtennis', 'tennis']) && abs($d_comp - $d_reg) > 0.049;
            ?>
            <?php if ((can_edit() && !$locked)): ?>
            <div class="d-inline-flex flex-column align-items-center gap-1">
              <form method="post" action="<?= url('competition/'.$c['id'].'/double/'.$d['id'].'/skill') ?>"
                    class="d-inline-flex align-items-center gap-1 js-ajax" data-refresh="#tab-players">
                <?= csrf_field() ?>
                <input type="number" name="skill" value="<?= (int)$d_comp ?>"
                       min="0" class="form-control form-control-sm text-center<?= $d_diff ? ' border-warning' : '' ?>" style="width:5rem">
                <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1" title="Speichern">
                  <i class="bi bi-check-lg"></i>
                </button>
              </form>
              <?php if ($d_diff): ?>
              <div class="d-flex align-items-center gap-1 small" style="color:#fd7e14">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Aktuell: <?= (int)$d_reg ?></span>
                <form method="post" action="<?= url('competition/'.$c['id'].'/double/'.$d['id'].'/skill') ?>"
                      class="js-ajax" data-refresh="#tab-players">
                  <?= csrf_field() ?>
                  <input type="hidden" name="skill" value="<?= (int)$d_reg ?>">
                  <button type="submit" class="btn btn-link btn-sm p-0 lh-1" style="color:#fd7e14" title="Auf aktuellen Wert vom Spielerregister setzen">
                    <i class="bi bi-arrow-clockwise"></i>
                  </button>
                </form>
              </div>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <?php if ($d_comp): ?>
            <span class="badge bg-secondary"><?= (int)$d_comp ?></span>
            <?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="small text-muted" data-sort="<?= e($d['reg_date']) ?>"><?= e(fmtdate($d['reg_date'])) ?></td>
          <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked)): ?>
          <td>
            <form method="post" action="<?= url('competition/'.$c['id'].'/double/'.$d['id'].'/remove') ?>"
                  class="js-ajax" data-refresh="#tab-players"
                  data-confirm="Doppel aus dem Bewerb entfernen?">
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

    <?php if ($c['phase'] === 'setup' && !empty($confirmed_regs) && (can_edit() && !$locked)): ?>
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
      <form method="post" action="<?= url('competition/'.$c['id'].'/double/pair') ?>" class="row g-2 align-items-end js-ajax" data-refresh="#tab-players">
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

    <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked)): ?>
    <?php if ($unassigned_doubles): ?>
    <div class="mt-3 pt-3 border-top">
      <h6 class="mb-2"><i class="bi bi-plus-circle me-1 text-primary"></i>Doppel hinzufügen</h6>
      <form method="post" action="<?= url('competition/'.$c['id'].'/double/add') ?>" class="js-ajax" data-refresh="#tab-players">
        <?= csrf_field() ?>
        <div class="d-flex gap-2 mb-1">
          <a href="#" class="small text-muted add-select-all">Alle</a>
          <a href="#" class="small text-muted add-select-none">Keine</a>
        </div>
        <div class="border rounded add-entry-list mb-2" style="max-height:220px;overflow-y:auto">
          <?php foreach ($unassigned_doubles as $d): ?>
          <label class="add-entry-item d-flex align-items-center gap-2 px-2 py-1 border-bottom mb-0 user-select-none" style="cursor:pointer">
            <input type="checkbox" name="double_ids[]" value="<?= $d['id'] ?>" class="form-check-input mt-0 flex-shrink-0">
            <span class="small">
              <?= e($d['name']) ?>
              <?php if ($d['skill']): ?><span class="badge bg-secondary ms-1"><?= (int)$d['skill'] ?></span><?php endif; ?>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus me-1"></i>Doppel hinzufügen</button>
      </form>
    </div>
    <?php elseif (!$unassigned_doubles && $t): ?>
    <div class="mt-3 pt-3 border-top">
      <p class="text-muted small mb-1">Alle vorhandenen Doppel sind bereits eingetragen.</p>
      <a href="<?= url('players#tab-doppel') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-plus-circle me-1"></i>Doppel verwalten
      </a>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php elseif ($is_team): ?>
    <!-- ── Team-Verwaltung ── -->
    <?php if ($assigned_teams): ?>
    <div class="btn-group btn-group-sm mb-3">
      <a href="<?= url('competition/'.$c['id'].'/players/pdf') ?>" class="btn btn-outline-danger" target="_blank" title="PDF">
        <i class="bi bi-file-earmark-pdf"></i>
      </a>
      <a href="<?= url('competition/'.$c['id'].'/players/csv') ?>" class="btn btn-outline-success" title="CSV">
        <i class="bi bi-filetype-csv"></i>
      </a>
    </div>
    <h6 class="mb-2"><i class="bi bi-shield me-1 text-secondary"></i>Zugeteilte Teams</h6>
    <div class="mb-2 d-flex align-items-center gap-2">
      <input type="search" class="form-control form-control-sm table-filter" style="max-width:220px"
             placeholder="Filtern…" data-target="tbl-comp-teams" aria-label="Teams filtern">
      <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked)): ?>
      <form method="post" action="<?= url('competition/'.$c['id'].'/teams/remove-all') ?>"
            class="js-ajax" data-refresh="#tab-players"
            data-confirm="Alle Teams aus dem Bewerb entfernen?">
        <?= csrf_field() ?>
        <button class="btn btn-outline-danger btn-sm text-nowrap">
          <i class="bi bi-x-circle me-1"></i>Alle entfernen
        </button>
      </form>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0" data-sortable id="tbl-comp-teams">
        <thead class="table-light">
          <tr>
            <th>Teamname</th><th>Mitglieder</th><th class="text-center">Spielstärke</th><th>Angemeldet</th>
            <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked)): ?><th class="no-sort"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($assigned_teams as $team): ?>
        <tr>
          <td class="small fw-semibold"><?= e($team['name']) ?></td>
          <td class="small text-muted">
            <?php if ($team['members']): ?>
              <?= e(implode(', ', array_column($team['members'], 'fullname'))) ?>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td class="text-center" data-sort="<?= (float)$team['skill'] ?>">
            <?php if ((can_edit() && !$locked)): ?>
            <form method="post" action="<?= url('competition/'.$c['id'].'/team/'.$team['id'].'/skill') ?>"
                  class="d-inline-flex align-items-center gap-1 js-ajax" data-refresh="#tab-players">
              <?= csrf_field() ?>
              <input type="number" name="skill" value="<?= (int)$team['skill'] ?>"
                     step="1" min="0" class="form-control form-control-sm text-center" style="width:5rem">
              <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1" title="Speichern">
                <i class="bi bi-check-lg"></i>
              </button>
            </form>
            <?php else: ?>
            <?php if ($team['skill']): ?><span class="badge bg-secondary"><?= (int)$team['skill'] ?></span><?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="small text-muted" data-sort="<?= e($team['reg_date'] ?? '') ?>">
            <?= $team['reg_date'] ? date('d.m.Y', strtotime($team['reg_date'])) : '—' ?>
          </td>
          <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked)): ?>
          <td>
            <form method="post" action="<?= url('competition/'.$c['id'].'/team/'.$team['id'].'/remove') ?>"
                  class="js-ajax" data-refresh="#tab-players"
                  data-confirm="Team aus dem Bewerb entfernen?">
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
    <p class="text-muted small">Noch keine Teams eingetragen.</p>
    <?php endif; ?>

    <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked)): ?>
    <?php if ($unassigned_teams): ?>
    <div class="mt-3 pt-3 border-top">
      <h6 class="mb-2"><i class="bi bi-plus-circle me-1 text-primary"></i>Teams hinzufügen</h6>
      <form method="post" action="<?= url('competition/'.$c['id'].'/team/add') ?>" class="js-ajax" data-refresh="#tab-players">
        <?= csrf_field() ?>
        <div class="d-flex gap-2 mb-1">
          <a href="#" class="small text-muted add-select-all">Alle</a>
          <a href="#" class="small text-muted add-select-none">Keine</a>
        </div>
        <div class="border rounded add-entry-list mb-2" style="max-height:220px;overflow-y:auto">
          <?php foreach ($unassigned_teams as $team): ?>
          <label class="add-entry-item d-flex align-items-center gap-2 px-2 py-1 border-bottom mb-0 user-select-none" style="cursor:pointer">
            <input type="checkbox" name="team_ids[]" value="<?= $team['id'] ?>" class="form-check-input mt-0 flex-shrink-0">
            <span class="small">
              <?= e($team['name']) ?>
              <?php if ($team['skill']): ?><span class="badge bg-secondary ms-1"><?= (int)$team['skill'] ?></span><?php endif; ?>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus me-1"></i>Teams hinzufügen</button>
      </form>
    </div>
    <?php elseif (!$unassigned_teams && $t): ?>
    <div class="mt-3 pt-3 border-top">
      <p class="text-muted small mb-1">Alle vorhandenen Teams sind bereits eingetragen.</p>
      <a href="<?= url('players#tab-teams') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-plus-circle me-1"></i>Teams verwalten
      </a>
    </div>
    <?php endif; ?>

    <?php if (!empty($import_sources)): $imp_max = max(array_column($import_sources, 'max_rank')); ?>
    <div class="mt-3 pt-3 border-top">
      <h6 class="mb-2"><i class="bi bi-magic me-1 text-primary"></i>Teilnehmer aus Ergebnis übernehmen
        <span class="text-muted small fw-normal">(aus beendeter Gruppenphase)</span>
      </h6>
      <form method="post" action="<?= url('competition/'.$c['id'].'/teams/import-result') ?>" class="js-ajax" data-refresh="#tab-players">
        <?= csrf_field() ?>
        <div class="row g-2 align-items-end">
          <div class="col-sm-7">
            <label class="form-label small mb-0">Beendeter Bewerb</label>
            <select name="source_cid" class="form-select form-select-sm" required>
              <?php foreach ($import_sources as $isrc): ?>
              <option value="<?= (int)$isrc['id'] ?>">
                <?= e($isrc['name']) ?> (<?= (int)$isrc['groups'] ?> Gruppen, Plätze 1–<?= (int)$isrc['max_rank'] ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label small mb-1">Zu übernehmende Gruppenplätze <span class="text-muted">(je Gruppe)</span></label>
          <div class="d-flex flex-wrap gap-3">
            <?php for ($r = 1; $r <= $imp_max; $r++): ?>
            <label class="d-flex align-items-center gap-1 small user-select-none" style="cursor:pointer">
              <input type="checkbox" name="ranks[]" value="<?= $r ?>" class="form-check-input mt-0">
              Platz <?= $r ?>
            </label>
            <?php endfor; ?>
          </div>
          <div class="form-text">Die Spielstärke der übernommenen Teilnehmer wird auf den erreichten Gruppenplatz gesetzt. Nicht vorhandene Plätze werden ignoriert.</div>
        </div>
        <button class="btn btn-sm btn-primary mt-2"><i class="bi bi-magic me-1"></i>Übernehmen</button>
      </form>
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
    <h6 class="mb-2"><i class="bi bi-person-check me-1 text-secondary"></i>Zugeteilte Spieler</h6>
    <div class="mb-2 d-flex align-items-center gap-2">
      <input type="search" class="form-control form-control-sm table-filter" style="max-width:220px"
             placeholder="Filtern…" data-target="tbl-comp-players" aria-label="Spieler filtern">
      <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked) && $assigned): ?>
      <form method="post" action="<?= url('competition/'.$c['id'].'/players/remove-all') ?>"
            class="js-ajax" data-refresh="#tab-players"
            data-confirm="Alle Spieler aus dem Bewerb entfernen?">
        <?= csrf_field() ?>
        <button class="btn btn-outline-danger btn-sm text-nowrap">
          <i class="bi bi-x-circle me-1"></i>Alle entfernen
        </button>
      </form>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0" data-sortable id="tbl-comp-players">
        <thead class="table-light">
          <tr>
            <th>Name</th><th>Verein</th><th>Angemeldet</th><th class="text-center">Spielstärke</th>
            <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked)): ?><th class="no-sort"></th><?php endif; ?>
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
            <?php
              $is_tennis   = ($t['sport'] ?? '') === 'tennis';
              $reg_skill   = (float)($pl['registry_skill'] ?? 0);
              $comp_skill  = (float)($pl['skill'] ?? 0);
              $skill_diff  = in_array($t['sport'] ?? '', ['tischtennis', 'tennis']) && abs($comp_skill - $reg_skill) > 0.049;
            ?>
            <?php if ((can_edit() && !$locked)): ?>
            <div class="d-inline-flex flex-column align-items-center gap-1">
              <form method="post" action="<?= url('competition/'.$c['id'].'/player/'.$pl['id'].'/skill') ?>"
                    class="d-inline-flex align-items-center gap-1 js-ajax" data-refresh="#tab-players">
                <?= csrf_field() ?>
                <input type="number" name="skill"
                       value="<?= $is_tennis ? number_format($comp_skill, 1) : (int)$comp_skill ?>"
                       min="0"<?= $is_tennis ? ' step="0.1"' : '' ?>
                       class="form-control form-control-sm text-center<?= $skill_diff ? ' border-warning' : '' ?>" style="width:5rem">
                <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1" title="Speichern">
                  <i class="bi bi-check-lg"></i>
                </button>
              </form>
              <?php if ($skill_diff): ?>
              <div class="d-flex align-items-center gap-1 small" style="color:#fd7e14">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Aktuell: <?= $is_tennis ? number_format($reg_skill, 1) : (int)$reg_skill ?></span>
                <form method="post" action="<?= url('competition/'.$c['id'].'/player/'.$pl['id'].'/skill') ?>"
                      class="js-ajax" data-refresh="#tab-players">
                  <?= csrf_field() ?>
                  <input type="hidden" name="skill" value="<?= $is_tennis ? number_format($reg_skill, 1, '.', '') : (int)$reg_skill ?>">
                  <button type="submit" class="btn btn-link btn-sm p-0 lh-1" style="color:#fd7e14" title="Auf aktuellen Wert vom Spielerregister setzen">
                    <i class="bi bi-arrow-clockwise"></i>
                  </button>
                </form>
              </div>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <?php if ($comp_skill): $sv = $is_tennis ? number_format($comp_skill, 1) : (int)$comp_skill; ?>
            <span class="badge bg-secondary"><?= $sv ?></span>
            <?php endif; ?>
            <?php endif; ?>
          </td>
          <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked)): ?>
          <td>
            <form method="post" action="<?= url('competition/'.$c['id'].'/player/'.$pl['id'].'/remove') ?>"
                  class="js-ajax" data-refresh="#tab-players"
                  data-confirm="Spieler aus dem Bewerb entfernen?">
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
    <?php if ($c['phase'] === 'setup' && (can_edit() && !$locked) && $unassigned): ?>
    <div class="mt-3 pt-3 border-top">
      <h6 class="mb-2"><i class="bi bi-plus-circle me-1 text-primary"></i>Spieler hinzufügen</h6>
      <form method="post" action="<?= url('competition/'.$c['id'].'/player/add') ?>" class="js-ajax" data-refresh="#tab-players">
        <?= csrf_field() ?>
        <div class="d-flex gap-2 mb-1">
          <a href="#" class="small text-muted add-select-all">Alle</a>
          <a href="#" class="small text-muted add-select-none">Keine</a>
        </div>
        <div class="border rounded add-entry-list mb-2" style="max-height:220px;overflow-y:auto">
          <?php foreach ($unassigned as $pl): ?>
          <label class="add-entry-item d-flex align-items-center gap-2 px-2 py-1 border-bottom mb-0 user-select-none" style="cursor:pointer">
            <input type="checkbox" name="player_ids[]" value="<?= $pl['id'] ?>" class="form-check-input mt-0 flex-shrink-0">
            <span class="small">
              <?= e(trim($pl['name'] . ' ' . ($pl['firstname'] ?? ''))) ?>
              <?php if ($pl['club']): ?><span class="text-muted ms-1">(<?= e($pl['club']) ?>)</span><?php endif; ?>
              <?php if ($unassigned_skills[$pl['id']] ?? 0): ?><span class="badge bg-secondary ms-1"><?= (int)$unassigned_skills[$pl['id']] ?></span><?php endif; ?>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus me-1"></i>Hinzufügen</button>
      </form>
    </div>
    <?php endif; ?>
    <?php endif; /* end is_doubles/else */ ?>
  </div><!-- /tab-players -->

  <!-- Tab: Bewerb (active, last in DOM) -->
  <div class="tab-pane fade show active p-3" id="tab-competition" role="tabpanel">
  <?php if ((can_edit() && !$locked) && $c['phase'] === 'setup'): ?>
  <?php $draw_count = $is_team ? count($assigned_teams) : ($is_doubles ? count($assigned_doubles) : count($assigned)); ?>
  <?php if (!in_array($c['mode'], ['ko_only','double_ko']) && $draw_count >= 3): ?>
  <div class="mb-3">
    <form method="post" action="<?= url('competition/'.$c['id'].'/draw/groups') ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary btn-sm"><i class="bi bi-shuffle me-1"></i>Gruppen auslosen</button>
    </form>
  </div>
  <?php endif; ?>
  <?php if ($c['mode'] === 'ko_only' && $draw_count >= 2): ?>
  <div class="mb-3">
    <form method="post" action="<?= url('competition/'.$c['id'].'/draw/ko-direct') ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary btn-sm"><i class="bi bi-shuffle me-1"></i>KO-Bracket auslosen</button>
    </form>
  </div>
  <?php endif; ?>
  <?php if ($c['mode'] === 'double_ko' && $draw_count >= 2): ?>
  <div class="mb-3">
    <form method="post" action="<?= url('competition/'.$c['id'].'/draw/ko-direct') ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary btn-sm"><i class="bi bi-shuffle me-1"></i>Doppel-KO auslosen</button>
    </form>
  </div>
  <?php endif; ?>
  <?php endif; ?>
  <?php if ((can_edit() && !$locked) && $c['mode'] !== 'groups_cross' && $c['phase'] === 'group' && $unplayed_group == 0 && (int)$c['advance_count'] > 0): ?>
  <div class="mb-3">
    <?php if ($has_open_tie): ?>
    <div class="d-inline-flex align-items-center gap-2" tabindex="0" data-bs-toggle="tooltip"
         title="Bitte zuerst alle Tabellengleichstände auflösen.">
      <button class="btn btn-primary opacity-50" disabled style="cursor:not-allowed"><i class="bi bi-trophy me-1"></i>KO-Runde auslosen</button>
      <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i></span>
    </div>
    <?php else: ?>
    <form method="post" action="<?= url('competition/'.$c['id'].'/draw/ko') ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary"><i class="bi bi-trophy me-1"></i>KO-Runde auslosen</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if ((can_edit() && !$locked) && $c['mode'] === 'groups_cross' && $c['phase'] === 'group' && $unplayed_group == 0): ?>
  <div class="mb-3">
    <form method="post" action="<?= url('competition/'.$c['id'].'/draw/cross') ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary"><i class="bi bi-trophy me-1"></i>Kreuzspiele auslosen</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($c['phase'] === 'setup' && $c['mode'] !== 'double_ko'): ?>
  <div class="alert alert-secondary mb-0">
    <i class="bi bi-hourglass me-1"></i>Dieser Bewerb wurde noch nicht gestartet.
  </div>
  <?php endif; ?>

<!-- ═══ Gruppenphase / KO-Bracket (volle Breite) ════════════════════════════ -->
<?php ob_start(); // Puffer Gruppenphase (Reihenfolge je nach aktueller Stage) ?>
<?php if ($groups): ?>
    <?php
    $sets_mode_active = in_array($c['score_mode'] ?? 'match', ['sets', 'sets_grp']);
    // Einzel-Spalten nur im "wins"-Modus sinnvoll: bei "sum" = Gesamtergebnis, bei "total" keine Einzelspiele.
    $team_no_einzel = $is_team && in_array($c['team_result_mode'] ?? 'wins', ['sum', 'total'], true);
    $show_einzel = ($is_team && (int)($c['team_size'] ?? 0) >= 2 && !$team_no_einzel) || $sets_mode_active;
    // Farbige Markierung der Top-Plätze in der Gruppentabelle: KO-Modus → advance_count;
    // Kreuzspiele → Rang-1+2-Konfig (über Kreuz = Plätze 1+2, getrennt = nur Platz 1).
    $highlight_count = (int)$c['advance_count'];
    if ($c['mode'] === 'groups_cross') {
        $cc0 = explode(',', $c['cross_config'] ?? '');
        $highlight_count = (($cc0[0] ?? 'x') === 's') ? 1 : 2;
    }
    $grp_editable = (can_edit() && !$locked) && ($group_no_results ?? false);
    ?>
    <style>
      /* Im „Umstellen"-Modus alle Gruppen gleichzeitig anzeigen (für Drag&Drop zwischen Gruppen) */
      #grp-tab-content.show-all > .tab-pane { display: block !important; opacity: 1 !important; }
    </style>

    <!-- Abschnitts-Kopf Gruppen: Überschrift links, Gesamtexporte & Steuerung rechts -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <?php if ($show_section_titles): ?><h4 class="h5 mb-0"><i class="bi bi-people me-2 text-primary"></i>Gruppen</h4><?php endif; ?>
      <div class="d-flex align-items-center gap-2 ms-auto">
        <?php if (can_edit()): ?>
        <span class="btn-group btn-group-sm">
          <a href="<?= url('competition/'.$c['id'].'/pdf/groups') ?>" class="btn btn-outline-danger" target="_blank" title="Gruppen-PDF (alle Gruppen)"><i class="bi bi-file-earmark-pdf"></i></a>
          <a href="<?= url('competition/'.$c['id'].'/pdf/match-cards') ?>" class="btn btn-outline-secondary" target="_blank" title="Match-Cards (alle Gruppen)"><i class="bi bi-card-text"></i></a>
          <?php if ($is_team): ?>
          <a href="<?= url('competition/'.$c['id'].'/pdf/team-strips') ?>" class="btn btn-outline-secondary" target="_blank" title="Teampläne (alle Gruppen)"><i class="bi bi-list-task"></i></a>
          <?php endif; ?>
          <?php if ((int)($c['num_courts'] ?? 0) > 0): ?>
          <a href="<?= url('competition/'.$c['id'].'/pdf/court-plans') ?>" class="btn btn-outline-secondary" target="_blank" title="<?= e($court_sg) ?>pläne (alle Gruppen)"><i class="bi bi-geo-alt"></i></a>
          <?php endif; ?>
        </span>
        <?php endif; ?>
        <?php if ($is_team && (int)($c['team_size'] ?? 0) > 1 && ($c['team_result_mode'] ?? 'wins') !== 'total'): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm toggle-duels-btn" onclick="toggleAllDuels()">
          <i class="bi bi-list-ol me-1"></i><span class="toggle-duels-label">Einzelspiele ausblenden</span>
        </button>
        <?php endif; ?>
        <?php if ($grp_editable): ?>
        <button type="button" id="grp-edit-btn" onclick="toggleGrpEdit()" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrows-move me-1"></i>Umstellen
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php if (count($groups) > 1): ?>
    <ul class="nav nav-tabs no-hash mb-3" id="grp-tabs" role="tablist">
      <?php foreach ($groups as $__gi => $gnav): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link<?= $__gi === 0 ? ' active' : '' ?>" data-bs-toggle="tab" type="button" role="tab"
                data-bs-target="#grppane-<?= (int)$gnav['group']['id'] ?>">
          <i class="bi bi-people me-1"></i><?= e($gnav['group']['name']) ?>
        </button>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($grp_editable): ?>
    <div id="grp-edit-toolbar" style="display:none"
         class="align-items-center gap-2 mb-3 p-2 rounded border border-warning-subtle bg-warning-subtle">
      <i class="bi bi-info-circle text-warning-emphasis"></i>
      <span class="small">Per Drag &amp; Drop zwischen Gruppen verschieben oder innerhalb der Gruppe an die gewünschte Position ziehen.</span>
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

    <div class="tab-content" id="grp-tab-content">
    <?php foreach ($groups as $__gi => $gi): $g = $gi['group']; $standings = $gi['standings']; $matches = $gi['matches']; $tie_ids = $gi['tie_ids'] ?? []; ?>
    <div class="tab-pane fade<?= $__gi === 0 ? ' show active' : '' ?>" id="grppane-<?= (int)$g['id'] ?>" role="tabpanel">
    <div class="card shadow-sm mb-4" id="grp-<?= (int)$g['id'] ?>" style="scroll-margin-top:1rem">
      <div class="card-header fw-semibold d-flex align-items-center">
        <span><i class="bi bi-people me-1"></i><?= e($g['name']) ?></span>
        <?php if (can_edit()): ?>
        <span class="ms-auto btn-group btn-group-sm">
          <a href="<?= url('competition/'.$c['id'].'/pdf/groups/'.$g['id']) ?>" class="btn btn-outline-danger" target="_blank" title="Gruppen-PDF dieser Gruppe">
            <i class="bi bi-file-earmark-pdf"></i>
          </a>
          <a href="<?= url('competition/'.$c['id'].'/pdf/match-cards/'.$g['id']) ?>" class="btn btn-outline-secondary" target="_blank" title="Match-Cards dieser Gruppe">
            <i class="bi bi-card-text"></i>
          </a>
          <?php if ($is_team): ?>
          <a href="<?= url('competition/'.$c['id'].'/pdf/team-strips/'.$g['id']) ?>" class="btn btn-outline-secondary" target="_blank" title="Teampläne dieser Gruppe">
            <i class="bi bi-list-task"></i>
          </a>
          <?php endif; ?>
          <?php if ((int)($c['num_courts'] ?? 0) > 0): ?>
          <a href="<?= url('competition/'.$c['id'].'/pdf/court-plans/'.$g['id']) ?>" class="btn btn-outline-secondary" target="_blank" title="<?= e($court_sg) ?>pläne dieser Gruppe">
            <i class="bi bi-geo-alt"></i>
          </a>
          <?php endif; ?>
        </span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="grp-normal-view">
        <!-- Standings table -->
        <div class="table-responsive" id="standings-<?= (int)$g['id'] ?>">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr><th>#</th><th><?= $is_team ? 'Team' : 'Spieler' ?></th><th class="text-center">Sp</th><th class="text-center">S</th><th class="text-center">U</th><th class="text-center">N</th><th class="text-center" style="width:72px"><?= $sets_mode_active ? 'V (Sätze)' : 'V' ?></th><th class="text-center" style="width:72px"><?= $sets_mode_active ? '+/- (Sätze)' : '+/-' ?></th><?php if ($show_einzel): ?><th class="text-center" style="width:72px"><?= $sets_mode_active ? 'V (Punkte)' : 'V (Einzel)' ?></th><th class="text-center" style="width:72px"><?= $sets_mode_active ? '+/- (Punkte)' : '+/- (Einzel)' ?></th><?php endif; ?><th class="text-center">Pkt</th></tr>
            </thead>
            <tbody>
              <?php foreach ($standings as $i => $pl): ?>
              <tr<?= $i < $highlight_count ? ' class="row-advance"' : '' ?>>
                <td><strong><?= $i+1 ?></strong></td>
                <td>
                  <?= e($pl['name']) ?>
                  <small class="text-muted"><?= e($pl['club']) ?></small>
                  <?php if (($c['show_skill'] ?? 0) && $pl['skill']):
                    $sv = ($t['sport'] ?? '') === 'tennis' ? number_format((float)$pl['skill'], 1) : (int)$pl['skill']; ?>
                  <span class="badge bg-secondary ms-1"><?= $sv ?></span>
                  <?php endif; ?>
                  <?php if (is_admin() && in_array($pl['id'], $tie_ids)): ?>
                  <span class="badge bg-warning text-dark ms-1" title="Tabellengleichstand – Positionen festlegen"><i class="bi bi-exclamation-triangle"></i></span>
                  <?php endif; ?>
                </td>
                <td class="text-center"><?= $pl['played'] ?></td>
                <td class="text-center"><?= $pl['wins'] ?></td>
                <td class="text-center"><?= $pl['draws'] ?></td>
                <td class="text-center"><?= $pl['losses'] ?></td>
                <td class="text-center"><?= $pl['goals_for'] ?>:<?= $pl['goals_against'] ?></td>
                <td class="text-center"><?= $pl['goal_diff'] >= 0 ? '+' . $pl['goal_diff'] : $pl['goal_diff'] ?></td>
                <?php if ($show_einzel): ?>
                <td class="text-center"><?= (int)($pl['einzel_for'] ?? 0) ?>:<?= (int)($pl['einzel_against'] ?? 0) ?></td>
                <td class="text-center"><?php $ed = (int)($pl['einzel_diff'] ?? 0); echo $ed >= 0 ? '+' . $ed : $ed; ?></td>
                <?php endif; ?>
                <td class="text-center"><strong><?= $pl['points'] ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (is_admin() && $c['phase'] === 'group' && !empty($tie_ids)): ?>
        <div class="px-3 pb-2 pt-1">
          <button class="btn btn-outline-secondary btn-sm"
                  data-bs-toggle="modal" data-bs-target="#losentscheid-modal-<?= $g['id'] ?>">
            <i class="bi bi-list-ol me-1"></i>Tabellengleichstand – Positionen festlegen
          </button>
        </div>
        <!-- Losentscheid-Modal -->
        <div class="modal fade" id="losentscheid-modal-<?= $g['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-list-ol me-1"></i>Tabellengleichstand – Gruppe <?= e($g['name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <form method="post" action="<?= url('group/'.$g['id'].'/tiebreak') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="<?= $is_team ? 'team' : ($is_doubles ? 'double' : 'player') ?>">
                <div class="modal-body">
                  <p class="text-muted small mb-3">Reihenfolge per Drag-and-Drop festlegen. Gilt nur als letzter Tiebreaker — Punkte und Direktvergleich haben immer Vorrang.</p>
                  <ul class="list-group los-sortable" id="los-list-<?= $g['id'] ?>">
                    <?php $los_rank = 1; foreach ($standings as $i => $row): $is_tied = in_array($row['id'], $tie_ids); ?>
                    <li class="list-group-item d-flex align-items-center gap-2<?= $is_tied ? ' los-draggable' : ' opacity-50' ?>"
                        style="<?= $is_tied ? 'cursor:grab' : '' ?>">
                      <?php if ($is_tied): ?>
                      <i class="bi bi-grip-vertical text-muted flex-shrink-0"></i>
                      <?php else: ?>
                      <span style="width:1rem;flex-shrink:0"></span>
                      <?php endif; ?>
                      <span class="badge bg-light text-secondary border flex-shrink-0" style="min-width:2rem"><?= $i + 1 ?></span>
                      <span class="flex-grow-1 small"><?= e($row['name']) ?><?php if ($row['club']): ?> <span class="text-muted">(<?= e($row['club']) ?>)</span><?php endif; ?></span>
                      <?php if ($is_tied): ?>
                      <input type="hidden" name="order[<?= $row['id'] ?>]" class="los-rank-input" value="<?= $los_rank++ ?>">
                      <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                  <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i>Speichern</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <!-- Match results -->
        <div class="p-3 border-top grp-results">
          <h6 class="text-muted mb-2">Spielergebnisse</h6>
          <?php
          $use_duels = $is_team && (int)($c['team_size'] ?? 0) > 1 && ($c['team_result_mode'] ?? 'wins') !== 'total';
          $use_sets  = in_array($c['score_mode'] ?? 'match', ['sets', 'sets_grp']);
          $grp_editable = (can_edit() && !$locked) && $c['phase'] === 'group';
          // Spielfreie (Bye) je Runde ermitteln: Gruppenmitglieder, die in der Runde fehlen.
          // (Bei garantierten Pausen / gerader Anzahl können es zwei pro Runde sein.)
          $show_byes  = !empty($c['show_byes']) || !empty($c['force_byes']);
          $round_byes = [];
          if ($show_byes) {
              $bye_members = [];
              foreach ($standings as $sp) { $bye_members[(int)$sp['id']] = $sp['name']; }
              $bye_present = [];
              foreach ($matches as $bm) {
                  $br = (int)($bm['round_no'] ?? 0);
                  if ($br <= 0) continue;
                  $bye_present[$br][(int)$bm['player1_id']] = true;
                  $bye_present[$br][(int)$bm['player2_id']] = true;
              }
              foreach ($bye_present as $br => $pres) {
                  foreach ($bye_members as $bpid => $bpname) {
                      if (!isset($pres[$bpid])) $round_byes[$br][] = $bpname;
                  }
              }
          }
          $bye_prev_round = null;
          $render_bye = function ($names) {
              $h = '';
              foreach ((array)$names as $name) {
                  $h .= '<div class="mb-1 small text-muted fst-italic text-center">'
                      . '<i class="bi bi-slash-circle me-1"></i>' . e($name) . ' — spielfrei</div>';
              }
              return $h;
          };
          $render_round = function ($r) use ($c, $g) {
              $rt = group_round_time($c, $g, (int)$r);
              $time = $rt !== '' ? ' &middot; <span class="text-primary">' . $rt . ' Uhr</span>' : '';
              return '<div class="text-center small fw-semibold text-secondary mt-2 mb-1 pt-1 border-top">Runde ' . (int)$r . $time . '</div>';
          };
          $grp_pause   = group_pause_info($c, $g);
          $render_pause = function ($pw) {
              return '<div class="text-center small fw-bold mt-2 mb-1 py-1 rounded" style="background:#fff3cd;color:#856404">'
                   . '<i class="bi bi-pause-circle me-1"></i>' . e($pw['label']) . '</div>';
          };
          if ($grp_editable && !$use_duels && !$use_sets): ?>
          <form id="grp-form-<?= $g['id'] ?>" method="post"
                action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
            <?= csrf_field() ?>
          </form>
          <?php endif; ?>
          <?php foreach ($matches as $m):
            $cur_round = (int)($m['round_no'] ?? 0);
            if (($show_byes || $grp_pause) && $cur_round !== $bye_prev_round) {
                if ($show_byes && $bye_prev_round !== null && isset($round_byes[$bye_prev_round])) echo $render_bye($round_byes[$bye_prev_round]);
                // Pause nach Runde P (zwischen Runde P und P+1).
                if ($grp_pause && $bye_prev_round !== null && $bye_prev_round <= $grp_pause['after_round'] && $cur_round > $grp_pause['after_round']) {
                    echo $render_pause($grp_pause);
                }
                if ($show_byes && $cur_round > 0) echo $render_round($cur_round);
            }
            $bye_prev_round = $cur_round;
            $has_p1 = $is_team ? !empty($m['team1_id']) : !empty($m['player1_id']);
            $has_p2 = $is_team ? !empty($m['team2_id']) : !empty($m['player2_id']);
            if (!$has_p1 || !$has_p2) continue;
            // Spielplatz kompakt links neben der Begegnungszeile (gleiche Zeile wie Teams/Felder).
            // Nur für admin/editor – für Gäste/Reader ausgeblendet.
            $court_pre = (!empty($m['court_no']) && can_edit())
              ? '<span class="badge bg-light text-secondary border flex-shrink-0" style="white-space:nowrap;margin-top:3px"><i class="bi bi-geo-alt"></i> ' . e($court_sg) . ' ' . (int)$m['court_no'] . '</span>'
              : '';
          ?>
          <div class="d-flex align-items-start gap-2" id="match-row-<?= $m['id'] ?>"><?= $court_pre ?><div class="flex-grow-1" style="min-width:0">
          <?php
            if ($use_duels):
              $t1id = (int)($m['team1_id'] ?? 0);
              $t2id = (int)($m['team2_id'] ?? 0);
              $t1members = $team_members[$t1id] ?? [];
              $t2members = $team_members[$t2id] ?? [];
              $duel_rows = $existing_duels[$m['id']] ?? [];
              $team_size_val = (int)($c['team_size'] ?? 0);
          ?>
          <div class="mb-3 border rounded overflow-hidden">
            <div class="d-flex align-items-center px-2 py-2 bg-light border-bottom">
              <span class="text-truncate fw-semibold" style="min-width:0;flex:1;text-align:right"><?= e($m['p1name']) ?></span>
              <?php if ($grp_editable): ?>
              <span class="d-flex align-items-center justify-content-center gap-1 flex-shrink-0" style="width:110px">
                <input type="number" name="total_score1" min="0" form="duel-form-<?= $m['id'] ?>"
                       class="form-control form-control-sm text-center duel-total-input" style="width:48px;font-size:.82rem"
                       value="<?= $m['played'] ? (int)$m['score1'] : '' ?>" title="Gesamtergebnis (optional)">
                <span class="text-muted">:</span>
                <input type="number" name="total_score2" min="0" form="duel-form-<?= $m['id'] ?>"
                       class="form-control form-control-sm text-center duel-total-input" style="width:48px;font-size:.82rem"
                       value="<?= $m['played'] ? (int)$m['score2'] : '' ?>" title="Gesamtergebnis (optional)">
              </span>
              <?php else: ?>
              <span class="fw-bold fs-6 text-center flex-shrink-0" style="width:110px">
                <?= $m['played'] ? (int)$m['score1'].':'.(int)$m['score2'] : '—:—' ?>
              </span>
              <?php endif; ?>
              <span class="text-truncate fw-semibold" style="min-width:0;flex:1"><?= e($m['p2name']) ?></span>
              <?php if ($m['played'] && $grp_editable): ?>
              <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>" class="flex-shrink-0 ms-1"
                    data-confirm="Ergebnis wirklich löschen?">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger p-0" style="width:26px;height:26px" title="Ergebnis löschen">
                  <i class="bi bi-x-circle"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
            <?php if ($grp_editable): ?>
            <form class="duel-form" id="duel-form-<?= $m['id'] ?>" method="post" action="<?= url('match/'.$m['id'].'/duels') ?>">
              <?= csrf_field() ?>
              <table class="table table-sm align-middle mb-0 duel-rows" style="table-layout:fixed;font-size:.82rem">
                <colgroup><col><col style="width:110px"><col></colgroup>
                <tbody>
                <?php for ($i = 0; $i < $team_size_val; $i++):
                      $d = $duel_rows[$i] ?? []; ?>
                <tr>
                  <td class="text-end py-1">
                    <select name="duels[<?= $i ?>][player1_id]" class="form-select form-select-sm duel-player-select" data-side="p1" style="direction:rtl;text-align:right;font-size:.82rem">
                      <option value="">— auswählen —</option>
                      <?php foreach ($t1members as $pl): ?>
                      <option value="<?= $pl['id'] ?>"<?= (($d['player1_id'] ?? null) == $pl['id']) ? ' selected' : '' ?>><?= e($pl['fullname']) ?></option>
                      <?php endforeach; ?>
                      <option value="__doppel__"<?= (($d['duel_label'] ?? null) === 'Doppel') ? ' selected' : '' ?>>Doppel</option>
                    </select>
                  </td>
                  <td class="text-center py-1">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                      <input type="number" name="duels[<?= $i ?>][score1]" min="0"
                             class="form-control form-control-sm text-center duel-score" style="width:48px;font-size:.82rem"
                             value="<?= (isset($d['score1']) && $d['played']) ? $d['score1'] : '' ?>">
                      <span class="text-muted">:</span>
                      <input type="number" name="duels[<?= $i ?>][score2]" min="0"
                             class="form-control form-control-sm text-center duel-score" style="width:48px;font-size:.82rem"
                             value="<?= (isset($d['score2']) && $d['played']) ? $d['score2'] : '' ?>">
                    </div>
                  </td>
                  <td class="py-1">
                    <select name="duels[<?= $i ?>][player2_id]" class="form-select form-select-sm duel-player-select" data-side="p2" style="font-size:.82rem">
                      <option value="">— auswählen —</option>
                      <?php foreach ($t2members as $pl): ?>
                      <option value="<?= $pl['id'] ?>"<?= (($d['player2_id'] ?? null) == $pl['id']) ? ' selected' : '' ?>><?= e($pl['fullname']) ?></option>
                      <?php endforeach; ?>
                      <option value="__doppel__"<?= (($d['duel_label'] ?? null) === 'Doppel') ? ' selected' : '' ?>>Doppel</option>
                    </select>
                  </td>
                </tr>
                <?php endfor; ?>
                </tbody>
              </table>
              <div class="px-2 py-1 text-end border-top">
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
              </div>
            </form>
            <?php elseif (!empty($duel_rows)): ?>
            <table class="table table-sm align-middle mb-0 duel-rows" style="table-layout:fixed">
              <colgroup><col><col style="width:80px"><col></colgroup>
              <tbody>
                <?php foreach ($duel_rows as $d): ?>
                <tr>
                  <td class="small text-end"><?= ($d['duel_label'] ?? null) === 'Doppel' ? '<em>Doppel</em>' : e($d['player1_name'] ?? '—') ?></td>
                  <td class="text-center small fw-semibold"><?= $d['played'] ? $d['score1'].':'.$d['score2'] : '—:—' ?></td>
                  <td class="small"><?= ($d['duel_label'] ?? null) === 'Doppel' ? '<em>Doppel</em>' : e($d['player2_name'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
          <?php elseif ($use_sets): ?>
          <?php $set_rows_grp = $existing_sets[$m['id']] ?? []; ?>
          <div class="mb-3 border rounded overflow-hidden">
            <div class="d-flex align-items-center px-2 py-2 bg-light border-bottom">
              <span class="text-truncate fw-semibold" style="min-width:0;flex:1;text-align:right"><?= e($m['p1name']) ?></span>
              <span class="fw-bold fs-6 text-center flex-shrink-0 sets-total-<?= $m['id'] ?>" style="width:110px">
                <?= $m['played'] ? (int)$m['score1'].':'.(int)$m['score2'] : '—:—' ?>
              </span>
              <span class="text-truncate fw-semibold" style="min-width:0;flex:1"><?= e($m['p2name']) ?></span>
              <?php if ($m['played'] && $grp_editable): ?>
              <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>" class="flex-shrink-0 ms-1"
                    data-confirm="Ergebnis wirklich löschen?">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger p-0" style="width:26px;height:26px" title="Ergebnis löschen">
                  <i class="bi bi-x-circle"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
            <?php if ($grp_editable): ?>
            <form class="sets-form" method="post" action="<?= url('match/'.$m['id'].'/sets') ?>"
                  data-mid="<?= $m['id'] ?>">
              <?= csrf_field() ?>
              <div class="d-flex flex-wrap align-items-center justify-content-center px-2 py-2" style="gap:40px" id="sets-container-<?= $m['id'] ?>">
              <?php
                $sc = max(count($set_rows_grp), 1);
                for ($si = 0; $si < $sc; $si++):
                  $sr = $set_rows_grp[$si] ?? [];
              ?>
                <div class="sets-pair d-flex align-items-center gap-1">
                  <input type="number" name="sets[<?= $si ?>][score1]" min="0"
                         class="form-control form-control-sm text-center sets-score" style="width:46px"
                         value="<?= isset($sr['score1']) ? $sr['score1'] : '' ?>">
                  <span class="text-muted small">:</span>
                  <input type="number" name="sets[<?= $si ?>][score2]" min="0"
                         class="form-control form-control-sm text-center sets-score" style="width:46px"
                         value="<?= isset($sr['score2']) ? $sr['score2'] : '' ?>">
                </div>
              <?php endfor; ?>
              </div>
              <div class="px-2 py-1 d-flex justify-content-center align-items-center gap-2 border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm sets-add-btn"
                        data-mid="<?= $m['id'] ?>">
                  <i class="bi bi-plus-circle me-1"></i>Satz
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm sets-remove-btn"
                        data-mid="<?= $m['id'] ?>">
                  <i class="bi bi-dash-circle"></i>
                </button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
              </div>
            </form>
            <?php elseif (!empty($set_rows_grp)): ?>
            <div class="d-flex flex-wrap gap-2 px-2 py-2">
              <?php foreach ($set_rows_grp as $sr): ?>
              <span class="small fw-semibold"><?= (int)$sr['score1'] ?>:<?= (int)$sr['score2'] ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <?php if ($grp_editable): ?>
          <?php
            // Ergebnisfärbung (nur bei gespeichertem Ergebnis): Sieger grün, Verlierer rot,
            // Unentschieden beide blau – gedämpfte Farben, Inhalt bleibt lesbar.
            $cls1 = $cls2 = '';
            if ($m['played']) {
                $s1c = (int)$m['score1']; $s2c = (int)$m['score2'];
                if     ($s1c >  $s2c) { $cls1 = ' score-win';  $cls2 = ' score-loss'; }
                elseif ($s1c <  $s2c) { $cls1 = ' score-loss'; $cls2 = ' score-win'; }
                else                  { $cls1 = ' score-draw'; $cls2 = ' score-draw'; }
            }
          ?>
          <div class="mb-2" style="display:grid;grid-template-columns:1fr 56px 1.2rem 56px 1fr 64px;align-items:center;column-gap:4px">
            <span class="text-end small text-truncate"><?= e($m['p1name']) ?></span>
            <input type="number" name="matches[<?= $m['id'] ?>][score1]" min="0"
                   form="grp-form-<?= $g['id'] ?>"<?= $m['played'] ? ' readonly' : '' ?>
                   class="form-control form-control-sm text-center<?= $cls1 ?>"
                   value="<?= $m['played'] ? $m['score1'] : '' ?>">
            <span class="text-center">:</span>
            <input type="number" name="matches[<?= $m['id'] ?>][score2]" min="0"
                   form="grp-form-<?= $g['id'] ?>"<?= $m['played'] ? ' readonly' : '' ?>
                   class="form-control form-control-sm text-center<?= $cls2 ?>"
                   value="<?= $m['played'] ? $m['score2'] : '' ?>">
            <span class="small text-truncate"><?= e($m['p2name']) ?></span>
            <div class="d-flex align-items-center gap-1 justify-content-end" id="match-actions-<?= $m['id'] ?>">
              <?php if (!$m['played']): ?>
              <button type="button" class="btn btn-sm btn-outline-primary p-0 save-one-result" style="width:30px;height:30px"
                      title="Dieses Ergebnis speichern"
                      data-mid="<?= $m['id'] ?>" data-gid="<?= (int)$g['id'] ?>" data-url="<?= url('match/'.$m['id'].'/result') ?>" data-csrf="<?= csrf_token() ?>">
                <i class="bi bi-save"></i>
              </button>
              <?php endif; ?>
              <?php if ($m['played']): ?>
              <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>"
                    class="js-inline-clear" data-mid="<?= $m['id'] ?>" data-gid="<?= (int)$g['id'] ?>"
                    data-confirm="Ergebnis wirklich löschen?">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger p-0" style="width:30px;height:30px" title="Ergebnis löschen">
                  <i class="bi bi-x-circle"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php else: ?>
          <?php
            // Gäste/Viewer (sowie gesperrte/abgeschlossene Phase): gleiche farbigen Kästchen
            // wie in der Editieransicht – Sieger grün, Verlierer rot, Unentschieden beide blau.
            $cls1 = $cls2 = '';
            if ($m['played']) {
                $s1c = (int)$m['score1']; $s2c = (int)$m['score2'];
                if     ($s1c >  $s2c) { $cls1 = ' score-win';  $cls2 = ' score-loss'; }
                elseif ($s1c <  $s2c) { $cls1 = ' score-loss'; $cls2 = ' score-win'; }
                else                  { $cls1 = ' score-draw'; $cls2 = ' score-draw'; }
            }
          ?>
          <div class="mb-2" style="display:grid;grid-template-columns:1fr 56px 1.2rem 56px 1fr;align-items:center;column-gap:4px">
            <span class="text-end small text-truncate"><?= e($m['p1name']) ?></span>
            <span class="score-box<?= $cls1 ?>"><?= $m['played'] ? (int)$m['score1'] : '' ?></span>
            <span class="text-center">:</span>
            <span class="score-box<?= $cls2 ?>"><?= $m['played'] ? (int)$m['score2'] : '' ?></span>
            <span class="small text-truncate"><?= e($m['p2name']) ?></span>
          </div>
          <?php endif; ?>
          <?php endif; ?>
          </div></div>
          <?php endforeach; ?>
          <?php if ($show_byes && $bye_prev_round !== null && isset($round_byes[$bye_prev_round])) echo $render_bye($round_byes[$bye_prev_round]); ?>
          <?php if ($grp_editable && !$use_duels && !$use_sets): ?>
          <button form="grp-form-<?= $g['id'] ?>" type="submit"
                  class="btn btn-primary btn-sm mt-2 w-100">
            <i class="bi bi-save me-1"></i>Alle speichern
          </button>
          <?php endif; ?>
        </div>
        </div><!-- /.grp-normal-view -->
        <?php if ((can_edit() && !$locked) && ($group_no_results ?? false)): ?>
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
    </div><!-- /.card -->
    </div><!-- /.tab-pane -->
    <?php endforeach; ?>
    </div><!-- /#grp-tab-content -->
    <?php endif; ?>
<?php $__groups_html = ob_get_clean(); ob_start(); // Puffer KO-Phase ?>
    <?php if ($ko_rounds || $third_place_match): ?>
    <!-- KO-Phase -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <?php if ($show_section_titles): ?><h4 class="h5 mb-0"><i class="bi bi-trophy me-2 text-warning"></i>KO-Runde</h4><?php endif; ?>
      <div class="d-flex align-items-center gap-2 ms-auto">
        <?php if (can_edit()): ?>
        <span class="btn-group btn-group-sm">
          <a href="<?= url('competition/'.$c['id'].'/pdf/ko') ?>" class="btn btn-outline-danger" target="_blank" title="KO-PDF"><i class="bi bi-file-earmark-pdf"></i></a>
          <a href="<?= url('competition/'.$c['id'].'/pdf/match-cards') ?>" class="btn btn-outline-secondary" target="_blank" title="Match-Cards"><i class="bi bi-card-text"></i></a>
          <?php if ($is_team): ?>
          <a href="<?= url('competition/'.$c['id'].'/pdf/team-strips') ?>" class="btn btn-outline-secondary" target="_blank" title="Teampläne"><i class="bi bi-list-task"></i></a>
          <?php endif; ?>
          <?php if ((int)($c['num_courts'] ?? 0) > 0): ?>
          <a href="<?= url('competition/'.$c['id'].'/pdf/court-plans') ?>" class="btn btn-outline-secondary" target="_blank" title="<?= e($court_sg) ?>pläne"><i class="bi bi-geo-alt"></i></a>
          <?php endif; ?>
        </span>
        <?php endif; ?>
        <?php if ($is_team && (int)($c['team_size'] ?? 0) > 1 && ($c['team_result_mode'] ?? 'wins') !== 'total'): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm toggle-duels-btn" onclick="toggleAllDuels()">
          <i class="bi bi-list-ol me-1"></i><span class="toggle-duels-label">Einzelspiele ausblenden</span>
        </button>
        <?php endif; ?>
        <?php if ((can_edit() && !$locked) && $ko_no_results): ?>
        <button type="button" id="ko-edit-btn" onclick="toggleKoEdit()" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrows-move me-1"></i>Umstellen
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php if ((can_edit() && !$locked) && $ko_no_results): ?>
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
      $slot_h = 115;
      $bracket_h = $first_count * $slot_h;
      $ko_col_w  = $is_team ? 405 : 270;
      $bracket_w = count($ko_rounds) * $ko_col_w;
      $ko_use_duels = $is_team && (int)($c['team_size'] ?? 0) > 1 && ($c['team_result_mode'] ?? 'wins') !== 'total';
      $ko_use_sets  = ($c['score_mode'] ?? 'match') === 'sets';
    ?>

    <?php if ((can_edit() && !$locked)): ?>
    <form id="ko-form" method="post" action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>

    <?php
    // Spiel um Platz 3 vorab als HTML aufbauen → wird unten direkt unter dem Finale
    // (letzte Bracket-Spalte) platziert, ohne Verbindungslinie.
    $third_place_html = '';
    if ($third_place_match) {
      $ko_total_matches = 0;
      foreach ($ko_rounds as $__r) { $ko_total_matches += count($__r['matches']); }
      ob_start(); ?>
      <div style="text-align:center;font-weight:600;font-size:.8rem;color:#856404;padding-bottom:4px;margin-top:6px">🥉 Spiel um Platz 3</div>
      <?php foreach ($third_place_match['matches'] as $m): ?>
      <div class="ko-match" style="border:1px solid #ffc107;border-radius:6px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:<?= $ko_use_sets ? 'visible' : 'hidden' ?>">
        <div style="font-size:.65rem;color:#9e9e9e;padding:1px 6px;border-bottom:1px solid #f5f5f5;background:#fafafa">Spiel <?= $ko_total_matches + 1 ?><?php if (!empty($m['court_no']) && can_edit()): ?> · <?= e($court_sg) ?> <?= (int)$m['court_no'] ?><?php endif; ?></div>
        <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score1'] > $m['score2']) ? 'bg-success-subtle fw-semibold' : '' ?>"
             style="min-height:33px;border-bottom:1px solid #f0f0f0">
          <span class="flex-grow-1 small text-truncate" style="min-width:0"
                title="<?= e($m['p1name'] ?? '') ?>"><?= $m['p1name'] !== null ? e($m['p1name']) : '—' ?></span>
          <?php if (!$ko_use_sets && (can_edit() && !$locked) && $m['player1_id'] && $m['player2_id']): ?>
          <input type="number" name="matches[<?= $m['id'] ?>][score1]" min="0" form="ko-form"
                 class="form-control form-control-sm text-center ms-auto" style="width:40px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0"
                 value="<?= $m['played'] ? $m['score1'] : '' ?>">
          <?php elseif ($m['played']): ?><span class="fw-bold small ms-auto" style="flex-shrink:0"><?= $m['score1'] ?></span><?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score2'] > $m['score1']) ? 'bg-success-subtle fw-semibold' : '' ?>"
             style="min-height:33px">
          <span class="flex-grow-1 small text-truncate" style="min-width:0"
                title="<?= e($m['p2name'] ?? '') ?>"><?= $m['p2name'] !== null ? e($m['p2name']) : '—' ?></span>
          <?php if (!$ko_use_sets && (can_edit() && !$locked) && $m['player1_id'] && $m['player2_id']): ?>
          <input type="number" name="matches[<?= $m['id'] ?>][score2]" min="0" form="ko-form"
                 class="form-control form-control-sm text-center ms-auto" style="width:40px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0"
                 value="<?= $m['played'] ? $m['score2'] : '' ?>">
          <?php elseif ($m['played']): ?><span class="fw-bold small ms-auto" style="flex-shrink:0"><?= $m['score2'] ?></span><?php endif; ?>
        </div>
        <?php if ($ko_use_sets && $m['player1_id'] && $m['player2_id']): ?>
        <?php $ko_set_rows = $existing_sets[$m['id']] ?? []; ?>
        <div style="border-top:1px solid #f0f0f0;padding:3px 6px">
          <details>
            <summary style="font-size:.72rem;cursor:pointer;color:#0d6efd;user-select:none;list-style:none;padding:2px 0">
              &#9656; <?= $m['played'] ? $m['score1'].':'.$m['score2'].' (Sätze)' : 'Sätze' ?>
            </summary>
            <?php if ((can_edit() && !$locked)): ?>
            <form class="sets-form" method="post" action="<?= url('match/'.$m['id'].'/sets') ?>"
                  style="margin-top:4px" data-mid="<?= $m['id'] ?>">
              <?= csrf_field() ?>
              <div class="d-flex flex-wrap align-items-center justify-content-center" style="gap:40px" id="sets-container-<?= $m['id'] ?>">
              <?php
                $ko_sc = max(count($ko_set_rows), 1);
                for ($si = 0; $si < $ko_sc; $si++):
                  $sr = $ko_set_rows[$si] ?? [];
              ?>
                <div class="sets-pair d-flex align-items-center gap-1">
                  <input type="number" name="sets[<?= $si ?>][score1]" min="0"
                         class="form-control form-control-sm text-center sets-score"
                         style="width:32px;height:22px;font-size:.7rem;padding:0 2px"
                         value="<?= isset($sr['score1']) ? (int)$sr['score1'] : '' ?>">
                  <span style="font-size:.7rem">:</span>
                  <input type="number" name="sets[<?= $si ?>][score2]" min="0"
                         class="form-control form-control-sm text-center sets-score"
                         style="width:32px;height:22px;font-size:.7rem;padding:0 2px"
                         value="<?= isset($sr['score2']) ? (int)$sr['score2'] : '' ?>">
                </div>
              <?php endfor; ?>
              </div>
              <div class="d-flex justify-content-center align-items-center gap-1 mt-1 mb-1">
                <button type="button" class="sets-add-btn btn btn-outline-secondary btn-sm py-0 px-1"
                        style="font-size:.7rem" data-mid="<?= $m['id'] ?>">
                  <i class="bi bi-plus"></i>
                </button>
                <button type="button" class="sets-remove-btn btn btn-outline-secondary btn-sm py-0 px-1"
                        style="font-size:.7rem" data-mid="<?= $m['id'] ?>">
                  <i class="bi bi-dash"></i>
                </button>
                <button type="submit" class="btn btn-primary btn-sm py-0 px-2" style="font-size:.72rem">Speichern</button>
                <?php if ($m['played']): ?>
                <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>" style="display:inline"
                      data-confirm="Ergebnis wirklich löschen?">
                  <?= csrf_field() ?>
                  <button class="btn btn-link text-danger p-0" style="font-size:.7rem;line-height:1.4" title="Ergebnis löschen">
                    <i class="bi bi-x-circle"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </form>
            <?php elseif (!empty($ko_set_rows)): ?>
            <div class="d-flex flex-wrap gap-1 mt-1" style="font-size:.72rem">
              <?php foreach ($ko_set_rows as $sr): ?>
              <span class="fw-semibold"><?= (int)$sr['score1'] ?>:<?= (int)$sr['score2'] ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </details>
        </div>
        <?php elseif (!$ko_use_sets && (can_edit() && !$locked) && $m['player1_id'] && $m['player2_id']): ?>
        <div style="border-top:1px solid #f0f0f0;padding:0 4px 1px;text-align:right">
          <?php if ($m['played']): ?>
          <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>" class="js-bracket-clear d-inline"
                data-mid="<?= $m['id'] ?>" data-area="ko-area-<?= $c['id'] ?>" data-confirm="Ergebnis wirklich löschen?">
            <?= csrf_field() ?>
            <button class="btn btn-link text-danger p-0" style="font-size:.7rem;line-height:1.4" title="Ergebnis löschen">
              <i class="bi bi-x-circle"></i>
            </button>
          </form>
          <?php else: ?>
          <button type="button" class="btn btn-link text-primary p-0 save-bracket-result" style="font-size:.7rem;line-height:1.4"
                  title="Dieses Ergebnis speichern" data-mid="<?= $m['id'] ?>" data-url="<?= url('match/'.$m['id'].'/result') ?>"
                  data-csrf="<?= csrf_token() ?>" data-area="ko-area-<?= $c['id'] ?>"><i class="bi bi-save"></i></button>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php $third_place_html = ob_get_clean();
    }
    ?>

    <div id="ko-area-<?= $c['id'] ?>" style="overflow-x:auto; margin-bottom:4px">
      <!-- Round headers -->
      <div style="display:flex; width:<?= $bracket_w ?>px">
        <?php $last_ri = count($ko_rounds) - 1; ?>
        <?php foreach ($ko_rounds as $ri => $round): ?>
        <div style="width:<?= $ko_col_w ?>px; flex-shrink:0; text-align:center; font-weight:600; font-size:.8rem; padding:0 8px 4px;
                    color:<?= $ri === $last_ri ? '#856404' : '#6c757d' ?>">
          <?= $ri === $last_ri ? '🏆 ' : '' ?><?= e($round['name']) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <!-- Bracket body -->
      <?php $ko_match_num = 0; ?>
      <div id="ko-bracket-<?= $c['id'] ?>" style="display:flex; position:relative; height:<?= $bracket_h ?>px; width:<?= $bracket_w ?>px">
        <?php foreach ($ko_rounds as $ri => $round): ?>
        <?php $__last_col_3rd = ($ri === $last_ri && $third_place_html !== ''); ?>
        <div class="ko-round" style="display:flex;flex-direction:column;justify-content:space-around;<?= $__last_col_3rd ? 'position:relative;' : '' ?>width:<?= $ko_col_w ?>px;flex-shrink:0;height:100%;padding:0 8px">
          <?php if ($ri === $last_ri): ?>
          <div style="display:flex;flex-direction:column">
            <div style="text-align:center;font-weight:600;font-size:.8rem;color:#856404;padding-bottom:4px">🏆 <?= e($round['name']) ?></div>
          <?php endif; ?>
          <?php foreach ($round['matches'] as $m): $ko_match_num++; ?>
          <div class="ko-match" style="border:1px solid #dee2e6;border-radius:6px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:<?= ($ko_use_duels || $ko_use_sets) ? 'visible' : 'hidden' ?>">
            <div style="font-size:.65rem;color:#9e9e9e;padding:1px 6px;border-bottom:1px solid #f5f5f5;background:#fafafa">Spiel <?= $ko_match_num ?><?php if (!empty($m['court_no']) && can_edit()): ?> · <?= e($court_sg) ?> <?= (int)$m['court_no'] ?><?php endif; ?></div>
            <!-- Spieler 1 -->
            <?php $isFirstRound = ($ri === 0); ?>
            <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score1'] > $m['score2']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                 style="min-height:33px;border-bottom:1px solid #f0f0f0">
              <?php if (!empty($ko_seedings)): ?>
              <small class="ko-seed-badge text-muted" style="font-size:.6rem;white-space:nowrap;flex-shrink:0;min-width:34px"><?= ($m['player1_id'] && ($s = $ko_seedings[$m['player1_id']] ?? null)) ? '(' . e($s) . ')' : '' ?></small>
              <?php endif; ?>
              <span class="flex-grow-1 small text-truncate<?= ($ko_no_results && (can_edit() && !$locked) && $isFirstRound) ? ' ko-slot' : '' ?>"
                    style="min-width:0"
                    data-pid="<?= (int)($m['player1_id'] ?? 0) ?>"
                    data-name="<?= e($m['p1name'] ?? '') ?>"
                    title="<?= e($m['p1name'] ?? '') ?>"
                    <?php if ($ko_no_results && (can_edit() && !$locked) && $isFirstRound): ?>
                    ondragstart="koSlotDragStart(event)" ondragend="koSlotDragEnd(event)"
                    <?php endif; ?>>
                <?= $m['player1_id'] ? e($m['p1name']) : '<span class="text-muted fst-italic">—</span>' ?>
              </span>
              <?php if (!$ko_use_duels && !$ko_use_sets && (can_edit() && !$locked) && $m['player1_id'] && $m['player2_id']): ?>
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
              <span class="flex-grow-1 small text-truncate<?= ($ko_no_results && (can_edit() && !$locked) && $isFirstRound) ? ' ko-slot' : '' ?>"
                    style="min-width:0"
                    data-pid="<?= (int)($m['player2_id'] ?? 0) ?>"
                    data-name="<?= e($m['p2name'] ?? '') ?>"
                    title="<?= e($m['p2name'] ?? '') ?>"
                    <?php if ($ko_no_results && (can_edit() && !$locked) && $isFirstRound): ?>
                    ondragstart="koSlotDragStart(event)" ondragend="koSlotDragEnd(event)"
                    <?php endif; ?>>
                <?= $m['player2_id'] ? e($m['p2name']) : '<span class="text-muted fst-italic">—</span>' ?>
              </span>
              <?php if (!$ko_use_duels && !$ko_use_sets && (can_edit() && !$locked) && $m['player1_id'] && $m['player2_id']): ?>
              <input type="number" name="matches[<?= $m['id'] ?>][score2]" min="0"
                     form="ko-form"
                     class="form-control form-control-sm text-center ms-auto" style="width:40px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0"
                     value="<?= $m['played'] ? $m['score2'] : '' ?>">
              <?php elseif ($m['played']): ?>
              <span class="fw-bold small ms-auto" style="flex-shrink:0"><?= $m['score2'] ?></span>
              <?php endif; ?>
            </div>
            <?php if ($ko_use_duels && $m['player1_id'] && $m['player2_id']): ?>
            <?php
              $ko_t1members = $team_members[(int)($m['team1_id'] ?? 0)] ?? [];
              $ko_t2members = $team_members[(int)($m['team2_id'] ?? 0)] ?? [];
              $ko_duel_rows = $existing_duels[$m['id']] ?? [];
              $ko_ts = (int)($c['team_size'] ?? 0);
            ?>
            <div style="border-top:1px solid #f0f0f0;padding:3px 6px">
              <details>
                <summary style="font-size:.72rem;cursor:pointer;color:#0d6efd;user-select:none;list-style:none;padding:2px 0">
                  &#9656; <?= $m['played'] ? $m['score1'].':'.$m['score2'].' (Duelle)' : 'Duelle' ?>
                </summary>
                <?php if ((can_edit() && !$locked)): ?>
                <form class="duel-form" id="duel-form-<?= $m['id'] ?>" method="post" action="<?= url('match/'.$m['id'].'/duels') ?>" style="margin-top:4px">
                  <?= csrf_field() ?>
                  <table class="table table-sm align-middle mb-1 duel-rows" style="font-size:.72rem;table-layout:fixed">
                    <colgroup><col><col style="width:76px"><col></colgroup>
                    <tbody>
                    <?php for ($i = 0; $i < $ko_ts; $i++):
                          $d = $ko_duel_rows[$i] ?? []; ?>
                    <tr>
                      <td>
                        <select name="duels[<?= $i ?>][player1_id]" class="form-select form-select-sm duel-player-select" data-side="p1" style="font-size:.7rem">
                          <option value="">—</option>
                          <?php foreach ($ko_t1members as $pl): ?>
                          <option value="<?= $pl['id'] ?>"<?= (($d['player1_id'] ?? null) == $pl['id']) ? ' selected' : '' ?>><?= e($pl['fullname']) ?></option>
                          <?php endforeach; ?>
                          <option value="__doppel__"<?= (($d['duel_label'] ?? null) === 'Doppel') ? ' selected' : '' ?>>Doppel</option>
                        </select>
                      </td>
                      <td>
                        <div class="d-flex align-items-center justify-content-center gap-1">
                          <input type="number" name="duels[<?= $i ?>][score1]" min="0"
                                 class="form-control form-control-sm text-center duel-score" style="width:32px;height:22px;font-size:.7rem;padding:0 2px"
                                 value="<?= (isset($d['score1']) && $d['played']) ? $d['score1'] : '' ?>">
                          <span style="font-size:.7rem">:</span>
                          <input type="number" name="duels[<?= $i ?>][score2]" min="0"
                                 class="form-control form-control-sm text-center duel-score" style="width:32px;height:22px;font-size:.7rem;padding:0 2px"
                                 value="<?= (isset($d['score2']) && $d['played']) ? $d['score2'] : '' ?>">
                        </div>
                      </td>
                      <td>
                        <select name="duels[<?= $i ?>][player2_id]" class="form-select form-select-sm duel-player-select" data-side="p2" style="font-size:.7rem">
                          <option value="">—</option>
                          <?php foreach ($ko_t2members as $pl): ?>
                          <option value="<?= $pl['id'] ?>"<?= (($d['player2_id'] ?? null) == $pl['id']) ? ' selected' : '' ?>><?= e($pl['fullname']) ?></option>
                          <?php endforeach; ?>
                          <option value="__doppel__"<?= (($d['duel_label'] ?? null) === 'Doppel') ? ' selected' : '' ?>>Doppel</option>
                        </select>
                      </td>
                    </tr>
                    <?php endfor; ?>
                    </tbody>
                  </table>
                  <div class="d-flex align-items-center justify-content-between mb-1" style="font-size:.72rem">
                    <span class="d-flex align-items-center gap-1">
                      <span class="text-muted">Gesamt:</span>
                      <input type="number" name="total_score1" min="0"
                             class="form-control form-control-sm text-center duel-total-input" style="width:32px;height:22px;font-size:.7rem;padding:0 2px"
                             value="<?= $m['played'] ? (int)$m['score1'] : '' ?>" title="Gesamtergebnis (optional)">
                      <span>:</span>
                      <input type="number" name="total_score2" min="0"
                             class="form-control form-control-sm text-center duel-total-input" style="width:32px;height:22px;font-size:.7rem;padding:0 2px"
                             value="<?= $m['played'] ? (int)$m['score2'] : '' ?>" title="Gesamtergebnis (optional)">
                    </span>
                    <button type="submit" class="btn btn-primary btn-sm py-0 px-2" style="font-size:.72rem">Speichern</button>
                  </div>
                </form>
                <?php if ($m['played']): ?>
                <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>" style="display:inline"
                      data-confirm="Ergebnis wirklich löschen?">
                  <?= csrf_field() ?>
                  <button class="btn btn-link text-danger p-0" style="font-size:.7rem;line-height:1.4" title="Ergebnis löschen">
                    <i class="bi bi-x-circle"></i>
                  </button>
                </form>
                <?php endif; ?>
                <?php if ($m['played'] && (int)$m['score1'] === (int)$m['score2']): ?>
                <?php $ko_tb = (int)($m['tiebreak_winner'] ?? 0); ?>
                <div class="mt-1 pt-1" style="border-top:1px dashed #dee2e6;font-size:.72rem">
                  <?php if ($ko_tb === 0): ?>
                  <div class="d-flex align-items-center gap-1 flex-wrap">
                    <span class="text-warning-emphasis fw-semibold"><i class="bi bi-trophy me-1"></i>Tiebreak:</span>
                    <form method="post" action="<?= url('match/'.$m['id'].'/advance/1') ?>" style="display:inline">
                      <?= csrf_field() ?>
                      <button class="btn btn-outline-primary btn-sm py-0 px-1" style="font-size:.7rem">
                        <i class="bi bi-arrow-right-circle me-1"></i><?= e($m['p1name'] ?? 'Team 1') ?>
                      </button>
                    </form>
                    <form method="post" action="<?= url('match/'.$m['id'].'/advance/2') ?>" style="display:inline">
                      <?= csrf_field() ?>
                      <button class="btn btn-outline-primary btn-sm py-0 px-1" style="font-size:.7rem">
                        <i class="bi bi-arrow-right-circle me-1"></i><?= e($m['p2name'] ?? 'Team 2') ?>
                      </button>
                    </form>
                  </div>
                  <?php else: ?>
                  <span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>Tiebreak: <?= e($ko_tb === 1 ? ($m['p1name'] ?? 'Team 1') : ($m['p2name'] ?? 'Team 2')) ?> vorgerückt</span>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php elseif (!empty($ko_duel_rows)): ?>
                <table class="table table-sm align-middle mb-0 mt-1 duel-rows" style="font-size:.72rem;table-layout:fixed">
                  <colgroup><col><col style="width:60px"><col></colgroup>
                  <tbody>
                    <?php foreach ($ko_duel_rows as $d): ?>
                    <tr>
                      <td class="py-0 text-end"><?= ($d['duel_label'] ?? null) === 'Doppel' ? '<em>Doppel</em>' : e($d['player1_name'] ?? '—') ?></td>
                      <td class="text-center py-0 fw-semibold"><?= $d['played'] ? $d['score1'].':'.$d['score2'] : '—:—' ?></td>
                      <td class="py-0"><?= ($d['duel_label'] ?? null) === 'Doppel' ? '<em>Doppel</em>' : e($d['player2_name'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php endif; ?>
              </details>
            </div>
            <?php elseif ($ko_use_sets && $m['player1_id'] && $m['player2_id']): ?>
            <?php $ko_set_rows = $existing_sets[$m['id']] ?? []; ?>
            <div style="border-top:1px solid #f0f0f0;padding:3px 6px">
              <details>
                <summary style="font-size:.72rem;cursor:pointer;color:#0d6efd;user-select:none;list-style:none;padding:2px 0">
                  &#9656; <?= $m['played'] ? $m['score1'].':'.$m['score2'].' (Sätze)' : 'Sätze' ?>
                </summary>
                <?php if ((can_edit() && !$locked)): ?>
                <form class="sets-form" method="post" action="<?= url('match/'.$m['id'].'/sets') ?>"
                      style="margin-top:4px" data-mid="<?= $m['id'] ?>">
                  <?= csrf_field() ?>
                  <div class="d-flex flex-wrap align-items-center justify-content-center" style="gap:40px" id="sets-container-<?= $m['id'] ?>">
                  <?php
                    $ko_sc = max(count($ko_set_rows), 1);
                    for ($si = 0; $si < $ko_sc; $si++):
                      $sr = $ko_set_rows[$si] ?? [];
                  ?>
                    <div class="sets-pair d-flex align-items-center gap-1">
                      <input type="number" name="sets[<?= $si ?>][score1]" min="0"
                             class="form-control form-control-sm text-center sets-score"
                             style="width:32px;height:22px;font-size:.7rem;padding:0 2px"
                             value="<?= isset($sr['score1']) ? (int)$sr['score1'] : '' ?>">
                      <span style="font-size:.7rem">:</span>
                      <input type="number" name="sets[<?= $si ?>][score2]" min="0"
                             class="form-control form-control-sm text-center sets-score"
                             style="width:32px;height:22px;font-size:.7rem;padding:0 2px"
                             value="<?= isset($sr['score2']) ? (int)$sr['score2'] : '' ?>">
                    </div>
                  <?php endfor; ?>
                  </div>
                  <div class="d-flex justify-content-center align-items-center gap-1 mt-1 mb-1">
                    <button type="button" class="sets-add-btn btn btn-outline-secondary btn-sm py-0 px-1"
                            style="font-size:.7rem" data-mid="<?= $m['id'] ?>">
                      <i class="bi bi-plus"></i>
                    </button>
                    <button type="button" class="sets-remove-btn btn btn-outline-secondary btn-sm py-0 px-1"
                            style="font-size:.7rem" data-mid="<?= $m['id'] ?>">
                      <i class="bi bi-dash"></i>
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm py-0 px-2" style="font-size:.72rem">Speichern</button>
                    <?php if ($m['played']): ?>
                    <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>" style="display:inline"
                          data-confirm="Ergebnis wirklich löschen?">
                      <?= csrf_field() ?>
                      <button class="btn btn-link text-danger p-0" style="font-size:.7rem;line-height:1.4" title="Ergebnis löschen">
                        <i class="bi bi-x-circle"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                </form>
                <?php elseif (!empty($ko_set_rows)): ?>
                <div class="d-flex flex-wrap gap-1 mt-1" style="font-size:.72rem">
                  <?php foreach ($ko_set_rows as $sr): ?>
                  <span class="fw-semibold"><?= (int)$sr['score1'] ?>:<?= (int)$sr['score2'] ?></span>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </details>
            </div>
            <?php elseif (!$ko_use_sets && (can_edit() && !$locked) && $m['player1_id'] && $m['player2_id']): ?>
            <div style="border-top:1px solid #f0f0f0;padding:0 4px 1px;text-align:right">
              <?php if ($m['played']): ?>
              <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>" class="js-bracket-clear d-inline"
                    data-mid="<?= $m['id'] ?>" data-area="ko-area-<?= $c['id'] ?>" data-confirm="Ergebnis wirklich löschen?">
                <?= csrf_field() ?>
                <button class="btn btn-link text-danger p-0" style="font-size:.7rem;line-height:1.4" title="Ergebnis löschen">
                  <i class="bi bi-x-circle"></i>
                </button>
              </form>
              <?php else: ?>
              <button type="button" class="btn btn-link text-primary p-0 save-bracket-result" style="font-size:.7rem;line-height:1.4"
                      title="Dieses Ergebnis speichern" data-mid="<?= $m['id'] ?>" data-url="<?= url('match/'.$m['id'].'/result') ?>"
                      data-csrf="<?= csrf_token() ?>" data-area="ko-area-<?= $c['id'] ?>"><i class="bi bi-save"></i></button>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if ($ri === $last_ri): ?></div><?php endif; ?>
          <?php if ($__last_col_3rd): ?><div class="ko-third-place" style="position:absolute;left:8px;right:8px"><?= $third_place_html ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <!-- SVG Verbindungslinien -->
        <svg id="bracket-svg-<?= $c['id'] ?>" style="position:absolute;top:0;left:0;pointer-events:none;overflow:visible"
             width="<?= $bracket_w ?>" height="<?= $bracket_h ?>"></svg>
      </div>
      <!-- Speichern-Button -->
      <?php if ((can_edit() && !$locked) && !$ko_use_sets): ?>
      <div class="mt-2">
        <button form="ko-form" type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-save me-1"></i>Ergebnisse speichern
        </button>
      </div>
      <?php endif; ?>

      <?php /* Spiel um Platz 3 wird oben in der letzten Bracket-Spalte (unter dem Finale) gerendert */ ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($cross_blocks): ?>
    <!-- ═══ Kreuzspiele / Platzierungs-Brackets ═════════════════════════════════ -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <?php if ($show_section_titles): ?><h4 class="h5 mb-0"><i class="bi bi-trophy me-2 text-warning"></i>Kreuzspiele</h4><?php endif; ?>
      <?php if (can_edit()): ?>
      <span class="btn-group btn-group-sm ms-auto">
        <a href="<?= url('competition/'.$c['id'].'/pdf/cross') ?>" class="btn btn-outline-danger" target="_blank" title="Platzierungs-PDF"><i class="bi bi-file-earmark-pdf"></i></a>
        <a href="<?= url('competition/'.$c['id'].'/pdf/match-cards') ?>" class="btn btn-outline-secondary" target="_blank" title="Match-Cards"><i class="bi bi-card-text"></i></a>
        <?php if ($is_team): ?>
        <a href="<?= url('competition/'.$c['id'].'/pdf/team-strips') ?>" class="btn btn-outline-secondary" target="_blank" title="Teampläne"><i class="bi bi-list-task"></i></a>
        <?php endif; ?>
        <?php if ((int)($c['num_courts'] ?? 0) > 0): ?>
        <a href="<?= url('competition/'.$c['id'].'/pdf/court-plans') ?>" class="btn btn-outline-secondary" target="_blank" title="<?= e($court_sg) ?>pläne"><i class="bi bi-geo-alt"></i></a>
        <?php endif; ?>
      </span>
      <?php endif; ?>
    </div>
    <?php $cross_editable = (can_edit() && !$locked); ?>
    <?php if ($cross_editable): ?>
    <form id="cross-form" method="post" action="<?= url('competition/'.$c['id'].'/results/bulk') ?>"><?= csrf_field() ?></form>
    <?php endif; ?>
    <div id="cross-area-<?= $c['id'] ?>">
    <?php if (count($cross_blocks) > 1): ?>
    <ul class="nav nav-tabs no-hash mb-3" id="cross-tabs" role="tablist">
      <?php foreach ($cross_blocks as $bi => $blk): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link<?= $bi === 0 ? ' active' : '' ?>" data-bs-toggle="tab" type="button" role="tab"
                data-bs-target="#crosspane-<?= $bi ?>"><i class="bi bi-diagram-3 me-1"></i><?= e($blk['label']) ?></button>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <div class="tab-content" id="cross-tab-content">
    <?php foreach ($cross_blocks as $bi => $blk): ?>
    <div class="tab-pane fade<?= $bi === 0 ? ' show active' : '' ?>" id="crosspane-<?= $bi ?>" role="tabpanel">
    <div class="card shadow-sm mb-4">
      <div class="card-header fw-semibold"><i class="bi bi-diagram-3 me-1"></i><?= e($blk['label']) ?></div>
      <div class="card-body p-2" style="overflow-x:auto">
        <div style="display:flex; gap:14px; align-items:flex-start">
          <?php foreach ($blk['rounds'] as $ri => $rd): ?>
          <div style="display:flex;flex-direction:column;gap:8px;min-width:190px">
            <div class="small text-muted text-center fw-semibold">Runde <?= $ri + 1 ?></div>
            <?php foreach ($rd['matches'] as $m):
              $has1 = !empty($m['player1_id']); $has2 = !empty($m['player2_id']);
              if (!$has1 && !$has2) continue; ?>
            <div class="ko-match" style="border:1px solid #dee2e6;border-radius:6px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.06)">
              <div style="font-size:.62rem;color:#9e9e9e;padding:1px 6px;border-bottom:1px solid #f5f5f5;background:#fafafa">
                <?= e($m['place_label']) ?><?php if (!empty($m['court_no']) && can_edit()): ?> · <?= e($court_sg) ?> <?= (int)$m['court_no'] ?><?php endif; ?>
              </div>
              <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score1'] > $m['score2']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                   style="min-height:30px;border-bottom:1px solid #f0f0f0">
                <span class="flex-grow-1 small text-truncate" style="min-width:0" title="<?= e($m['p1name'] ?? '') ?>"><?= $has1 ? e($m['p1name']) : '<span class="text-muted fst-italic">—</span>' ?></span>
                <?php if ($cross_editable && $has1 && $has2): ?>
                <input type="number" name="matches[<?= $m['id'] ?>][score1]" min="0" form="cross-form"
                       class="form-control form-control-sm text-center" style="width:40px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0"
                       value="<?= $m['played'] ? $m['score1'] : '' ?>">
                <?php elseif ($m['played']): ?>
                <span class="fw-bold small" style="flex-shrink:0"><?= $m['score1'] ?></span>
                <?php endif; ?>
              </div>
              <div class="d-flex align-items-center gap-1 px-2 <?= ($m['played'] && $m['score2'] > $m['score1']) ? 'bg-success-subtle fw-semibold' : '' ?>"
                   style="min-height:30px">
                <span class="flex-grow-1 small text-truncate" style="min-width:0" title="<?= e($m['p2name'] ?? '') ?>"><?= $has2 ? e($m['p2name']) : '<span class="text-muted fst-italic">Freilos</span>' ?></span>
                <?php if ($cross_editable && $has1 && $has2): ?>
                <input type="number" name="matches[<?= $m['id'] ?>][score2]" min="0" form="cross-form"
                       class="form-control form-control-sm text-center" style="width:40px;height:24px;padding:0 2px;font-size:.8rem;flex-shrink:0"
                       value="<?= $m['played'] ? $m['score2'] : '' ?>">
                <?php elseif ($m['played']): ?>
                <span class="fw-bold small" style="flex-shrink:0"><?= $m['score2'] ?></span>
                <?php endif; ?>
              </div>
              <?php if ($cross_editable && $has1 && $has2): ?>
              <div style="border-top:1px solid #f0f0f0;padding:0 4px 1px;text-align:right">
                <?php if ($m['played']): ?>
                <form method="post" action="<?= url('match/'.$m['id'].'/result/clear') ?>" class="js-bracket-clear d-inline"
                      data-mid="<?= $m['id'] ?>" data-area="cross-area-<?= $c['id'] ?>" data-confirm="Ergebnis wirklich löschen?">
                  <?= csrf_field() ?>
                  <button class="btn btn-link text-danger p-0" style="font-size:.7rem;line-height:1.4" title="Ergebnis löschen"><i class="bi bi-x-circle"></i></button>
                </form>
                <?php else: ?>
                <button type="button" class="btn btn-link text-primary p-0 save-bracket-result" style="font-size:.7rem;line-height:1.4"
                        title="Dieses Ergebnis speichern" data-mid="<?= $m['id'] ?>" data-url="<?= url('match/'.$m['id'].'/result') ?>"
                        data-csrf="<?= csrf_token() ?>" data-area="cross-area-<?= $c['id'] ?>"><i class="bi bi-save"></i></button>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    </div><!-- /.tab-pane -->
    <?php endforeach; ?>
    </div><!-- /#cross-tab-content -->
    </div><!-- /#cross-area -->
    <?php if ($cross_editable): ?>
    <button form="cross-form" type="submit" class="btn btn-primary btn-sm mb-4"><i class="bi bi-save me-1"></i>Ergebnisse speichern</button>
    <?php endif; ?>
    <?php endif; ?>
<?php
$__ko_html = ob_get_clean();
// Leerraum zwischen den Abschnitten (Gruppen ↔ KO-Runde/Kreuzspiele). Die Abschnitts-
// Überschriften stehen jeweils im Kopf des Abschnitts (auf einer Ebene mit den Icons).
$__has_groups = trim($__groups_html) !== '';
$__has_ko     = trim($__ko_html) !== '';
$__gap = ($__has_groups && $__has_ko)
    ? '<hr class="my-5" style="border:0;border-top:3px solid var(--bs-primary,#0d6efd);opacity:.45">'
    : '';
// Aktuelle Bewerbs-Stage immer oben: in der KO-Phase das KO-Bracket über die Gruppenphase stellen.
echo in_array($c['phase'], ['ko', 'done'], true) ? $__ko_html . $__gap . $__groups_html : $__groups_html . $__gap . $__ko_html;
?>

<?php if ($c['mode'] === 'double_ko' && ($dko_wb || $dko_lb || $dko_gf)): ?>
<!-- ═══ Doppel-KO-Bracket ════════════════════════════════════════════════════ -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <?php if ($show_section_titles): ?><h4 class="h5 mb-0"><i class="bi bi-diagram-2 me-2 text-warning"></i>Doppel-KO</h4><?php endif; ?>
  <?php if (can_edit()): ?>
  <span class="btn-group btn-group-sm ms-auto">
    <a href="<?= url('competition/'.$c['id'].'/pdf/ko') ?>" class="btn btn-outline-danger" target="_blank" title="KO-PDF"><i class="bi bi-file-earmark-pdf"></i></a>
    <a href="<?= url('competition/'.$c['id'].'/pdf/match-cards') ?>" class="btn btn-outline-secondary" target="_blank" title="Match-Cards"><i class="bi bi-card-text"></i></a>
    <?php if ($is_team): ?>
    <a href="<?= url('competition/'.$c['id'].'/pdf/team-strips') ?>" class="btn btn-outline-secondary" target="_blank" title="Teampläne"><i class="bi bi-list-task"></i></a>
    <?php endif; ?>
    <?php if ((int)($c['num_courts'] ?? 0) > 0): ?>
    <a href="<?= url('competition/'.$c['id'].'/pdf/court-plans') ?>" class="btn btn-outline-secondary" target="_blank" title="<?= e($court_sg) ?>pläne"><i class="bi bi-geo-alt"></i></a>
    <?php endif; ?>
  </span>
  <?php endif; ?>
</div>
<?php $wb_num_map = []; // wird im WB-Block befüllt; hier vorbelegen für LB-Block

// Helper: render one DKO match card
function _dko_match_card(array $m, string $form_id, bool $editable, ?int $match_num = null, ?string $p1ph = null, ?string $p2ph = null, array $seedings = [], bool $wide = false, string $clear_area = ''): string {
    $p1 = $m['p1name'] ?? null;
    $p2 = $m['p2name'] ?? null;
    $has_both = $m['player1_id'] && $m['player2_id'];
    $p1win = $m['played'] && $m['score1'] > $m['score2'];
    $p2win = $m['played'] && $m['score2'] > $m['score1'];
    $min_w = $wide ? 225 : 150;
    $o = '<div class="ko-match" style="border:1px solid #dee2e6;border-radius:6px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden;min-width:' . $min_w . 'px">';
    $hdr = [];
    if ($match_num !== null) $hdr[] = 'Spiel ' . $match_num;
    if (!empty($m['court_no']) && can_edit()) $hdr[] = $court_sg . ' ' . (int)$m['court_no'];
    if ($hdr) {
        $o .= '<div style="font-size:.65rem;color:#9e9e9e;padding:1px 6px;border-bottom:1px solid #f5f5f5;background:#fafafa">' . implode(' · ', $hdr) . '</div>';
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
    if ($editable && $has_both) {
        $o .= '<div style="border-top:1px solid #f0f0f0;padding:0 4px 1px;text-align:right">';
        if ($m['played']) {
            $o .= '<form method="post" action="' . url('match/' . $m['id'] . '/result/clear') . '" class="js-bracket-clear d-inline"'
                . ' data-mid="' . (int)$m['id'] . '" data-area="' . e($clear_area) . '" data-confirm="Ergebnis wirklich löschen?">'
                . csrf_field()
                . '<button class="btn btn-link text-danger p-0" style="font-size:.7rem" title="Ergebnis löschen"><i class="bi bi-x-circle"></i></button>'
                . '</form>';
        } else {
            $o .= '<button type="button" class="btn btn-link text-primary p-0 save-bracket-result" style="font-size:.7rem"'
                . ' title="Dieses Ergebnis speichern" data-mid="' . (int)$m['id'] . '" data-url="' . url('match/' . $m['id'] . '/result') . '"'
                . ' data-csrf="' . e(csrf_token()) . '" data-area="' . e($clear_area) . '"><i class="bi bi-save"></i></button>';
        }
        $o .= '</div>';
    }
    $o .= '</div>';
    return $o;
}
?>

<div id="dko-area-<?= $c['id'] ?>">
<ul class="nav nav-tabs no-hash mb-3" id="dko-tabs" role="tablist">
  <?php if ($dko_wb): ?><li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" type="button" role="tab" data-bs-target="#dko-wb-pane"><i class="bi bi-trophy me-1 text-warning"></i>Winners Bracket</button></li><?php endif; ?>
  <?php if ($dko_lb): ?><li class="nav-item" role="presentation"><button class="nav-link<?= !$dko_wb ? ' active' : '' ?>" data-bs-toggle="tab" type="button" role="tab" data-bs-target="#dko-lb-pane"><i class="bi bi-arrow-down-circle me-1 text-danger"></i>Losers Bracket</button></li><?php endif; ?>
  <?php if ($dko_gf): ?><li class="nav-item" role="presentation"><button class="nav-link<?= (!$dko_wb && !$dko_lb) ? ' active' : '' ?>" data-bs-toggle="tab" type="button" role="tab" data-bs-target="#dko-gf-pane"><i class="bi bi-star-fill me-1 text-warning"></i>Großes Finale</button></li><?php endif; ?>
</ul>
<div class="tab-content" id="dko-tab-content">

<?php if ($dko_wb):
  $dko_wb_arr  = array_values($dko_wb);
  $dko_first_n = count(reset($dko_wb_arr)['matches']);
  $dko_slot_h  = 115;
  $dko_wb_h    = $dko_first_n * $dko_slot_h;
  $dko_col_w   = $is_team ? 405 : 270;
  $dko_wb_w    = count($dko_wb_arr) * $dko_col_w;
  // Sequentielle Spielnummern für WB (Runde 1 top→bottom, Runde 2 top→bottom, ...)
  $wb_num_map = []; $wb_n = 0;
  foreach ($dko_wb_arr as $rd) {
      foreach ($rd['matches'] as $wm) {
          $wb_n++;
          $wb_num_map[(int)$wm['ko_round']][(int)$wm['ko_position']] = $wb_n;
      }
  }
?>
<div class="tab-pane fade show active" id="dko-wb-pane" role="tabpanel">
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold"><i class="bi bi-trophy me-1 text-warning"></i>Winners Bracket</div>
  <div class="card-body p-3">
    <?php if ((can_edit() && !$locked)): ?>
    <form id="dko-wb-form" method="post" action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>
    <div style="overflow-x:auto">
      <!-- Rundenüberschriften -->
      <div style="display:flex;width:<?= $dko_wb_w ?>px">
        <?php $last_wb_ri = count($dko_wb_arr) - 1; $ri = 0; foreach ($dko_wb as $rd): ?>
        <div style="width:<?= $dko_col_w ?>px;flex-shrink:0;text-align:center;font-weight:600;font-size:.8rem;padding:0 8px 4px;
                    color:<?= $ri === $last_wb_ri ? '#856404' : '#6c757d' ?>">
          <?= $ri === $last_wb_ri ? '🏆 ' : '' ?><?= e($rd['name']) ?>
        </div>
        <?php $ri++; endforeach; ?>
      </div>
      <!-- Bracket-Körper (exakt wie normaler KO) -->
      <div id="dko-wb-bracket-<?= $c['id'] ?>" style="display:flex;position:relative;height:<?= $dko_wb_h ?>px;width:<?= $dko_wb_w ?>px">
        <?php foreach ($dko_wb as $rd): ?>
        <div class="ko-round" style="display:flex;flex-direction:column;justify-content:space-around;width:<?= $dko_col_w ?>px;flex-shrink:0;height:100%;padding:0 8px">
          <?php foreach ($rd['matches'] as $m): ?>
          <?= _dko_match_card($m, 'dko-wb-form', (can_edit() && !$locked), $wb_num_map[(int)$m['ko_round']][(int)$m['ko_position']] ?? null, null, null, $ko_seedings, $is_team, 'dko-area-'.$c['id']) ?>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <svg id="dko-wb-svg-<?= $c['id'] ?>" style="position:absolute;top:0;left:0;pointer-events:none;overflow:visible"
             width="<?= $dko_wb_w ?>" height="<?= $dko_wb_h ?>"></svg>
      </div>
      <?php if ((can_edit() && !$locked)): ?>
      <div class="mt-2">
        <button form="dko-wb-form" type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-save me-1"></i>Ergebnisse speichern
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div><!-- /.tab-pane WB -->
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
  $lb_slot_h   = 115;
  $lb_total_h  = max(1, $lb_r1_count) * $lb_slot_h;
  $lb_col_w    = $is_team ? 330 : 220;
  $lb_total_w  = count($lb_rd_arr) * $lb_col_w;
?>
<div class="tab-pane fade<?= !$dko_wb ? ' show active' : '' ?>" id="dko-lb-pane" role="tabpanel">
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold"><i class="bi bi-arrow-down-circle me-1 text-danger"></i>Losers Bracket</div>
  <div class="card-body p-3">
    <?php if ((can_edit() && !$locked)): ?>
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
              $m, 'dko-lb-form', (can_edit() && !$locked), $lb_match_num,
              !$m['player1_id'] ? ($lb_ph[$lr][$lp][1] ?? null) : null,
              !$m['player2_id'] ? ($lb_ph[$lr][$lp][2] ?? null) : null,
              [], $is_team, 'dko-area-'.$c['id']
          ) ?>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <svg id="dko-lb-svg-<?= $c['id'] ?>" style="position:absolute;top:0;left:0;pointer-events:none;overflow:visible"
             width="<?= $lb_total_w ?>" height="<?= $lb_total_h ?>"></svg>
      </div>
    </div>
    <?php if ((can_edit() && !$locked)): ?>
    <div class="mt-2">
      <button form="dko-lb-form" type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-save me-1"></i>LB Ergebnisse speichern
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>
</div><!-- /.tab-pane LB -->
<?php endif; ?>

<?php if ($dko_gf): ?>
<div class="tab-pane fade<?= (!$dko_wb && !$dko_lb) ? ' show active' : '' ?>" id="dko-gf-pane" role="tabpanel">
<div class="card shadow-sm mb-4 border-primary">
  <div class="card-header fw-semibold text-primary"><i class="bi bi-star-fill me-1 text-warning"></i>Großes Finale</div>
  <div class="card-body p-3">
    <?php if ((can_edit() && !$locked)): ?>
    <form id="dko-gf-form" method="post" action="<?= url('competition/'.$c['id'].'/results/bulk') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>
    <div class="d-flex gap-3 align-items-start">
      <div style="min-width:<?= $is_team ? 300 : 200 ?>px;max-width:<?= $is_team ? 450 : 300 ?>px">
        <?= _dko_match_card($dko_gf, 'dko-gf-form', (can_edit() && !$locked), null, null, null, [], $is_team, 'dko-area-'.$c['id']) ?>
      </div>
      <div class="small text-muted align-self-center">
        <div><strong>Spieler 1:</strong> WB-Sieger (ungeschlagen)</div>
        <div><strong>Spieler 2:</strong> LB-Sieger (1 Niederlage)</div>
      </div>
    </div>
    <?php if ((can_edit() && !$locked)): ?>
    <div class="mt-3">
      <button form="dko-gf-form" type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-save me-1"></i>Finale speichern
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>
</div><!-- /.tab-pane GF -->
<?php endif; ?>
</div><!-- /#dko-tab-content -->
</div><!-- /#dko-area -->

<?php elseif ($c['mode'] === 'double_ko' && $c['phase'] === 'setup'): ?>
<!-- DKO: noch nicht ausgelost -->
<div class="alert alert-info">
  <i class="bi bi-info-circle me-1"></i>
  Doppel-KO-Bracket noch nicht ausgelost. Spieler hinzufügen und im Tab <strong>Bewerb</strong> auslosen.
</div>
<?php endif; ?>

  </div><!-- /tab-competition -->
</div><!-- /tab-content -->

<?php
$extra_js = <<<'JS'
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<style>
.add-entry-item:hover { background: var(--bs-tertiary-bg); }
.add-entry-item:has(input:checked) { background: var(--bs-primary-bg-subtle); }
@keyframes resultSavedFlash { 0% { background: #d1e7dd; } 100% { background: transparent; } }
.result-saved-flash { animation: resultSavedFlash 1.2s ease-out; border-radius: 4px; }
/* Read-only-Kästchen (Gäste/Viewer/gesperrte Phase) im Look der Eingabefelder */
.score-box { display:inline-flex; align-items:center; justify-content:center; width:100%;
  min-height:31px; padding:.2rem .3rem; font-size:.875rem; color:#212529;
  background:#fff; border:1px solid #ced4da; border-radius:.25rem; }
/* Ergebnisfärbung der Score-Kästchen (gedämpft, Inhalt lesbar) – Eingabefelder + Read-only */
.score-win  { background-color: #d1e7dd !important; border-color: #a3cfbb !important; }
.score-loss { background-color: #f8d7da !important; border-color: #e9a3ac !important; }
.score-draw { background-color: #cfe2ff !important; border-color: #9ec5fe !important; }
/* Gespeicherten Wert exakt zentrieren: Spinner ausblenden (reservieren sonst Platz) */
input.form-control[name^="matches"][readonly] { text-align: center !important; -moz-appearance: textfield; }
input.form-control[name^="matches"][readonly]::-webkit-inner-spin-button,
input.form-control[name^="matches"][readonly]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
</style>
<script>
// ── Einzelnes Ergebnis inline speichern/löschen (ohne Seitenreload) ──────────
// Speichert/löscht nur dieses Ergebnis und tauscht aus der Server-Antwort gezielt
// die Gruppentabelle (#standings-<gid>) und die Aktions-Icons (#match-actions-<mid>)
// aus. Andere, noch nicht gespeicherte Eingaben in der Gruppe bleiben erhalten.
(function() {
  function parseDoc(text) { return new DOMParser().parseFromString(text, 'text/html'); }

  function applyResponse(doc, gid, mid) {
    // Ganze Begegnungszeile (inkl. Felder → readonly/editierbar) und Tabelle austauschen.
    ['match-row-' + mid, 'standings-' + gid].forEach(function(id) {
      var nw = doc.getElementById(id), cur = document.getElementById(id);
      if (nw && cur) cur.replaceWith(nw);
    });
    var alerts = doc.querySelectorAll('.alert-danger, .alert-warning');
    if (alerts.length) {
      alert(Array.prototype.map.call(alerts, function(a) { return a.textContent.trim(); }).join('\n'));
    }
  }

  function flashRow(mid) {
    var row = document.getElementById('match-row-' + mid);
    if (!row) return;
    row.classList.remove('result-saved-flash');
    void row.offsetWidth;            // Reflow erzwingen, damit die Animation neu startet
    row.classList.add('result-saved-flash');
  }

  function postForm(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    }).then(function(r) { return r.text(); });
  }

  // Speichern
  document.addEventListener('click', function(ev) {
    var btn = ev.target.closest('.save-one-result');
    if (!btn) return;
    ev.preventDefault();
    var mid = btn.dataset.mid, gid = btn.dataset.gid;
    var s1 = document.getElementsByName('matches[' + mid + '][score1]')[0];
    var s2 = document.getElementsByName('matches[' + mid + '][score2]')[0];
    if (!s1 || !s2) return;
    if (s1.value === '' || s2.value === '') { alert('Bitte beide Werte eingeben.'); return; }
    btn.disabled = true;
    var body = new URLSearchParams();
    body.append('csrf_token', btn.dataset.csrf);
    body.append('score1', s1.value);
    body.append('score2', s2.value);
    postForm(btn.dataset.url, body.toString())
      .then(function(t) { applyResponse(parseDoc(t), gid, mid); flashRow(mid); })
      .catch(function() { alert('Speichern fehlgeschlagen.'); btn.disabled = false; });
  });

  // Löschen (Bestätigung übernimmt der globale data-confirm-Handler)
  document.addEventListener('submit', function(ev) {
    var form = ev.target.closest('form.js-inline-clear');
    if (!form) return;
    if (ev.defaultPrevented) return;   // Bestätigung wurde abgelehnt
    ev.preventDefault();
    var mid = form.dataset.mid, gid = form.dataset.gid;
    postForm(form.action, new URLSearchParams(new FormData(form)).toString())
      .then(function(t) { applyResponse(parseDoc(t), gid, mid); })
      .catch(function() { alert('Löschen fehlgeschlagen.'); });
  });
})();

// ── Einzelnes Ergebnis inline speichern/löschen für Brackets (KO, Doppel-KO, Kreuzspiele) ──
// Wie bei den Gruppen, aber: durch den Aufstieg ändern sich Folgespiele, daher wird der
// gesamte Bracket-Bereich (data-area) aus der Server-Antwort getauscht und neu gezeichnet.
// Noch ungespeicherte Eingaben und der aktive Unter-Tab bleiben erhalten.
(function() {
  function parseDoc(t) { return new DOMParser().parseFromString(t, 'text/html'); }
  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
      .then(function(r) { return r.text(); });
  }
  function swapArea(doc, areaId) {
    var nw = doc.getElementById(areaId), cur = document.getElementById(areaId);
    if (!nw || !cur) { return; }
    // Ungespeicherte Eingaben (editierbare Score-Felder) merken
    var vals = {};
    cur.querySelectorAll('input[name^="matches["]').forEach(function(i) {
      if (!i.readOnly && i.value !== '') vals[i.name] = i.value;
    });
    // Aktive Unter-Tabs merken
    var active = [];
    cur.querySelectorAll('.nav-link.active[data-bs-target]').forEach(function(b) { active.push(b.getAttribute('data-bs-target')); });
    cur.replaceWith(nw);
    // Eingaben wiederherstellen (nur noch editierbare Felder)
    Object.keys(vals).forEach(function(name) {
      var el = document.getElementsByName(name)[0];
      if (el && !el.readOnly) el.value = vals[name];
    });
    // Aktive Tabs wiederherstellen
    active.forEach(function(target) {
      var btn = document.querySelector('[data-bs-target="' + target + '"]');
      if (btn) { try { bootstrap.Tab.getOrCreateInstance(btn).show(); } catch (e) {} }
    });
    if (typeof drawAllBrackets === 'function') drawAllBrackets();
    var alerts = doc.querySelectorAll('.alert-danger, .alert-warning');
    if (alerts.length) alert(Array.prototype.map.call(alerts, function(a) { return a.textContent.trim(); }).join('\n'));
  }
  // Speichern
  document.addEventListener('click', function(ev) {
    var btn = ev.target.closest('.save-bracket-result');
    if (!btn) return;
    ev.preventDefault();
    var mid = btn.dataset.mid;
    var s1 = document.getElementsByName('matches[' + mid + '][score1]')[0];
    var s2 = document.getElementsByName('matches[' + mid + '][score2]')[0];
    if (!s1 || !s2) return;
    if (s1.value === '' || s2.value === '') { alert('Bitte beide Werte eingeben.'); return; }
    if (s1.value === s2.value) { alert('Unentschieden ist im KO-/Kreuz-Bewerb nicht erlaubt.'); return; }
    btn.disabled = true;
    var body = new URLSearchParams();
    body.append('csrf_token', btn.dataset.csrf);
    body.append('score1', s1.value);
    body.append('score2', s2.value);
    post(btn.dataset.url, body.toString())
      .then(function(t) { swapArea(parseDoc(t), btn.dataset.area); })
      .catch(function() { alert('Speichern fehlgeschlagen.'); btn.disabled = false; });
  });
  // Löschen (Bestätigung übernimmt der globale data-confirm-Handler)
  document.addEventListener('submit', function(ev) {
    var form = ev.target.closest('form.js-bracket-clear');
    if (!form) return;
    if (ev.defaultPrevented) return;
    ev.preventDefault();
    post(form.action, new URLSearchParams(new FormData(form)).toString())
      .then(function(t) { swapArea(parseDoc(t), form.dataset.area); })
      .catch(function() { alert('Löschen fehlgeschlagen.'); });
  });
})();

// ── Group Drag-and-Drop ──────────────────────────────────────────
var grpDragEl = null, grpEditActive = false;
function toggleGrpEdit() {
  grpEditActive = !grpEditActive;
  document.querySelectorAll('.grp-normal-view').forEach(function(el) { el.style.display = grpEditActive ? 'none' : ''; });
  document.querySelectorAll('.grp-edit-panel').forEach(function(el) { el.style.display = grpEditActive ? '' : 'none'; });
  // Im Umstellen-Modus alle Gruppen gleichzeitig zeigen (Drag&Drop zwischen Gruppen), Tabs ausblenden
  var tc = document.getElementById('grp-tab-content'); if (tc) tc.classList.toggle('show-all', grpEditActive);
  var nav = document.getElementById('grp-tabs'); if (nav) nav.style.display = grpEditActive ? 'none' : '';
  var tb = document.getElementById('grp-edit-toolbar'); if (tb) tb.style.display = grpEditActive ? 'flex' : 'none';
  var btn = document.getElementById('grp-edit-btn');
  if (btn) btn.innerHTML = grpEditActive ? '<i class="bi bi-x me-1"></i>Abbrechen' : '<i class="bi bi-arrows-move me-1"></i>Umstellen';
}
function grpDragStart(e) {
  grpDragEl = e.currentTarget; e.dataTransfer.effectAllowed = 'move';
  setTimeout(function() { if (grpDragEl) grpDragEl.style.opacity = '0.4'; }, 0);
}
function grpDragEnd() { if (grpDragEl) grpDragEl.style.opacity = ''; grpDragEl = null; }
// Ermittelt die Pill, VOR der eingefügt werden soll (anhand der vertikalen Cursor-Position).
// Liefert null, wenn hinter der letzten Pill losgelassen wurde (= ans Ende anhängen).
function grpDropTarget(panel, y) {
  var pills = Array.prototype.slice.call(panel.querySelectorAll('.grp-player-pill'))
    .filter(function(el) { return el !== grpDragEl; });
  for (var i = 0; i < pills.length; i++) {
    var box = pills[i].getBoundingClientRect();
    if (y < box.top + box.height / 2) return pills[i];
  }
  return null;
}
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

// Einzelspiel-Zeilen (Duelle) aller Team-Begegnungen gemeinsam aus-/einblenden.
// Globaler Schalter; Zustand pro Bewerb in localStorage gespeichert.
function _duelStorageKey() {
  var m = location.pathname.match(/competition\/(\d+)/);
  return 'hideDuels:' + (m ? m[1] : 'x');
}
function _applyDuelVisibility(hide) {
  document.querySelectorAll('.duel-rows').forEach(function(el) {
    el.style.display = hide ? 'none' : '';
  });
  document.querySelectorAll('.toggle-duels-label').forEach(function(lbl) {
    lbl.textContent = hide ? 'Einzelspiele anzeigen' : 'Einzelspiele ausblenden';
  });
}
function toggleAllDuels() {
  var hide = localStorage.getItem(_duelStorageKey()) !== '1';
  localStorage.setItem(_duelStorageKey(), hide ? '1' : '0');
  _applyDuelVisibility(hide);
}
document.addEventListener('DOMContentLoaded', function() {
  if (localStorage.getItem(_duelStorageKey()) === '1') _applyDuelVisibility(true);
});

// Test-Hilfe: füllt alle offenen (leeren) Ergebnis-Paare mit zufälligen, ungleichen
// Werten. save=false → nur ins Formular eintragen; save=true → zusätzlich speichern
// (Bulk-POST der Spielergebnisse, danach Reload). Score-Felder kommen je Spiel/Satz/Duell
// paarweise (score1 direkt vor score2) in Dokumentreihenfolge.
function fillTestResults(save) {
  var inputs = Array.prototype.slice.call(document.querySelectorAll('input[type="number"]'))
    .filter(function(el) { return /\[score[12]\]$/.test(el.name || ''); });
  var filled = 0;
  for (var i = 0; i + 1 < inputs.length; i += 2) {
    var a = inputs[i], b = inputs[i + 1];
    if (a.value !== '' || b.value !== '') continue; // bereits (teil-)ausgefüllt → überspringen
    var s1 = Math.floor(Math.random() * 12);
    var s2 = Math.floor(Math.random() * 12);
    if (s1 === s2) s2 = (s2 + 1) % 12; // kein Unentschieden (für KO/Kreuzspiele nötig)
    a.value = s1; b.value = s2;
    a.dispatchEvent(new Event('input', { bubbles: true }));
    b.dispatchEvent(new Event('input', { bubbles: true }));
    filled++;
  }
  if (!save) {
    if (!filled) alert('Keine offenen Ergebnisfelder gefunden.');
    return;
  }
  // Speichern: alle matches[ID][scoreN] (Gruppen-, KO- und Kreuzspiele) per Bulk-POST senden.
  // Bulk-URL aus einem vorhandenen Ergebnisformular lesen (NOWDOC → kein PHP hier).
  var bulkForm = document.querySelector('form[action$="/results/bulk"]');
  if (!bulkForm) {
    // Team-Bewerbe speichern Begegnungen über die Einzelspiel-Formulare (Duelle). Alle in EINEM
    // Bulk-Request bündeln (sonst je Begegnung ein eigener Request → bei vielen Begegnungen langsam).
    // Als JSON-Body senden, NICHT als Formularfelder: viele Begegnungen sprengen sonst
    // PHP's max_input_vars (Default 1000) und die Daten würden stillschweigend verworfen.
    var tools = document.getElementById('test-tools');
    var duelsUrl = tools ? tools.getAttribute('data-duels-bulk-url') : '';
    var duelForms = document.querySelectorAll('form.duel-form');
    if (duelsUrl && duelForms.length) {
      var tok0 = document.querySelector('input[name="csrf_token"]');
      var payload = { csrf_token: tok0 ? tok0.value : '', dm: {} };
      Array.prototype.forEach.call(duelForms, function(f) {
        var mid = f.id.replace('duel-form-', '');
        var entry = { duels: {}, total_score1: '', total_score2: '' };
        new FormData(f).forEach(function(val, key) {
          var mm = key.match(/^duels\[(\d+)\]\[(\w+)\]$/);
          if (mm) {
            if (!entry.duels[mm[1]]) entry.duels[mm[1]] = {};
            entry.duels[mm[1]][mm[2]] = val;
          } else if (key === 'total_score1' || key === 'total_score2') {
            entry[key] = val;
          }
        });
        payload.dm[mid] = entry;
      });
      fetch(duelsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function() { location.reload(); })
        .catch(function() { alert('Speichern fehlgeschlagen.'); });
      return;
    }
    alert('Kein Speicherformular gefunden.'); return;
  }
  var data = new URLSearchParams();
  document.querySelectorAll('input[type="number"]').forEach(function(el) {
    if (/^matches\[\d+\]\[score[12]\]$/.test(el.name || '') && el.value !== '') data.append(el.name, el.value);
  });
  if (!Array.from(data.keys()).length) { alert('Keine speicherbaren Ergebnisfelder gefunden (Sätze/Duelle bitte manuell speichern).'); return; }
  var tok = document.querySelector('input[name="csrf_token"]');
  if (tok) data.append('csrf_token', tok.value);
  fetch(bulkForm.getAttribute('action'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: data.toString()
  }).then(function() { location.reload(); })
    .catch(function() { alert('Speichern fehlgeschlagen.'); });
}

// Einstellungen: Felder je nach Modus + Finalrunde ein-/ausblenden.
function editToggleCross() {
  var sel = document.getElementById('comp-mode-edit');
  if (!sel) return;
  var mode = sel.value;
  var isGroups = (mode === 'groups_ko');
  var frSel = document.getElementById('finalrunde-edit');
  var fr = frSel ? frSel.value : 'none';
  // Ausblenden mit !important, da manche Wrapper die Klasse d-flex (display:flex !important) tragen.
  var show = function(id, cond) {
    var el = document.getElementById(id);
    if (!el) return;
    if (cond) el.style.removeProperty('display');
    else el.style.setProperty('display', 'none', 'important');
  };
  show('finalrunde-wrap', isGroups);
  show('advance-wrap-edit', isGroups && fr === 'ko');
  show('field-round-limit', isGroups && fr === 'none');
  show('cross-config-wrap', isGroups && fr === 'cross');
  show('third_place_wrap', (mode === 'ko_only' || mode === 'double_ko') || (isGroups && fr === 'ko'));
  // Setzungen anzeigen (KO) nur im KO-/Doppel-KO-Modus
  show('show-seeding-wrap', mode === 'ko_only' || mode === 'double_ko');
}
editToggleCross();

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
      // An die Position einfügen, über der losgelassen wurde (Sortieren innerhalb der Gruppe
      // und zwischen Gruppen). Ohne Ziel-Pill ans Ende anhängen.
      var after = grpDropTarget(panel, e.clientY);
      if (after) panel.insertBefore(grpDragEl, after);
      else panel.appendChild(grpDragEl);
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

// Spiel um Platz 3 direkt unter dem (vertikal zentrierten) Finale positionieren.
function positionThirdPlace() {
  document.querySelectorAll('.ko-third-place').forEach(function(tp) {
    var col = tp.closest('.ko-round'); if (!col) return;
    var fin = col.querySelector('.ko-match'); if (!fin) return;  // erstes .ko-match = Finale
    tp.style.top = (fin.offsetTop + fin.offsetHeight + 16) + 'px';
    // Platz unterhalb reservieren OHNE die Bracket-Höhe zu ändern → das per space-around
    // zentrierte Finale bleibt stabil (Höhe ändern würde es verschieben → instabile Darstellung,
    // je nachdem wie oft die Funktion läuft).
    var bracket = col.closest('[id^="ko-bracket-"]');
    if (bracket) {
      var overflow = (tp.offsetTop + tp.offsetHeight) - bracket.offsetHeight;
      bracket.style.marginBottom = (overflow > 0 ? overflow + 12 : 0) + 'px';
    }
  });
}
function drawAllBrackets() {
  positionThirdPlace();
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
// Nach vollständigem Laden (Schriftarten/Layout fertig) erneut zeichnen → korrekte Maße
// auch beim Aktualisieren der Seite.
window.addEventListener('load', function() { setTimeout(drawAllBrackets, 0); });
window.addEventListener('resize', drawAllBrackets);
// Brackets in zunächst versteckten Tabs (Doppel-KO: WB/LB/GF) erst beim Anzeigen korrekt zeichnen
document.addEventListener('shown.bs.tab', drawAllBrackets);

// ── Bewerbstyp → team_size- und Begegnungsergebnis-Feld ein-/ausblenden ──────────────────
(function() {
  var sel = document.querySelector('select[name="comp_type"]');
  var flds = ['sec-team-hd', 'field-team-size', 'field-team-result', 'field-kickoff', 'field-match-card'].map(function(id){ return document.getElementById(id); });
  if (!sel) return;
  sel.addEventListener('change', function() {
    var show = this.value === 'team';
    flds.forEach(function(f){ if (f) f.style.display = show ? '' : 'none'; });
  });
})();

// ── Zeitplan: nur aktivierbar bei Spielplätzen + Spielrunden; Felder ein-/ausblenden ─────
(function() {
  var cb     = document.getElementById('schedule_enabled');
  var courts = document.querySelector('input[name="num_courts"]');
  var byes   = document.getElementById('show_byes');
  var wrap   = document.getElementById('schedule-fields-wrap');
  var dur    = document.getElementById('schedule_duration');
  var st     = document.getElementById('schedule_start');
  if (!cb || !courts || !byes) return;
  function prereq() { return (parseInt(courts.value || '0', 10) > 0) && byes.checked; }
  function sync() {
    var ok = prereq();
    cb.disabled = !ok;
    if (!ok) cb.checked = false;
    var on = cb.checked && ok;
    if (wrap) wrap.style.display = on ? '' : 'none';
    if (dur) dur.required = on;
    if (st)  st.required  = on;
  }
  [courts, byes, cb].forEach(function(el) {
    el.addEventListener('change', sync);
    el.addEventListener('input', sync);
  });
  sync();
})();

// ── Duel inline: Live-Gesamtergebnis ─────────────────────────────
document.addEventListener('input', function(e) {
  if (!e.target.classList.contains('duel-score')) return;
  var form = e.target.closest('.duel-form');
  if (!form) return;
  var s1 = 0, s2 = 0;
  form.querySelectorAll('tbody tr').forEach(function(row) {
    var inp = row.querySelectorAll('.duel-score');
    if (inp.length < 2) return;
    var v1 = parseInt(inp[0].value), v2 = parseInt(inp[1].value);
    if (!isNaN(v1) && !isNaN(v2)) { if (v1 > v2) s1++; else if (v2 > v1) s2++; }
  });
  var t1 = form.querySelector('.duel-total-1'), t2 = form.querySelector('.duel-total-2');
  if (t1) t1.textContent = s1;
  if (t2) t2.textContent = s2;
});

document.addEventListener('click', function(e) {
  var form = e.target.closest('form');
  if (!form) return;
  if (e.target.closest('.add-select-all')) {
    e.preventDefault();
    form.querySelectorAll('.add-entry-item input[type="checkbox"]').forEach(function(cb) {
      if (cb.closest('.add-entry-item').style.display !== 'none') cb.checked = true;
    });
  }
  if (e.target.closest('.add-select-none')) {
    e.preventDefault();
    form.querySelectorAll('.add-entry-item input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
  }
});


// ── Sätze: + / - Button ──────────────────────────────────────────
document.addEventListener('click', function(e) {
  var rem = e.target.closest('.sets-remove-btn');
  if (rem) {
    e.preventDefault();
    var container = document.getElementById('sets-container-' + rem.dataset.mid);
    if (!container) return;
    var pairs = container.querySelectorAll('.sets-pair');
    if (pairs.length > 1) pairs[pairs.length - 1].remove();
    return;
  }
  var btn = e.target.closest('.sets-add-btn');
  if (!btn) return;
  e.preventDefault();
  var mid = btn.dataset.mid;
  var container = document.getElementById('sets-container-' + mid);
  if (!container) return;
  var idx = container.querySelectorAll('.sets-pair').length;
  var isSmall = container.querySelector('input') && container.querySelector('input').style.width === '32px';
  var w = isSmall ? '32px' : '46px';
  var h = isSmall ? ';height:22px;font-size:.7rem;padding:0 2px' : '';
  var pair = document.createElement('div');
  pair.className = 'sets-pair d-flex align-items-center gap-1';
  pair.innerHTML =
    '<input type="number" name="sets[' + idx + '][score1]" min="0"' +
    ' class="form-control form-control-sm text-center sets-score"' +
    ' style="width:' + w + h + '">' +
    '<span style="font-size:.7rem">:</span>' +
    '<input type="number" name="sets[' + idx + '][score2]" min="0"' +
    ' class="form-control form-control-sm text-center sets-score"' +
    ' style="width:' + w + h + '">';
  container.appendChild(pair);
  pair.querySelector('input').focus();
});

// ── Sätze: Live-Gesamtergebnis ────────────────────────────────────
document.addEventListener('input', function(e) {
  if (!e.target.classList.contains('sets-score')) return;
  var form = e.target.closest('.sets-form');
  if (!form) return;
  var mid = form.dataset.mid;
  var s1 = 0, s2 = 0;
  form.querySelectorAll('.sets-pair').forEach(function(pair) {
    var inp = pair.querySelectorAll('.sets-score');
    if (inp.length < 2) return;
    var v1 = parseInt(inp[0].value), v2 = parseInt(inp[1].value);
    if (!isNaN(v1) && !isNaN(v2)) { if (v1 > v2) s1++; else if (v2 > v1) s2++; }
  });
  var tot = document.querySelector('.sets-total-' + mid);
  if (tot) tot.textContent = s1 + ':' + s2;
});

// ── Losentscheid Drag-and-Drop ────────────────────────────────────────────────
document.querySelectorAll('.los-sortable').forEach(function(el) {
  new Sortable(el, {
    animation: 150,
    handle: '.bi-grip-vertical',
    draggable: '.los-draggable',
    onMove: function(evt) {
      return evt.related.classList.contains('los-draggable');
    },
    onEnd: function() {
      var rank = 1;
      el.querySelectorAll('li.los-draggable').forEach(function(li) {
        li.querySelector('.los-rank-input').value = rank++;
      });
    }
  });
});
</script>
JS;
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
