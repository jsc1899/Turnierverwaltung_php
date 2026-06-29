<?php
$sport_icons  = ['tischtennis'=>'🏓','tennis'=>'🎾','fussball'=>'⚽','cornhole'=>'🫘'];
$sport_labels = ['tischtennis'=>'Tischtennis','tennis'=>'Tennis','fussball'=>'Fußball','cornhole'=>'Cornhole'];
$locked = (int)($t['is_done'] ?? 0) === 1;
ob_start(); ?>
<div class="d-flex justify-content-end mb-3">
  <a href="<?= url() ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
</div>

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
      <?php if (can_edit() && !$locked): ?>
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
    <?php if (can_edit() && !$locked): ?>
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
          <?php if (can_edit() && !$locked): ?>
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
              KO-Modus
              <?php elseif ($c['mode'] === 'double_ko'): ?>
              Doppel-KO Modus
              <?php elseif ($c['mode'] === 'groups_cross'): ?>
              Gruppenphase à <?= $c['group_size'] ?> &nbsp;·&nbsp; Kreuzspiele
              <?php else: ?>
              Gruppenphase à <?= $c['group_size'] ?>
              &nbsp;·&nbsp;
              <?php if ($c['advance_count'] == 0): ?>nur Gruppen
              <?php elseif ($c['advance_count'] == 1): ?>KO (1 Aufsteiger)
              <?php else: ?>KO (2 Aufsteiger)<?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2 mb-3">
              <?php $phase_labels = ['setup'=>'Einrichtung','group'=>'Gruppenphase','ko'=>'KO-Phase','done'=>'Beendet']; ?>
              <?php $phase_colors = ['setup'=>'bg-secondary','group'=>'bg-warning text-dark','ko'=>'bg-info text-dark','done'=>'bg-success']; ?>
              <span class="badge <?= $phase_colors[$c['phase']] ?? 'bg-secondary' ?>">
                <?= e(phase_label($c['phase'], $c['mode'] ?? null)) ?>
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
              <a href="<?= url('competition/' . $c['id'] . '/aushang') ?>" target="_blank"
                 class="btn btn-outline-secondary btn-sm" title="Aushang (PDF mit QR-Code)">
                <i class="bi bi-printer"></i>
              </a>
              <?php if (can_edit() && !$locked): ?>
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
          <style>
            #newCompetitionModal .row > .col-12:has(> .opt-head) { margin-top:1.25rem; }
            #newCompetitionModal .row > .col-12:has(> .opt-head):first-child { margin-top:0; }
            #newCompetitionModal .opt-head {
              font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em;
              color:#6c757d; border-bottom:1px solid var(--bs-border-color); padding-bottom:.25rem; margin-bottom:.1rem;
            }
          </style>
          <div class="row g-3">

            <!-- ── Allgemein ── -->
            <div class="col-12"><div class="opt-head"><i class="bi bi-info-circle me-1"></i>Allgemein</div></div>
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
            <div class="col-sm-3">
              <label class="form-label">Max. Teilnehmer <span class="text-muted small">(0 = ∞)</span></label>
              <input type="number" name="max_players" class="form-control" value="0" min="0">
            </div>
            <div class="col-sm-3 d-flex align-items-end pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="registrations_open" id="new_regs_open" checked>
                <label class="form-check-label" for="new_regs_open">Nennung offen</label>
              </div>
            </div>

            <!-- ── Spielmodus & Format ── -->
            <div class="col-12"><div class="opt-head"><i class="bi bi-diagram-3 me-1"></i>Spielmodus &amp; Format</div></div>
            <div class="col-sm-6">
              <label class="form-label">Spielmodus</label>
              <select name="mode" class="form-select" id="comp-mode-select" onchange="toggleGroupSettings()">
                <option value="groups_ko" selected>Gruppenphase</option>
                <option value="ko_only">KO-Modus</option>
                <option value="double_ko">Doppel-KO Modus</option>
              </select>
            </div>
            <div class="col-sm-3" id="new-group-size-wrap">
              <label class="form-label">Gruppengröße</label>
              <select name="group_size" class="form-select" onchange="newToggleCross()">
                <?php foreach (range(3, 24) as $s): ?>
                <option value="<?= $s ?>"<?= $s === 4 ? ' selected' : '' ?>><?= $s ?> Teilnehmer</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-3" id="new-finalrunde-wrap">
              <label class="form-label">Finalrunde</label>
              <select name="finalrunde" id="new-finalrunde" class="form-select"
                      onchange="newToggleCross(); newToggleThirdPlace();">
                <option value="none">nur Gruppenphase</option>
                <option value="ko" selected>KO-Runde</option>
                <option value="cross">Kreuzspiele</option>
              </select>
            </div>
            <div class="col-sm-3" id="new-advance-wrap">
              <label class="form-label">Aufsteiger</label>
              <select name="advance_count" id="new-advance-count" class="form-select">
                <option value="1" selected>1 (Gruppenerste)</option>
                <option value="2">2 (Erste &amp; Zweite)</option>
              </select>
            </div>
            <div class="col-sm-3" id="new-round-limit-wrap" style="display:none">
              <label class="form-label">Rundenanzahl <span class="text-muted small">(0 = alle)</span></label>
              <input type="number" name="round_limit" class="form-control" min="0" max="50" placeholder="alle"
                     title="Spielplan nur für so viele Runden erstellen (leer/0 = vollständiger Spielplan).">
            </div>
            <div class="col-sm-6 d-flex align-items-end pb-1" id="new-third-place-wrap">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="third_place" id="new_third_place">
                <label class="form-check-label" for="new_third_place">Platz-3-Spiel</label>
              </div>
            </div>
            <div class="col-sm-3 d-flex align-items-end pb-1" id="new-show-seeding-wrap" style="display:none !important">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="show_seeding" id="new_show_seeding" checked>
                <label class="form-check-label" for="new_show_seeding">Setzungen anzeigen (KO)</label>
              </div>
            </div>
            <div class="col-12" id="new-cross-wrap" style="display:none">
              <label class="form-label">Kreuzspiele – Paarungen je Rang</label>
              <div class="d-flex flex-wrap gap-3">
                <?php for ($t = 1; $t <= 5; $t++): ?>
                <div class="new-cross-tier" data-tier="<?= $t ?>">
                  <div class="small text-muted mb-1">Rang <?= 2*$t-1 ?>+<?= 2*$t ?></div>
                  <select name="cross_config[]" class="form-select form-select-sm" style="width:auto">
                    <option value="x">über Kreuz</option>
                    <option value="s">getrennt</option>
                  </select>
                </div>
                <?php endfor; ?>
              </div>
            </div>

            <!-- ── Mannschaft (Teambewerb) ── -->
            <div class="col-12" id="new-sec-team" style="display:none"><div class="opt-head"><i class="bi bi-people me-1"></i>Mannschaft</div></div>
            <div class="col-sm-6" id="new-team-size-wrap" style="display:none">
              <label class="form-label">Spiele pro Team</label>
              <input type="number" name="team_size" class="form-control" value="0" min="0" max="20">
            </div>
            <div class="col-sm-6" id="new-team-result-wrap" style="display:none">
              <label class="form-label">Begegnungsergebnis</label>
              <select name="team_result_mode" class="form-select">
                <option value="wins" selected>Je Einzelsieg 1 Punkt</option>
                <option value="sum">Einzelergebnisse aufsummieren</option>
                <option value="total">Nur Gesamtergebnis eingeben</option>
              </select>
            </div>
            <div class="col-sm-6" id="new-matchcard-wrap" style="display:none">
              <label class="form-label">Match-Cards</label>
              <select name="match_card_mode" class="form-select">
                <option value="fields" selected>mit Spielerfelder</option>
                <option value="compact">ohne Spielerfelder</option>
              </select>
            </div>
            <div class="col-sm-3 d-flex align-items-end pb-1" id="new-kickoff-wrap" style="display:none">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="kickoff_enabled" id="new_kickoff_enabled">
                <label class="form-check-label" for="new_kickoff_enabled">Anwurf auslosen</label>
              </div>
            </div>

            <!-- ── Wertung & Setzung ── -->
            <div class="col-12"><div class="opt-head"><i class="bi bi-trophy me-1"></i>Wertung &amp; Setzung</div></div>
            <div class="col-sm-6" id="new-scoremode-wrap">
              <label class="form-label">Ergebniserfassung</label>
              <select name="score_mode" class="form-select">
                <option value="match" selected>Spielergebnis</option>
                <option value="sets">Satzergebnisse</option>
                <option value="sets_grp">Gruppe Sätze, KO Spielergebnis</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Setzungsreihenfolge</label>
              <select name="seeding_order" class="form-select">
                <option value="desc" selected>Höhere Spielstärke = stärker</option>
                <option value="asc">Niedrigere Spielstärke = stärker</option>
                <option value="random">Zufällig (keine Setzung)</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Tabellenreihung</label>
              <select name="standings_order" class="form-select">
                <option value="h2h" selected>Punkte – Direktes Duell – Differenz</option>
                <option value="diff">Punkte – Differenz – Direktes Duell</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Punktevergabe</label>
              <select name="points_mode" class="form-select">
                <option value="2-1-0" selected>Sieg 2 – Unentsch. 1 – Niederl. 0</option>
                <option value="3-1-0">Sieg 3 – Unentsch. 1 – Niederl. 0</option>
                <option value="3-2-1">Sieg 3 – Unentsch. 2 – Niederl. 1</option>
              </select>
            </div>

            <!-- ── Spielplan & Zeit ── -->
            <div class="col-12"><div class="opt-head"><i class="bi bi-calendar3 me-1"></i>Spielplan &amp; Zeit</div></div>
            <div class="col-sm-3 d-flex align-items-end pb-1" id="new-byes-wrap">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="show_byes" id="new_show_byes">
                <label class="form-check-label" for="new_show_byes">Spielrunden anzeigen</label>
              </div>
            </div>
            <div class="col-sm-3 d-flex align-items-end pb-1" id="new-forcebyes-wrap">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="force_byes" id="new_force_byes">
                <label class="form-check-label" for="new_force_byes">Spielfreie Runde garantieren</label>
              </div>
            </div>
            <div class="col-sm-6" id="new-schedule-mode-wrap">
              <label class="form-label">Spielplan-Erstellung</label>
              <select name="schedule_mode" class="form-select">
                <option value="random" selected>Zufällig</option>
                <option value="position">Nach Position</option>
              </select>
            </div>
            <div class="col-sm-3">
              <label class="form-label"><?= e(court_label($t['sport'] ?? '', true)) ?> <span class="text-muted small">(0 = aus)</span></label>
              <input type="number" name="num_courts" class="form-control" value="0" min="0" max="20">
            </div>
            <div class="col-sm-3">
              <label class="form-label">ab Nr. <span class="text-muted small">(Start)</span></label>
              <input type="number" name="court_start" class="form-control" value="1" min="1" max="200"
                     title="Ab welcher Platznummer gezählt wird (Standard 1).">
            </div>
            <div class="col-sm-3 d-flex align-items-end pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="schedule_enabled" id="new_schedule_enabled" onchange="newToggleSchedule()">
                <label class="form-check-label" for="new_schedule_enabled"
                       title="Nur wirksam mit Spielplätzen (&gt;0) und „Spielrunden anzeigen".">Zeitplan</label>
              </div>
            </div>
            <div class="col-12" id="new-schedule-fields" style="display:none">
              <div class="row g-2 align-items-end">
                <div class="col-auto">
                  <label class="form-label">Spieldauer/Runde <span class="text-muted small">(Min.)</span></label>
                  <input type="number" name="schedule_duration" class="form-control" style="max-width:150px" min="1" max="600" placeholder="z.B. 15">
                </div>
                <div class="col-auto">
                  <label class="form-label">Startzeit</label>
                  <input type="time" name="schedule_start" class="form-control" style="max-width:150px">
                </div>
                <div class="col-12">
                  <small class="text-muted">Wirksam nur mit aktivierten Spielplätzen (&gt;0) und „Spielrunden anzeigen".</small>
                </div>
              </div>
            </div>

            <!-- ── Anzeige & Druck ── -->
            <div class="col-12"><div class="opt-head"><i class="bi bi-printer me-1"></i>Anzeige &amp; Druck</div></div>
            <div class="col-sm-4 d-flex align-items-end pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="show_skill" id="new_show_skill">
                <label class="form-check-label" for="new_show_skill">Spielstärke anzeigen (Gruppe)</label>
              </div>
            </div>
            <div class="col-sm-4 d-flex align-items-end pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="mc_separate_page" id="new_mc_separate_page">
                <label class="form-check-label" for="new_mc_separate_page"
                       title="Match-Cards, Teampläne und Bahnpläne: jede Karte bzw. Übersicht auf einer eigenen Seite.">separate Seite für Match-Cards</label>
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
  var show = (sel && sel.value === 'team');
  // Team-spezifische Felder nur im Teambewerb
  ['new-sec-team', 'new-team-size-wrap', 'new-team-result-wrap', 'new-matchcard-wrap', 'new-kickoff-wrap'].forEach(function(id) {
    var wrap = document.getElementById(id);
    if (wrap) wrap.style.display = show ? '' : 'none';
  });
  // Ergebniserfassung (Sätze) nur im Einzel-/Doppelbewerb
  var sm = document.getElementById('new-scoremode-wrap');
  if (sm) sm.style.display = show ? 'none' : '';
}
function newToggleSchedule() {
  var cb   = document.getElementById('new_schedule_enabled');
  var wrap = document.getElementById('new-schedule-fields');
  if (wrap) wrap.style.display = (cb && cb.checked) ? '' : 'none';
}
// Ausblenden mit !important, da manche Wrapper die Klasse d-flex (display:flex !important) tragen.
function _setVis(el, cond) {
  if (!el) return;
  if (cond) el.style.removeProperty('display');
  else el.style.setProperty('display', 'none', 'important');
}
function toggleGroupSettings() {
  var sel  = document.getElementById('comp-mode-select');
  var mode = sel ? sel.value : 'groups_ko';
  var isGroups = (mode === 'groups_ko');
  // Gruppen-bezogene Felder (Größe, Spielrunden, Finalrunde) nur im Gruppenphase-Modus
  ['new-group-size-wrap', 'new-byes-wrap', 'new-forcebyes-wrap', 'new-finalrunde-wrap', 'new-schedule-mode-wrap'].forEach(function(id) {
    _setVis(document.getElementById(id), isGroups);
  });
  // Setzungen anzeigen (KO) nur im KO-/Doppel-KO-Modus
  _setVis(document.getElementById('new-show-seeding-wrap'), mode === 'ko_only' || mode === 'double_ko');
  newToggleCross();
  newToggleThirdPlace();
}
function newToggleCross() {
  var sel = document.getElementById('comp-mode-select');
  var fr  = document.getElementById('new-finalrunde');
  var isGroups = sel && sel.value === 'groups_ko';
  var frVal = fr ? fr.value : 'ko';
  // Aufsteiger-Feld nur bei Finalrunde = KO-Runde
  _setVis(document.getElementById('new-advance-wrap'), isGroups && frVal === 'ko');
  // Rundenanzahl-Feld nur bei reiner Gruppenphase (Finalrunde = nur Gruppenphase)
  _setVis(document.getElementById('new-round-limit-wrap'), isGroups && frVal === 'none');
  // Kreuz-Konfig nur bei Finalrunde = Kreuzspiele
  var wrap = document.getElementById('new-cross-wrap');
  if (wrap) {
    _setVis(wrap, isGroups && frVal === 'cross');
    var gsSel = document.querySelector('#new-group-size-wrap select');
    var gs = gsSel ? parseInt(gsSel.value, 10) : 4;
    var ntiers = Math.ceil(gs / 2);
    document.querySelectorAll('.new-cross-tier').forEach(function(el) {
      _setVis(el, parseInt(el.dataset.tier, 10) <= ntiers);
    });
  }
}
function newToggleThirdPlace() {
  var sel  = document.getElementById('comp-mode-select');
  var fr   = document.getElementById('new-finalrunde');
  var wrap = document.getElementById('new-third-place-wrap');
  if (!wrap) return;
  var mode  = sel ? sel.value : 'groups_ko';
  var frVal = fr  ? fr.value  : 'ko';
  _setVis(wrap, (mode === 'ko_only' || mode === 'double_ko') || (mode === 'groups_ko' && frVal === 'ko'));
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
