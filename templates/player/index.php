<?php
$sport_icons = ['tischtennis'=>'🏓','tennis'=>'🎾','fussball'=>'⚽','cornhole'=>null];
// Fußball und Cornhole werden im Register nicht verwaltet — nur TT und Tennis anzeigen
$registry_sports_list = array_values(array_filter($sports_list, fn($s) => !in_array($s[0], ['fussball', 'cornhole'])));
ob_start(); ?>
<div class="d-flex align-items-center mb-1 gap-2">
  <h2 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Spielerregister</h2>
</div>
<p class="text-muted small mb-3">Stammdaten und Spielstärken aller registrierten Spieler.</p>

<!-- Tabs -->
<ul class="nav nav-tabs mb-0" id="players-tabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-players-btn"
            data-bs-toggle="tab" data-bs-target="#tab-spieler" type="button" role="tab">
      <i class="bi bi-people me-1"></i>Spieler (<?= count(array_filter($players, fn($p) => $p['is_active'])) ?>)
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-doppel-btn"
            data-bs-toggle="tab" data-bs-target="#tab-doppel" type="button" role="tab">
      <i class="bi bi-people-fill me-1"></i>Doppel (<?= count(array_filter($all_doubles, fn($d) => $d['is_active'])) ?>)
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-teams-btn"
            data-bs-toggle="tab" data-bs-target="#tab-teams" type="button" role="tab">
      <i class="bi bi-shield-fill me-1"></i>Teams (<?= count(array_filter($all_teams, fn($t) => $t['is_active'])) ?>)
    </button>
  </li>
</ul>
<div class="tab-content border border-top-0 rounded-bottom mb-4">

  <!-- ── Tab: Spieler ── -->
  <div class="tab-pane fade show active p-3" id="tab-spieler" role="tabpanel">
    <?php if (can_edit()): ?>
    <div class="mb-2 d-flex gap-2">
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newPlayerModal"
              id="btn-new-player">
        <i class="bi bi-person-plus me-1"></i>Neuer Spieler
      </button>
      <a href="<?= url('players/import') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-file-earmark-arrow-up me-1"></i>Importieren
      </a>
    </div>
    <?php endif; ?>
    <?php if ($players): ?>
    <div class="d-flex align-items-center mb-2 gap-2">
      <span class="text-muted small"><?= count(array_filter($players, fn($p) => $p['is_active'])) ?> Einträge</span>
      <input type="search" class="form-control form-control-sm table-filter" style="max-width:220px"
             placeholder="Filtern…" data-target="tbl-spieler" aria-label="Spieler filtern">
      <?php if (can_edit()): ?>
      <button class="btn btn-outline-primary btn-sm ms-auto" id="btn-sync-all-tt"
              title="Tischtennis-Spielstärke aller Spieler mit RatingsCentral-ID aktualisieren">
        <i class="bi bi-arrow-clockwise me-1"></i>TT RC Abgleich
      </button>
      <span id="sync-all-status" class="small" style="display:none"></span>
      <?php else: ?>
      <span class="ms-auto"></span>
      <?php endif; ?>
      <div class="form-check form-check-inline mb-0">
        <input class="form-check-input show-inactive-cb" type="checkbox"
               id="show-inactive-spieler" data-table="tbl-spieler">
        <label class="form-check-label small text-muted" for="show-inactive-spieler">Inaktive anzeigen</label>
      </div>
      <div class="btn-group btn-group-sm ms-3">
        <a href="<?= url('players/pdf') ?>" class="btn btn-outline-danger" target="_blank">
          <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
        <a href="<?= url('players/csv') ?>" class="btn btn-outline-success">
          <i class="bi bi-filetype-csv me-1"></i>CSV
        </a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle" data-sortable id="tbl-spieler">
        <thead class="table-light">
          <tr>
            <th>Nachname</th><th>Vorname</th><th class="text-center">G</th>
            <th>Verein</th><th>Pass-Nr.</th><th>E-Mail</th>
            <?php foreach ($registry_sports_list as [$sk, $sl, $se]): ?>
            <th class="text-center" title="<?= e($sl) ?>">
              <?php if ($se): echo $se; else: ?>
              <img src="<?= url('static/cornhole_icon.svg') ?>" height="14" alt="Cornhole">
              <?php endif; ?>
            </th>
            <?php endforeach; ?>
            <th class="no-sort"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($players as $p): $ps = $player_skills[$p['id']] ?? []; ?>
          <tr data-active="<?= $p['is_active'] ?>"<?= $p['is_active'] ? '' : ' class="opacity-50"' ?>>
            <td class="fw-semibold">
              <button class="btn btn-link btn-sm p-0 text-start text-decoration-none text-dark fw-semibold"
                      data-bs-toggle="modal" data-bs-target="#playerProfileModal"
                      data-player-id="<?= $p['id'] ?>"
                      title="Profil öffnen"><?= e($p['name']) ?></button>
            </td>
            <td><?= e($p['firstname'] ?? '') ?></td>
            <td class="text-center">
              <?php if ($p['gender']): ?>
              <span class="badge <?= $p['gender'] === 'm' ? 'bg-primary' : 'bg-danger' ?>"><?= e($p['gender']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e($p['club'] ?? '') ?></td>
            <td><span class="text-muted"><?= e($p['pass_nr'] ?? '') ?></span></td>
            <td><span class="text-muted small"><?= e($p['email'] ?? '') ?></span></td>
            <?php foreach ($registry_sports_list as [$sk, $sl, $se]):
              $sv = isset($ps[$sk]) ? ($sk === 'tennis' ? number_format((float)$ps[$sk], 1) : (int)$ps[$sk]) : null; ?>
            <td class="text-center" data-sort="<?= $sv ?? 0 ?>">
              <?php if ($sv !== null): ?>
              <span class="badge bg-secondary"
                    <?= ($sk === 'tischtennis') ? 'id="tt-skill-'.$p['id'].'"' : '' ?>>
                <?= $sv ?>
              </span>
              <?php endif; ?>
              <?php if ($sk === 'tischtennis' && !empty($p['ratingscentral_id'])): ?>
              <a href="https://www.ratingscentral.com/Player.php?PlayerID=<?= e($p['ratingscentral_id']) ?>" target="_blank"
                 rel="noopener" class="btn btn-link btn-sm p-0 ms-1 text-secondary"
                 title="RatingsCentral-Profil öffnen" style="font-size:.75rem;vertical-align:middle">
                <i class="bi bi-box-arrow-up-right"></i>
              </a>
              <?php endif; ?>
              <?php if ($sk === 'tischtennis' && !empty($p['ratingscentral_id']) && can_edit()): ?>
              <button class="btn btn-link btn-sm p-0 ms-1 rc-sync-btn text-secondary"
                      data-pid="<?= $p['id'] ?>" title="TT-Spielstärke von RatingsCentral abrufen"
                      style="font-size:.75rem;vertical-align:middle">
                <i class="bi bi-arrow-clockwise"></i>
              </button>
              <?php endif; ?>
              <?php if ($sk === 'tennis' && !empty($p['oetv_nr'])): ?>
              <a href="https://www.oetv.at/spieler/<?= e($p['oetv_nr']) ?>" target="_blank"
                 rel="noopener" class="btn btn-link btn-sm p-0 ms-1 text-secondary"
                 title="ÖTV-Profil öffnen" style="font-size:.75rem;vertical-align:middle">
                <i class="bi bi-box-arrow-up-right"></i>
              </a>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td class="text-end text-nowrap">
              <button class="btn btn-outline-secondary btn-sm"
                      data-bs-toggle="modal" data-bs-target="#playerProfileModal"
                      data-player-id="<?= $p['id'] ?>" data-open-tab="edit"
                      title="Bearbeiten">
                <i class="bi bi-pencil"></i>
              </button>
              <?php if (can_edit()): ?>
              <form method="post" action="<?= url('player/'.$p['id'].'/toggle-active') ?>"
                    class="d-inline ms-1 js-ajax" data-refresh="#tab-spieler"
                    data-confirm="<?= $p['is_active'] ? 'Spieler '.e($p['firstname'].' '.$p['name']).' inaktiv setzen?' : 'Spieler '.e($p['firstname'].' '.$p['name']).' wieder aktivieren?' ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-<?= $p['is_active'] ? 'warning' : 'success' ?> btn-sm"
                        title="<?= $p['is_active'] ? 'Inaktiv setzen' : 'Aktivieren' ?>">
                  <i class="bi bi-<?= $p['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                </button>
              </form>
              <?php endif; ?>
              <?php if (is_admin()): ?>
              <form method="post" action="<?= url('player/' . $p['id'] . '/delete') ?>"
                    class="d-inline ms-1 js-ajax" data-refresh="#tab-spieler"
                    data-confirm="Spieler <?= e($p['firstname'].' '.$p['name']) ?> wirklich löschen?">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted">Noch keine Spieler im Register.</p>
    <?php endif; ?>
  </div>

  <!-- ── Tab: Doppel ── -->
  <div class="tab-pane fade p-3" id="tab-doppel" role="tabpanel">
    <?php $sport_labels = ['tischtennis'=>'Tischtennis','tennis'=>'Tennis','fussball'=>'Fußball','cornhole'=>'Cornhole']; ?>
    <?php if (can_edit()): ?>
    <div class="mb-2">
      <a href="<?= url('players/doubles/import') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-file-earmark-arrow-up me-1"></i>Doppel importieren
      </a>
    </div>
    <?php endif; ?>
    <?php if ($all_doubles): ?>
    <div class="d-flex align-items-center mb-2 gap-2">
      <span class="text-muted small"><?= count(array_filter($all_doubles, fn($d) => $d['is_active'])) ?> Einträge</span>
      <input type="search" class="form-control form-control-sm table-filter" style="max-width:220px"
             placeholder="Filtern…" data-target="tbl-doppel" aria-label="Doppel filtern">
      <div class="form-check form-check-inline mb-0">
        <input class="form-check-input show-inactive-cb" type="checkbox"
               id="show-inactive-doppel" data-table="tbl-doppel">
        <label class="form-check-label small text-muted" for="show-inactive-doppel">Inaktive anzeigen</label>
      </div>
      <?php if (can_edit()): ?>
      <div class="btn-group btn-group-sm ms-auto">
        <a href="<?= url('players/doubles/pdf') ?>" class="btn btn-outline-danger" target="_blank">
          <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
        <a href="<?= url('players/doubles/csv') ?>" class="btn btn-outline-success">
          <i class="bi bi-filetype-csv me-1"></i>CSV
        </a>
      </div>
      <?php endif; ?>
    </div>
    <div class="table-responsive mb-3">
      <table class="table table-sm table-hover align-middle mb-0" data-sortable id="tbl-doppel">
        <thead class="table-light">
          <tr>
            <th>Name</th><th>Spieler 1</th><th>Spieler 2</th><th>Spielstärke</th>
            <?php if (can_edit()): ?><th class="no-sort"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($all_doubles as $d): ?>
        <?php $dsums = $double_sport_skills[$d['id']] ?? []; ?>
        <tr data-active="<?= $d['is_active'] ?>"<?= $d['is_active'] ? '' : ' class="opacity-50"' ?>>
          <td class="fw-semibold small"><?= e($d['name']) ?></td>
          <td class="small text-muted">
            <?= e($d['p1name']) ?>
            <?php if ($d['p1club']): ?><span class="text-muted"> (<?= e($d['p1club']) ?>)</span><?php endif; ?>
          </td>
          <td class="small text-muted">
            <?= e($d['p2name']) ?>
            <?php if ($d['p2club']): ?><span class="text-muted"> (<?= e($d['p2club']) ?>)</span><?php endif; ?>
          </td>
          <td class="small" data-sort="<?= array_sum($dsums) ?>">
            <?php foreach ($dsums as $sport => $sum): if ($sum <= 0) continue; ?>
            <span class="badge bg-secondary me-1" title="<?= e($sport_labels[$sport] ?? $sport) ?>">
              <?= e($sport_icons[$sport] ?? $sport) ?> <?= $sum == (int)$sum ? (int)$sum : $sum ?>
            </span>
            <?php endforeach; ?>
            <?php if (!array_filter($dsums)): ?>—<?php endif; ?>
          </td>
          <?php if (can_edit()): ?>
          <td class="text-end text-nowrap">
            <button class="btn btn-outline-secondary btn-sm py-0 px-1 me-1"
                    data-bs-toggle="modal" data-bs-target="#editDoubleModal<?= $d['id'] ?>" title="Bearbeiten">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="post" action="<?= url('players/double/'.$d['id'].'/toggle-active') ?>"
                  class="d-inline me-1 js-ajax" data-refresh="#tab-doppel"
                  data-confirm="<?= $d['is_active'] ? 'Doppel &bdquo;'.e($d['name']).'&ldquo; inaktiv setzen?' : 'Doppel &bdquo;'.e($d['name']).'&ldquo; wieder aktivieren?' ?>">
              <?= csrf_field() ?>
              <button class="btn btn-outline-<?= $d['is_active'] ? 'warning' : 'success' ?> btn-sm py-0 px-1"
                      title="<?= $d['is_active'] ? 'Inaktiv setzen' : 'Aktivieren' ?>">
                <i class="bi bi-<?= $d['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
              </button>
            </form>
            <?php if (is_admin()): ?>
            <form method="post" action="<?= url('players/double/'.$d['id'].'/delete') ?>"
                  class="d-inline js-ajax" data-refresh="#tab-doppel"
                  data-confirm="Doppel &bdquo;<?= e($d['name']) ?>&ldquo; wirklich löschen?">
              <?= csrf_field() ?>
              <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Löschen">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted small">Noch keine Doppel gebildet.</p>
    <?php endif; ?>

    <?php if (can_edit() && count($players) >= 2): ?>
    <div class="border-top pt-3">
      <h6 class="mb-3"><i class="bi bi-plus-circle me-1"></i>Neues Doppel bilden</h6>
      <form method="post" action="<?= url('players/double/new') ?>" class="row g-2 align-items-end js-ajax" data-refresh="#tab-doppel">
        <?= csrf_field() ?>
        <div class="col-sm-5">
          <label class="form-label small">Spieler 1</label>
          <select name="player1_id" id="new_p1" class="form-select form-select-sm" required onchange="calcDoubleSkill()">
            <option value="">— auswählen —</option>
            <?php foreach ($players as $pl): ?>
            <option value="<?= $pl['id'] ?>">
              <?= e(trim($pl['name'].($pl['firstname'] ? ' '.$pl['firstname'] : ''))) ?>
              <?php if ($pl['club']): ?>(<?= e($pl['club']) ?>)<?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-5">
          <label class="form-label small">Spieler 2</label>
          <select name="player2_id" id="new_p2" class="form-select form-select-sm" required onchange="calcDoubleSkill()">
            <option value="">— auswählen —</option>
            <?php foreach ($players as $pl): ?>
            <option value="<?= $pl['id'] ?>">
              <?= e(trim($pl['name'].($pl['firstname'] ? ' '.$pl['firstname'] : ''))) ?>
              <?php if ($pl['club']): ?>(<?= e($pl['club']) ?>)<?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-2">
          <button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus me-1"></i>Erstellen</button>
        </div>
        <div class="col-12" id="new_double_skills" style="display:none">
          <small class="text-muted">Berechnete Stärke: <span id="new_double_skills_text"></span></small>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>


  <!-- ── Tab: Teams ── -->
  <div class="tab-pane fade p-3" id="tab-teams" role="tabpanel">
    <?php if (can_edit()): ?>
    <div class="mb-2">
      <a href="<?= url('players/teams/import') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-file-earmark-arrow-up me-1"></i>Teams importieren
      </a>
    </div>
    <?php endif; ?>
    <?php if ($all_teams): ?>
    <div class="d-flex align-items-center mb-2 gap-2">
      <span class="text-muted small"><?= count(array_filter($all_teams, fn($t) => $t['is_active'])) ?> Einträge</span>
      <input type="search" class="form-control form-control-sm table-filter" style="max-width:220px"
             placeholder="Filtern…" data-target="tbl-teams" aria-label="Teams filtern">
      <div class="form-check form-check-inline mb-0">
        <input class="form-check-input show-inactive-cb" type="checkbox"
               id="show-inactive-teams" data-table="tbl-teams">
        <label class="form-check-label small text-muted" for="show-inactive-teams">Inaktive anzeigen</label>
      </div>
      <?php if (can_edit()): ?>
      <div class="btn-group btn-group-sm ms-auto">
        <a href="<?= url('players/teams/pdf') ?>" class="btn btn-outline-danger" target="_blank">
          <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
        <a href="<?= url('players/teams/csv') ?>" class="btn btn-outline-success">
          <i class="bi bi-filetype-csv me-1"></i>CSV
        </a>
      </div>
      <?php endif; ?>
    </div>
    <div class="table-responsive mb-3">
      <table class="table table-sm table-hover align-middle mb-0" data-sortable id="tbl-teams">
        <thead class="table-light">
          <tr>
            <th>Teamname</th><th>Kapitän</th><th>Mitglieder</th>
            <?php if (can_edit()): ?><th class="no-sort"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($all_teams as $team): ?>
        <tr data-active="<?= $team['is_active'] ?>"<?= $team['is_active'] ? '' : ' class="opacity-50"' ?>>
          <td class="fw-semibold"><?= e($team['name']) ?></td>
          <td class="small">
            <?php if (!empty($team['captain'])): ?>
              <i class="bi bi-person-badge me-1 text-muted"></i><?= e($team['captain']) ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="small">
            <?php if ($team['members']): ?>
              <?= e(implode(', ', array_column($team['members'], 'fullname'))) ?>
              <span class="text-muted">(<?= count($team['members']) ?>)</span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <?php if (can_edit()): ?>
          <td class="text-end text-nowrap">
            <button class="btn btn-outline-secondary btn-sm py-0 px-1 me-1"
                    data-bs-toggle="modal" data-bs-target="#editTeamModal<?= $team['id'] ?>" title="Bearbeiten">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="post" action="<?= url('players/team/'.$team['id'].'/toggle-active') ?>"
                  class="d-inline me-1 js-ajax" data-refresh="#tab-teams"
                  data-confirm="<?= $team['is_active'] ? 'Team &bdquo;'.e($team['name']).'&ldquo; inaktiv setzen?' : 'Team &bdquo;'.e($team['name']).'&ldquo; wieder aktivieren?' ?>">
              <?= csrf_field() ?>
              <button class="btn btn-outline-<?= $team['is_active'] ? 'warning' : 'success' ?> btn-sm py-0 px-1"
                      title="<?= $team['is_active'] ? 'Inaktiv setzen' : 'Aktivieren' ?>">
                <i class="bi bi-<?= $team['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
              </button>
            </form>
            <?php if (is_admin()): ?>
            <form method="post" action="<?= url('players/team/'.$team['id'].'/delete') ?>"
                  class="d-inline js-ajax" data-refresh="#tab-teams"
                  data-confirm="Team &bdquo;<?= e($team['name']) ?>&ldquo; wirklich löschen?">
              <?= csrf_field() ?>
              <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Löschen">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted small">Noch keine Teams erstellt.</p>
    <?php endif; ?>

    <?php if (can_edit()): ?>
    <div class="border-top pt-3">
      <h6 class="mb-3"><i class="bi bi-plus-circle me-1"></i>Neues Team erstellen</h6>
      <form method="post" action="<?= url('players/team/new') ?>" class="row g-2 align-items-end js-ajax" data-refresh="#tab-teams">
        <?= csrf_field() ?>
        <div class="col-sm-5">
          <label class="form-label small">Teamname <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control form-control-sm" required placeholder="z.B. Team Alpha">
        </div>
        <div class="col-sm-4">
          <label class="form-label small">Kapitän <span class="text-muted">(optional)</span></label>
          <input type="text" name="captain" class="form-control form-control-sm" placeholder="z.B. Max Mustermann">
        </div>
        <div class="col-sm-3">
          <button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus me-1"></i>Erstellen</button>
        </div>
        <?php if ($players): ?>
        <div class="col-12">
          <label class="form-label small">Spieler hinzufügen <span class="text-muted">(optional)</span></label>
          <div class="d-flex gap-2 mb-1">
            <a href="#" class="small text-muted add-select-all">Alle</a>
            <a href="#" class="small text-muted add-select-none">Keine</a>
          </div>
          <div class="border rounded add-entry-list mb-2" style="max-height:220px;overflow-y:auto">
            <?php foreach ($players as $pl): ?>
            <label class="add-entry-item d-flex align-items-center gap-2 px-2 py-1 border-bottom mb-0 user-select-none" style="cursor:pointer">
              <input type="checkbox" name="player_ids[]" value="<?= $pl['id'] ?>" class="form-check-input mt-0 flex-shrink-0">
              <span class="small">
                <?= e(trim($pl['name'].($pl['firstname'] ? ' '.$pl['firstname'] : ''))) ?>
                <?php if ($pl['club']): ?><span class="text-muted ms-1">(<?= e($pl['club']) ?>)</span><?php endif; ?>
              </span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </form>
    </div>
    <?php endif; ?>
  </div>

</div><!-- tab-content -->

<?php if (can_edit()): ?>
<!-- Edit-Modals für jedes Team -->
<?php foreach ($all_teams as $team): ?>
<div class="modal fade" id="editTeamModal<?= $team['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-shield me-1"></i>Team bearbeiten: <?= e($team['name']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?= url('players/team/'.$team['id'].'/edit') ?>" class="mb-3 js-ajax" data-refresh="#tab-teams">
          <?= csrf_field() ?>
          <div class="row g-2 align-items-end">
            <div class="col-sm-5">
              <label class="form-label small">Teamname <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control form-control-sm" value="<?= e($team['name']) ?>" required>
            </div>
            <div class="col-sm-4">
              <label class="form-label small">Kapitän <span class="text-muted">(optional)</span></label>
              <input type="text" name="captain" class="form-control form-control-sm" value="<?= e($team['captain'] ?? '') ?>" placeholder="z.B. Max Mustermann">
            </div>
            <div class="col-sm-3">
              <button class="btn btn-primary btn-sm w-100"><i class="bi bi-check me-1"></i>Speichern</button>
            </div>
          </div>
        </form>
        <ul class="nav nav-tabs mb-3" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab"
                    data-bs-target="#teamTabMember<?= $team['id'] ?>" type="button" role="tab">
              <i class="bi bi-people me-1"></i>Mitglieder
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab"
                    data-bs-target="#teamTabComp<?= $team['id'] ?>" type="button" role="tab">
              <i class="bi bi-trophy me-1"></i>Bewerbe
            </button>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="teamTabMember<?= $team['id'] ?>" role="tabpanel">
            <?php if ($team['members']): ?>
            <ul class="list-group list-group-flush mb-3">
              <?php foreach ($team['members'] as $member): ?>
              <li class="list-group-item d-flex align-items-center justify-content-between py-1 px-0">
                <span class="small">
                  <?= e($member['fullname']) ?>
                  <?php if ($member['club']): ?><span class="text-muted">(<?= e($member['club']) ?>)</span><?php endif; ?>
                </span>
                <form method="post" action="<?= url('players/team/'.$team['id'].'/player/'.$member['id'].'/remove') ?>"
                      class="d-inline js-ajax" data-refresh="#teamTabMember<?= $team['id'] ?>, #tab-teams"
                      data-confirm="Spieler aus dem Team entfernen?">
                  <?= csrf_field() ?>
                  <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Entfernen">
                    <i class="bi bi-x"></i>
                  </button>
                </form>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted small mb-3">Noch keine Mitglieder.</p>
            <?php endif; ?>
            <form method="post" action="<?= url('players/team/'.$team['id'].'/player/add') ?>" class="row g-2 align-items-end js-ajax" data-refresh="#teamTabMember<?= $team['id'] ?>, #tab-teams">
              <?= csrf_field() ?>
              <?php $assigned_ids = array_column($team['members'], 'id'); ?>
              <div class="col-sm-9">
                <label class="form-label small">Spieler hinzufügen</label>
                <select name="player_id" class="form-select form-select-sm" required>
                  <option value="">— auswählen —</option>
                  <?php foreach ($players as $pl): if (in_array($pl['id'], $assigned_ids)) continue; ?>
                  <option value="<?= $pl['id'] ?>">
                    <?= e(trim($pl['name'].($pl['firstname'] ? ' '.$pl['firstname'] : ''))) ?>
                    <?php if ($pl['club']): ?>(<?= e($pl['club']) ?>)<?php endif; ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-3">
                <button class="btn btn-secondary btn-sm w-100"><i class="bi bi-plus me-1"></i>Hinzufügen</button>
              </div>
            </form>
          </div>
          <div class="tab-pane fade" id="teamTabComp<?= $team['id'] ?>" role="tabpanel">
            <?php if ($team['competitions']): ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($team['competitions'] as $comp): ?>
              <li class="list-group-item py-1 px-0 small">
                <i class="bi bi-trophy text-secondary me-2"></i>
                <span class="text-muted"><?= e($comp['tournament_name']) ?> —</span> <?= e($comp['name']) ?>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted small mb-0">Keinem Bewerb zugeteilt.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<!-- Neuer Spieler Modal -->
<div class="modal fade" id="newPlayerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus me-1"></i>Neuer Spieler</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?= url('player/new') ?>" class="js-ajax" data-refresh="#tab-spieler" data-modal-close data-ajax-reset>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Nachname <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Vorname <span class="text-danger">*</span></label>
            <input type="text" name="firstname" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Verein</label>
            <input type="text" name="club" class="form-control">
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Geschlecht</label>
              <select name="gender" class="form-select">
                <option value="">—</option>
                <option value="m">m</option>
                <option value="w">w</option>
              </select>
            </div>
            <div class="col">
              <label class="form-label">Pass-Nr.</label>
              <input type="text" name="pass_nr" class="form-control">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">E-Mail</label>
            <input type="email" name="email" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Spielstärken</label>
            <?php foreach ($registry_sports_list as [$sk, $sl, $se]): ?>
            <div class="input-group input-group-sm mb-1">
              <span class="input-group-text"><?= $se ?: '<img src="'.url('static/cornhole_icon.svg').'" height="12">' ?></span>
              <input type="number" step="<?= $sk === 'tennis' ? '0.1' : '1' ?>" min="0"
                     name="skill_<?= $sk ?>" class="form-control" placeholder="<?= e($sl) ?>">
            </div>
            <?php endforeach; ?>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
            <button type="submit" class="btn btn-primary">Hinzufügen</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit-Modals für jedes Doppel -->
<?php foreach ($all_doubles as $d): ?>
<?php $dsums = $double_sport_skills[$d['id']] ?? []; ?>
<?php $dcomps = $double_competitions[$d['id']] ?? []; ?>
<div class="modal fade" id="editDoubleModal<?= $d['id'] ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-1"></i>Doppel bearbeiten</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?= url('players/double/'.$d['id'].'/edit') ?>" class="mb-3 js-ajax" data-refresh="#tab-doppel" data-modal-close id="doubleEditForm<?= $d['id'] ?>">
          <?= csrf_field() ?>
          <div class="row g-2 align-items-end">
            <div class="col-sm-9">
              <label class="form-label small">Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control form-control-sm" value="<?= e($d['name']) ?>" required>
            </div>
            <div class="col-sm-3">
              <button class="btn btn-primary btn-sm w-100"><i class="bi bi-check me-1"></i>Speichern</button>
            </div>
          </div>
        </form>
        <?php if ($dsums): ?>
        <div class="mb-3">
          <div class="form-text mb-1">Spielstärke (automatisch):</div>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($dsums as $sport => $sum): if ($sum <= 0) continue; ?>
            <span class="badge bg-secondary">
              <?= e($sport_icons[$sport] ?? $sport) ?>
              <?= e($sport_labels[$sport] ?? $sport) ?>:
              <?= $sum == (int)$sum ? (int)$sum : $sum ?>
            </span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <ul class="nav nav-tabs mb-3" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab"
                    data-bs-target="#doubleTabComp<?= $d['id'] ?>" type="button" role="tab">
              <i class="bi bi-trophy me-1"></i>Bewerbe
            </button>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="doubleTabComp<?= $d['id'] ?>" role="tabpanel">
            <?php if ($dcomps): ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($dcomps as $comp): ?>
              <li class="list-group-item py-1 px-0 small">
                <i class="bi bi-trophy text-secondary me-2"></i>
                <span class="text-muted"><?= e($comp['tournament_name']) ?> —</span> <?= e($comp['name']) ?>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted small mb-0">Keinem Bewerb zugeteilt.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- ── Spieler-Profil-Modal (shared, AJAX-geladen) ────────────────────────── -->
<div class="modal fade" id="playerProfileModal" tabindex="-1" aria-labelledby="profileModalTitle">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="profileModalTitle">
          <i class="bi bi-person-circle me-2 text-primary"></i><span id="profileModalName">…</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <!-- Ladezustand -->
        <div id="profileLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Lade…</span>
          </div>
        </div>
        <!-- Inhalt nach AJAX -->
        <div id="profileContent" style="display:none">
          <ul class="nav nav-tabs px-3 pt-2 border-bottom-0" id="profileModalTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="profileTabStammBtn"
                      data-bs-toggle="tab" data-bs-target="#profileTabStamm" type="button">
                <i class="bi bi-person me-1"></i>Stammdaten
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="profileTabCompsBtn"
                      data-bs-toggle="tab" data-bs-target="#profileTabComps" type="button">
                <i class="bi bi-trophy me-1"></i>Bewerbe
              </button>
            </li>
          </ul>
          <div class="tab-content px-3 pt-3 pb-2">
            <!-- Tab: Stammdaten -->
            <div class="tab-pane fade show active" id="profileTabStamm" role="tabpanel">
              <form method="post" id="profileEditForm" action="#" class="js-ajax" data-refresh="#tab-spieler">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="row g-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold">Nachname <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="pf-name" class="form-control"
                           required <?= can_edit() ? '' : 'readonly' ?>>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold">Vorname <span class="text-danger">*</span></label>
                    <input type="text" name="firstname" id="pf-firstname" class="form-control"
                           required <?= can_edit() ? '' : 'readonly' ?>>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold">Verein</label>
                    <input type="text" name="club" id="pf-club" class="form-control"
                           <?= can_edit() ? '' : 'readonly' ?>>
                  </div>
                  <div class="col-sm-3">
                    <label class="form-label fw-semibold">Geschlecht</label>
                    <select name="gender" id="pf-gender" class="form-select" <?= can_edit() ? '' : 'disabled' ?>>
                      <option value="">—</option>
                      <option value="m">m</option>
                      <option value="w">w</option>
                    </select>
                  </div>
                  <div class="col-sm-3">
                    <label class="form-label fw-semibold">Pass-Nr.</label>
                    <input type="text" name="pass_nr" id="pf-pass_nr" class="form-control"
                           <?= can_edit() ? '' : 'readonly' ?>>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-semibold">E-Mail</label>
                    <input type="email" name="email" id="pf-email" class="form-control"
                           <?= can_edit() ? '' : 'readonly' ?>>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Externes Profil</label>
                    <label class="form-label small">🏓 RatingsCentral-ID</label>
                    <div class="input-group input-group-sm">
                      <input type="text" name="ratingscentral_id" id="pf-ratingscentral_id"
                             class="form-control" placeholder="z.B. 123456"
                             <?= can_edit() ? '' : 'readonly' ?>>
                      <?php if (can_edit()): ?>
                      <a id="pf-rc-link" href="#" target="_blank" rel="noopener"
                         class="btn btn-outline-secondary" title="Profil auf RatingsCentral öffnen"
                         style="display:none">
                        <i class="bi bi-box-arrow-up-right"></i>
                      </a>
                      <button type="button" class="btn btn-outline-primary" id="pf-rc-sync"
                              title="Tischtennis-Spielstärke jetzt abrufen">
                        <i class="bi bi-arrow-clockwise"></i>
                      </button>
                      <?php endif; ?>
                    </div>
                    <div id="pf-rc-status" class="form-text"></div>
                    <label class="form-label small mt-2">🎾 ÖTV-ID</label>
                    <div class="input-group input-group-sm">
                      <input type="text" name="oetv_nr" id="pf-oetv_nr"
                             class="form-control" placeholder="z.B. NU74391"
                             <?= can_edit() ? '' : 'readonly' ?>>
                      <a id="pf-oetv-link" href="#" target="_blank" rel="noopener"
                         class="btn btn-outline-secondary" title="ÖTV-Profil öffnen"
                         style="display:none">
                        <i class="bi bi-box-arrow-up-right"></i>
                      </a>
                    </div>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Spielstärken</label>
                    <div class="row g-2">
                      <?php foreach ($registry_sports_list as [$sk, $sl, $se]): ?>
                      <div class="col-6 col-sm-3">
                        <label class="form-label small">
                          <?php if ($se): echo $se; else: ?>
                          <img src="<?= url('static/cornhole_icon.svg') ?>" height="14" alt="">
                          <?php endif; ?>
                          <?= e($sl) ?>
                        </label>
                        <input type="number" step="<?= $sk === 'tennis' ? '0.1' : '1' ?>" min="0"
                               name="skill_<?= $sk ?>" id="pf-skill-<?= $sk ?>"
                               class="form-control form-control-sm" placeholder="0"
                               <?= can_edit() ? '' : 'readonly' ?>>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </form>
            </div>
            <!-- Tab: Bewerbe & Doppel -->
            <div class="tab-pane fade" id="profileTabComps" role="tabpanel">
              <div id="profileCompsContent"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer" id="profileFooter" style="display:none">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
        <?php if (can_edit()): ?>
        <form id="profileToggleForm" method="post" action="" class="me-auto js-ajax" data-refresh="#tab-spieler" data-modal-close>
          <?= csrf_field() ?>
          <button type="submit" id="profileToggleBtn" class="btn btn-outline-warning btn-sm"></button>
        </form>
        <?php if (is_admin()): ?>
        <form id="profileDeleteForm" method="post" action="" class="js-ajax" data-refresh="#tab-spieler" data-modal-close>
          <?= csrf_field() ?>
          <button type="submit" id="profileDeleteBtn" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-trash me-1"></i>Löschen
          </button>
        </form>
        <?php endif; ?>
        <button type="submit" form="profileEditForm" class="btn btn-primary">
          <i class="bi bi-check2 me-1"></i>Speichern
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
.add-entry-item:hover { background: var(--bs-tertiary-bg); }
.add-entry-item:has(input:checked) { background: var(--bs-primary-bg-subtle); }
</style>
<script>
var playerSkillsData = <?= json_encode($player_skills) ?>;
var sportLabels = {'tischtennis':'Tischtennis','tennis':'Tennis','fussball':'Fußball','cornhole':'Cornhole'};
var sportDefaults = {'tennis': 10.0};
var profileBaseUrl = <?= json_encode(url('player')) ?>;

function calcDoubleSkill() {
  var p1 = document.getElementById('new_p1')?.value;
  var p2 = document.getElementById('new_p2')?.value;
  var box = document.getElementById('new_double_skills');
  var txt = document.getElementById('new_double_skills_text');
  if (!p1 || !p2 || p1 === p2 || !box || !txt) { if (box) box.style.display='none'; return; }
  var s1 = playerSkillsData[p1] || {};
  var s2 = playerSkillsData[p2] || {};
  var sports = Object.keys(Object.assign({}, s1, s2));
  var parts = [];
  sports.forEach(function(sp) {
    var v1 = s1[sp] !== undefined ? parseFloat(s1[sp]) : (sportDefaults[sp] || 0);
    var v2 = s2[sp] !== undefined ? parseFloat(s2[sp]) : (sportDefaults[sp] || 0);
    var sum = v1 + v2;
    if (sum > 0) parts.push((sportLabels[sp] || sp) + ': ' + (sum % 1 === 0 ? sum.toFixed(0) : sum.toFixed(1)));
  });
  if (parts.length) { txt.textContent = parts.join(' / '); box.style.display=''; }
  else { box.style.display='none'; }
}

// ── Profil-Modal ─────────────────────────────────────────────────────────────

var phaseLabels = {
  'setup': 'Vorbereitung', 'group': 'Gruppenphase',
  'ko': 'KO-Phase', 'done': 'Abgeschlossen'
};

document.addEventListener('DOMContentLoaded', function() {
  var modal = document.getElementById('playerProfileModal');
  if (!modal) return;

  modal.addEventListener('show.bs.modal', function(e) {
    var trigger = e.relatedTarget;
    var pid = trigger ? trigger.dataset.playerId : null;
    var openTab = trigger ? trigger.dataset.openTab : null;
    if (!pid) return;

    // Reset
    document.getElementById('profileLoading').style.display = '';
    document.getElementById('profileContent').style.display = 'none';
    document.getElementById('profileFooter').style.display = 'none';
    document.getElementById('profileModalName').textContent = '…';

    // Switch to requested tab or default to Stammdaten
    if (openTab === 'edit') {
      bootstrap.Tab.getOrCreateInstance(document.getElementById('profileTabStammBtn')).show();
    }

    fetch(profileBaseUrl + '/' + pid + '/profile')
      .then(function(r) { return r.json(); })
      .then(function(data) { fillProfileModal(data, pid); })
      .catch(function() {
        document.getElementById('profileLoading').innerHTML =
          '<p class="text-danger p-4">Fehler beim Laden des Profils.</p>';
      });
  });
});

function fillProfileModal(data, pid) {
  var p = data.player;

  // Titel
  var fullName = ((p.firstname || '') + ' ' + (p.name || '')).trim();
  document.getElementById('profileModalName').textContent = fullName + (p.club ? ' · ' + p.club : '');

  // Formular-Action
  document.getElementById('profileEditForm').action = profileBaseUrl + '/' + pid + '/edit';

  // Stammdaten-Felder füllen
  document.getElementById('pf-name').value      = p.name      || '';
  document.getElementById('pf-firstname').value = p.firstname || '';
  document.getElementById('pf-club').value      = p.club      || '';
  document.getElementById('pf-gender').value    = p.gender    || '';
  document.getElementById('pf-pass_nr').value   = p.pass_nr   || '';
  document.getElementById('pf-email').value     = p.email     || '';

  // Spielstärken
  Object.keys(sportLabels).forEach(function(sk) {
    var inp = document.getElementById('pf-skill-' + sk);
    if (!inp) return;
    var v = data.skills[sk];
    if (!v) { inp.value = ''; return; }
    inp.value = (sk === 'tennis') ? parseFloat(v).toFixed(1) : parseInt(v, 10);
  });

  // Externes Profil (RatingsCentral)
  var rcIdEl = document.getElementById('pf-ratingscentral_id');
  if (rcIdEl) rcIdEl.value = p.ratingscentral_id || '';

  var rcLink = document.getElementById('pf-rc-link');
  if (rcLink) {
    if (p.ratingscentral_id) {
      rcLink.href = 'https://www.ratingscentral.com/Player.php?PlayerID=' + encodeURIComponent(p.ratingscentral_id);
      rcLink.style.display = '';
    } else {
      rcLink.style.display = 'none';
    }
  }

  var rcStatus = document.getElementById('pf-rc-status');
  if (rcStatus) { rcStatus.className = 'form-text'; rcStatus.textContent = ''; }

  // Externes Profil (ÖTV)
  var oetvIdEl = document.getElementById('pf-oetv_nr');
  if (oetvIdEl) oetvIdEl.value = p.oetv_nr || '';

  var oetvLink = document.getElementById('pf-oetv-link');
  if (oetvLink) {
    if (p.oetv_nr) {
      oetvLink.href = 'https://www.oetv.at/spieler/' + encodeURIComponent(p.oetv_nr);
      oetvLink.style.display = '';
    } else {
      oetvLink.style.display = 'none';
    }
  }

  // Löschen im Modal-Footer (nur Admin)
  var deleteForm = document.getElementById('profileDeleteForm');
  if (deleteForm) {
    deleteForm.action = profileBaseUrl + '/' + pid + '/delete';
    deleteForm.dataset.confirm = 'Spieler wirklich löschen?';
  }

  // Aktiv/Inaktiv-Toggle im Modal-Footer
  var toggleForm = document.getElementById('profileToggleForm');
  var toggleBtn  = document.getElementById('profileToggleBtn');
  if (toggleForm && toggleBtn) {
    toggleForm.action = profileBaseUrl + '/' + pid + '/toggle-active';
    var isActive = p.is_active == 1;
    toggleBtn.innerHTML = isActive
      ? '<i class="bi bi-pause-circle me-1"></i>Inaktiv setzen'
      : '<i class="bi bi-play-circle me-1"></i>Aktivieren';
    toggleBtn.className = 'btn btn-sm ' + (isActive ? 'btn-outline-warning' : 'btn-outline-success');
    toggleForm.dataset.confirm = isActive
      ? 'Spieler inaktiv setzen?'
      : 'Spieler wieder aktivieren?';
  }

  // Bewerbe Tab
  document.getElementById('profileCompsContent').innerHTML = buildCompsHtml(data);

  // Anzeigen
  document.getElementById('profileLoading').style.display = 'none';
  document.getElementById('profileContent').style.display = '';
  document.getElementById('profileFooter').style.display = '';
}

function buildCompsHtml(data) {
  var html = '';

  // Einzelbewerbe
  html += '<h6 class="mb-2"><i class="bi bi-person me-1 text-secondary"></i>Bewerbe als Einzelspieler</h6>';
  if (data.comps && data.comps.length > 0) {
    // Nach Turnier gruppieren
    var byTournament = {};
    data.comps.forEach(function(c) {
      if (!byTournament[c.tname]) byTournament[c.tname] = [];
      byTournament[c.tname].push(c);
    });
    html += '<div class="list-group list-group-flush mb-3">';
    Object.keys(byTournament).forEach(function(tname) {
      html += '<div class="list-group-item px-0 py-1">';
      html += '<div class="text-muted small fw-semibold mb-1"><i class="bi bi-calendar-event me-1"></i>' + esc(tname) + '</div>';
      byTournament[tname].forEach(function(c) {
        var phase = phaseLabels[c.phase] || c.phase;
        html += '<div class="ms-3 d-flex align-items-center gap-2 mb-1">';
        html += '<i class="bi bi-trophy text-warning small"></i>';
        html += '<a href="' + esc(profileBaseUrl.replace(/\/player$/, '/competition/') + c.cid) + '" class="text-decoration-none">' + esc(c.cname) + '</a>';
        html += '<span class="badge bg-light text-secondary border small">' + esc(phase) + '</span>';
        html += '</div>';
      });
      html += '</div>';
    });
    html += '</div>';
  } else {
    html += '<p class="text-muted small mb-3">Kein Bewerb als Einzelspieler.</p>';
  }

  // Doppel
  html += '<h6 class="mb-2 mt-3"><i class="bi bi-people-fill me-1 text-secondary"></i>Doppelpaarungen</h6>';
  if (data.doubles && data.doubles.length > 0) {
    html += '<div class="list-group list-group-flush mb-2">';
    data.doubles.forEach(function(d) {
      html += '<div class="list-group-item px-0 py-2">';
      html += '<div class="fw-semibold"><i class="bi bi-people me-1 text-primary"></i>' + esc(d.name) + '</div>';
      html += '<div class="text-muted small ms-3">Partner: ' + esc(d.partner_name) + '</div>';
      if (d.competitions && d.competitions.length > 0) {
        d.competitions.forEach(function(c) {
          var phase = phaseLabels[c.phase] || c.phase;
          html += '<div class="ms-3 d-flex align-items-center gap-2 mt-1">';
          html += '<i class="bi bi-calendar-event text-muted small"></i>';
          html += '<span class="text-muted small">' + esc(c.tname) + '</span>';
          html += '<i class="bi bi-chevron-right text-muted" style="font-size:.65rem"></i>';
          html += '<a href="' + esc(profileBaseUrl.replace(/\/player$/, '/competition/') + c.cid) + '" class="text-decoration-none small">' + esc(c.cname) + '</a>';
          html += '<span class="badge bg-light text-secondary border" style="font-size:.7rem">' + esc(phase) + '</span>';
          html += '</div>';
        });
      } else {
        html += '<div class="ms-3 text-muted small mt-1">Noch keinem Bewerb zugeordnet.</div>';
      }
      html += '</div>';
    });
    html += '</div>';
  } else {
    html += '<p class="text-muted small">Keine Doppelpaarungen.</p>';
  }

  // Teams
  html += '<h6 class="mb-2 mt-3"><i class="bi bi-shield me-1 text-secondary"></i>Teams</h6>';
  if (data.teams && data.teams.length > 0) {
    html += '<div class="list-group list-group-flush mb-2">';
    data.teams.forEach(function(tm) {
      html += '<div class="list-group-item px-0 py-2">';
      html += '<div class="fw-semibold"><i class="bi bi-shield me-1 text-primary"></i>' + esc(tm.name) + '</div>';
      if (tm.competitions && tm.competitions.length > 0) {
        tm.competitions.forEach(function(c) {
          var phase = phaseLabels[c.phase] || c.phase;
          html += '<div class="ms-3 d-flex align-items-center gap-2 mt-1">';
          html += '<i class="bi bi-calendar-event text-muted small"></i>';
          html += '<span class="text-muted small">' + esc(c.tname) + '</span>';
          html += '<i class="bi bi-chevron-right text-muted" style="font-size:.65rem"></i>';
          html += '<a href="' + esc(profileBaseUrl.replace(/\/player$/, '/competition/') + c.cid) + '" class="text-decoration-none small">' + esc(c.cname) + '</a>';
          html += '<span class="badge bg-light text-secondary border" style="font-size:.7rem">' + esc(phase) + '</span>';
          html += '</div>';
        });
      } else {
        html += '<div class="ms-3 text-muted small mt-1">Noch keinem Bewerb zugeordnet.</div>';
      }
      html += '</div>';
    });
    html += '</div>';
  } else {
    html += '<p class="text-muted small">Keinem Team zugeordnet.</p>';
  }

  return html;
}

function esc(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ── RatingsCentral-Sync ───────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  var syncBtn = document.getElementById('pf-rc-sync');
  if (!syncBtn) return;

  syncBtn.addEventListener('click', function() {
    var form   = document.getElementById('profileEditForm');
    var action = form ? form.action : '';
    var pidMatch = action.match(/\/player\/(\d+)\//);
    var pid    = pidMatch ? pidMatch[1] : null;
    var rcId   = (document.getElementById('pf-ratingscentral_id') || {}).value || '';
    var status = document.getElementById('pf-rc-status');

    if (!pid) { status.className = 'form-text text-danger'; status.textContent = 'Spieler-ID nicht ermittelbar.'; return; }
    if (!rcId.trim()) { status.className = 'form-text text-warning'; status.textContent = 'Bitte zuerst RatingsCentral-ID eingeben und speichern.'; return; }

    syncBtn.disabled = true;
    status.className = 'form-text text-muted';
    status.textContent = 'Abrufen…';

    fetch(profileBaseUrl + '/' + pid + '/sync/ratingscentral')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        syncBtn.disabled = false;
        if (d.error) {
          status.className = 'form-text text-danger';
          status.textContent = d.error;
        } else {
          status.className = 'form-text text-success';
          status.textContent = 'Gespeichert: ' + d.skill + ' Punkte';
          var ttInp = document.getElementById('pf-skill-tischtennis');
          if (ttInp) ttInp.value = d.skill;
          // Link-Button aktualisieren
          var rcLink = document.getElementById('pf-rc-link');
          var rcId2  = (document.getElementById('pf-ratingscentral_id') || {}).value || '';
          if (rcLink && rcId2) {
            rcLink.href = 'https://www.ratingscentral.com/Player.php?PlayerID=' + encodeURIComponent(rcId2);
            rcLink.style.display = '';
          }
        }
      })
      .catch(function() {
        syncBtn.disabled = false;
        status.className = 'form-text text-danger';
        status.textContent = 'Netzwerkfehler';
      });
  });
});

// ── ÖTV-Link live aktualisieren ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  var oetvInput = document.getElementById('pf-oetv_nr');
  var oetvLink  = document.getElementById('pf-oetv-link');
  if (!oetvInput || !oetvLink) return;
  oetvInput.addEventListener('input', function() {
    var val = oetvInput.value.trim();
    if (val) {
      oetvLink.href = 'https://www.oetv.at/spieler/' + encodeURIComponent(val);
      oetvLink.style.display = '';
    } else {
      oetvLink.style.display = 'none';
    }
  });
});

// ── Einzel-Sync (Icon in Tabellenzeile) ──────────────────────────────────────
document.addEventListener('click', function(e) {
  var btn = e.target.closest('.rc-sync-btn');
  if (!btn) return;
  var pid = btn.dataset.pid;
  var icon = btn.querySelector('i');
  btn.disabled = true;
  icon.className = 'bi bi-hourglass-split';
  fetch(profileBaseUrl + '/' + pid + '/sync/ratingscentral')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      btn.disabled = false;
      if (d.error) {
        icon.className = 'bi bi-exclamation-circle text-danger';
        btn.title = d.error;
      } else {
        icon.className = 'bi bi-check-circle text-success';
        btn.title = 'Aktualisiert: ' + d.skill;
        var badge = document.getElementById('tt-skill-' + pid);
        if (badge) badge.lastChild.textContent = ' ' + d.skill;
        setTimeout(function() { icon.className = 'bi bi-arrow-clockwise'; btn.title = 'TT-Spielstärke von RatingsCentral abrufen'; }, 3000);
      }
    })
    .catch(function() {
      btn.disabled = false;
      icon.className = 'bi bi-exclamation-circle text-danger';
      btn.title = 'Netzwerkfehler';
    });
});

// ── Bulk-Sync (alle Spieler mit RatingsCentral-ID, einzeln nacheinander) ──────
document.addEventListener('DOMContentLoaded', function() {
  var bulkBtn = document.getElementById('btn-sync-all-tt');
  if (!bulkBtn) return;
  var statusEl = document.getElementById('sync-all-status');

  bulkBtn.addEventListener('click', async function() {
    var pids = Array.from(document.querySelectorAll('.rc-sync-btn[data-pid]'))
                    .map(function(b) { return b.dataset.pid; });
    if (!pids.length) return;

    bulkBtn.disabled = true;
    statusEl.style.display = '';
    var synced = 0, errors = 0, total = pids.length;

    for (var i = 0; i < pids.length; i++) {
      var pid = pids[i];
      statusEl.className = 'small ms-2 text-muted';
      statusEl.textContent = (i + 1) + '/' + total + '…';
      try {
        var r = await fetch(profileBaseUrl + '/' + pid + '/sync/ratingscentral');
        var d = await r.json();
        if (d.error) {
          errors++;
        } else {
          synced++;
          var badge = document.getElementById('tt-skill-' + pid);
          if (badge) badge.lastChild.textContent = ' ' + d.skill;
        }
      } catch(e) {
        errors++;
      }
    }

    bulkBtn.disabled = false;
    if (errors > 0) {
      statusEl.className = 'small ms-2 text-warning';
      statusEl.textContent = synced + ' aktualisiert, ' + errors + ' Fehler';
    } else {
      statusEl.className = 'small ms-2 text-success';
      statusEl.textContent = synced + ' aktualisiert';
    }
  });
});

// Tab-Persistenz via localStorage
document.addEventListener('DOMContentLoaded', function() {
  var STORE = 'players_active_tab';
  // Hash hat Vorrang (wird vom globalen Handler in _base.php aktiviert),
  // sonst letzten Tab aus localStorage wiederherstellen
  if (!location.hash) {
    var btnId = localStorage.getItem(STORE);
    if (btnId) {
      var btn = document.getElementById(btnId);
      if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    }
  }
  document.querySelectorAll('#players-tabs button[data-bs-toggle="tab"]').forEach(function(b) {
    b.addEventListener('shown.bs.tab', function() { localStorage.setItem(STORE, b.id); });
  });
});

// ── Alle/Keine für Checkbox-Listen ───────────────────────────────────────────
document.addEventListener('click', function(e) {
  var form = e.target.closest('form');
  if (!form) return;
  if (e.target.closest('.add-select-all')) {
    e.preventDefault();
    form.querySelectorAll('.add-entry-item input[type="checkbox"]').forEach(function(cb) { cb.checked = true; });
  }
  if (e.target.closest('.add-select-none')) {
    e.preventDefault();
    form.querySelectorAll('.add-entry-item input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
  }
});

// ── Inaktive Einträge ein-/ausblenden ─────────────────────────────────────────
// Zustand je Tabelle merken, damit er einen AJAX-Refresh des Tabs übersteht.
var inactiveState = {};
function initInactiveToggles(root) {
  (root || document).querySelectorAll('.show-inactive-cb').forEach(function(cb) {
    var table = cb.dataset.table;
    function applyFilter() {
      document.querySelectorAll('#' + table + ' tbody tr[data-active="0"]').forEach(function(r) {
        r.classList.toggle('d-none', !cb.checked);
      });
    }
    if (table in inactiveState) cb.checked = inactiveState[table];
    if (!cb.dataset.inactiveBound) {
      cb.dataset.inactiveBound = '1';
      cb.addEventListener('change', function() { inactiveState[table] = cb.checked; applyFilter(); });
    }
    applyFilter(); // inaktive sofort verstecken (gemäß gemerktem Zustand)
  });
}
document.addEventListener('DOMContentLoaded', function() { initInactiveToggles(document); });
document.addEventListener('content:refreshed', function(e) { initInactiveToggles(e.detail.container); });
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
