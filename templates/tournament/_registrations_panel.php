<div class="d-flex align-items-center gap-2 mb-3 mt-2">
  <h5 class="mb-0">
    Nennungen
    <?php if ($registrations): ?>
    <span class="badge bg-warning text-dark ms-1"><?= count($registrations) ?> offen</span>
    <?php endif; ?>
    <?php if ($change_requests): ?>
    <span class="badge bg-info text-dark ms-1"><?= count($change_requests) ?> Änderung<?= count($change_requests) > 1 ? 'en' : '' ?></span>
    <?php endif; ?>
  </h5>
  <div class="d-flex gap-2 flex-wrap ms-auto">
    <div class="btn-group btn-group-sm">
      <span class="btn btn-sm btn-outline-secondary disabled pe-none" style="cursor:default">Nennungen</span>
      <a href="<?= url('tournament/' . $t['id'] . '/registrations/pdf') ?>" class="btn btn-outline-danger" target="_blank">
        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
      </a>
      <a href="<?= url('tournament/' . $t['id'] . '/registrations/csv') ?>" class="btn btn-outline-success">
        <i class="bi bi-filetype-csv me-1"></i>CSV
      </a>
    </div>
  </div>
</div>

<?php if ($registrations): ?>
<div class="card shadow-sm mb-3">
  <div class="card-header fw-semibold text-warning-emphasis bg-warning-subtle">
    <i class="bi bi-clock me-1"></i>Ausstehend (<?= count($registrations) ?>)
  </div>
  <div class="list-group list-group-flush">
    <?php foreach ($registrations as $item): $r = $item['reg']; ?>
    <div class="list-group-item">
      <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
        <div>
          <span class="fw-semibold"><?= e($r['lastname']) ?> <?= e($r['firstname']) ?></span>
          <?php if ($r['gender']): ?><span class="badge bg-secondary ms-1 fw-normal"><?= e($r['gender']) ?></span><?php endif; ?>
          <div class="text-muted small">
            <?= $r['club'] ? e($r['club']) : '' ?>
            <?= $r['pass_nr'] ? ' · Pass: ' . e($r['pass_nr']) : '' ?>
          </div>
          <div class="text-muted" style="font-size:.75rem"><i class="bi bi-clock me-1"></i><?= e($r['created_at']) ?></div>
        </div>
        <?php $pend_count = count(array_filter($item['competitions'], fn($c) => $c['status'] === 'pending')); ?>
        <?php if ($pend_count > 1): ?>
        <div class="d-flex gap-1 flex-shrink-0">
          <form method="post" action="<?= url('registration/' . $r['id'] . '/confirm') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-success btn-sm" title="Alle bestätigen"><i class="bi bi-check-all"></i> Alle</button>
          </form>
          <form method="post" action="<?= url('registration/' . $r['id'] . '/reject') ?>"
                onsubmit="return confirm('Alle Nennungen von <?= e($r['lastname']) ?> <?= e($r['firstname']) ?> ablehnen?')">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i> Alle</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php foreach ($item['competitions'] as $comp): ?>
      <div class="d-flex align-items-center gap-2 mb-1 ps-2">
        <?php if ($comp['status'] === 'pending'): ?>
          <span class="badge bg-warning text-dark" style="min-width:6rem">offen</span>
        <?php elseif ($comp['status'] === 'confirmed'): ?>
          <span class="badge bg-success" style="min-width:6rem">bestätigt</span>
        <?php else: ?>
          <span class="badge bg-danger" style="min-width:6rem">abgelehnt</span>
        <?php endif; ?>
        <span class="small fw-semibold"><?= e($comp['name']) ?></span>
        <?php if ($comp['status'] === 'pending'): ?>
        <div class="d-flex gap-1 ms-auto">
          <form method="post" action="<?= url('registration/' . $r['id'] . '/comp/' . $comp['cid'] . '/confirm') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-success btn-sm py-0 px-2"><i class="bi bi-check-lg"></i></button>
          </form>
          <form method="post" action="<?= url('registration/' . $r['id'] . '/comp/' . $comp['cid'] . '/reject') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm py-0 px-2"><i class="bi bi-x-lg"></i></button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($change_requests): ?>
<div class="card shadow-sm mb-3 border-info">
  <div class="card-header fw-semibold text-info-emphasis bg-info-subtle">
    <i class="bi bi-arrow-repeat me-1"></i>Änderungsanträge (<?= count($change_requests) ?>)
  </div>
  <div class="list-group list-group-flush">
    <?php foreach ($change_requests as $item): $cr = $item['rcr']; ?>
    <div class="list-group-item">
      <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
        <div>
          <span class="fw-semibold"><?= e($cr['lastname']) ?> <?= e($cr['firstname']) ?></span>
          <span class="badge ms-2 <?= $cr['request_type'] === 'withdraw' ? 'bg-danger' : 'bg-primary' ?>">
            <?= $cr['request_type'] === 'withdraw' ? 'Rückzug' : 'Bewerbsänderung' ?>
          </span>
          <div class="text-muted small"><?= e($cr['email']) ?> · <?= e($cr['created_at']) ?></div>
        </div>
        <?php $pend_count = count(array_filter($item['competitions'], fn($c) => $c['status'] === 'pending')); ?>
        <?php if ($cr['request_type'] === 'withdraw' || $pend_count > 1): ?>
        <div class="d-flex gap-1 flex-shrink-0">
          <form method="post" action="<?= url('reg-change/' . $cr['id'] . '/confirm') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-success btn-sm"><i class="bi bi-check-all"></i><?= $cr['request_type'] === 'modify' ? ' Alle' : '' ?></button>
          </form>
          <form method="post" action="<?= url('reg-change/' . $cr['id'] . '/reject') ?>"
                onsubmit="return confirm('Antrag ablehnen?')">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i><?= $cr['request_type'] === 'modify' ? ' Alle' : '' ?></button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($cr['request_type'] === 'modify'): ?>
      <?php foreach ($item['competitions'] as $comp): ?>
      <div class="d-flex align-items-center gap-2 mb-1 ps-2">
        <?php if ($comp['status'] === 'pending'): ?>
        <span class="badge bg-warning text-dark" style="min-width:6rem">offen</span>
        <?php elseif ($comp['status'] === 'confirmed'): ?>
        <span class="badge bg-success" style="min-width:6rem">bestätigt</span>
        <?php else: ?>
        <span class="badge bg-danger" style="min-width:6rem">abgelehnt</span>
        <?php endif; ?>
        <?php if ($comp['action'] === 'remove'): ?>
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
          <i class="bi bi-dash-circle me-1"></i>Entfernen
        </span>
        <?php else: ?>
        <span class="badge bg-success-subtle text-success border border-success-subtle">
          <i class="bi bi-plus-circle me-1"></i>Hinzufügen
        </span>
        <?php endif; ?>
        <span class="small fw-semibold"><?= e($comp['name']) ?></span>
        <?php if ($comp['status'] === 'pending'): ?>
        <div class="d-flex gap-1 ms-auto">
          <form method="post" action="<?= url('reg-change/' . $cr['id'] . '/comp/' . $comp['competition_id'] . '/confirm') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-success btn-sm py-0 px-2"><i class="bi bi-check-lg"></i></button>
          </form>
          <form method="post" action="<?= url('reg-change/' . $cr['id'] . '/comp/' . $comp['competition_id'] . '/reject') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm py-0 px-2"><i class="bi bi-x-lg"></i></button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($history): ?>
<div class="card shadow-sm mb-3">
  <div class="card-header fw-semibold">
    <i class="bi bi-clock-history me-1"></i>Historie (<?= count($history) ?>)
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Datum</th><th>Name</th><th>Typ</th><th>Bewerbe</th><th class="text-center">Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <tr>
          <td class="text-muted small text-nowrap"><?= e($h['date']) ?></td>
          <td class="fw-semibold"><?= e($h['lastname']) ?> <?= e($h['firstname']) ?></td>
          <td>
            <?php $tl = $h['type_label']; ?>
            <span class="badge <?= $tl === 'Nennung' ? 'bg-secondary' : ($tl === 'Rückzug' ? 'bg-danger' : 'bg-primary') ?>">
              <?= e($tl) ?>
            </span>
          </td>
          <td>
            <?php if ($tl === 'Rückzug'): ?>
            <span class="text-muted small">gesamte Nennung</span>
            <?php else: foreach ($h['competitions'] as $comp): ?>
            <div class="d-flex align-items-center gap-1 mb-1">
              <?php if ($tl === 'Bewerbsänderung'):
                if ($comp['action'] === 'add' && $comp['status'] === 'confirmed'):
                  echo '<span class="badge bg-success" style="font-size:.7rem">angemeldet</span>';
                elseif ($comp['action'] === 'add' && $comp['status'] === 'rejected'):
                  echo '<span class="badge bg-secondary" style="font-size:.7rem">nicht angemeldet</span>';
                elseif ($comp['action'] === 'remove' && $comp['status'] === 'confirmed'):
                  echo '<span class="badge bg-danger" style="font-size:.7rem">abgemeldet</span>';
                else:
                  echo '<span class="badge bg-secondary" style="font-size:.7rem">nicht abgemeldet</span>';
                endif;
              else:
                $sc = $comp['status'] === 'confirmed' ? 'bg-success' : 'bg-danger';
                $sl = $comp['status'] === 'confirmed' ? 'bestätigt' : 'abgelehnt';
                echo '<span class="badge ' . $sc . '" style="font-size:.7rem">' . $sl . '</span>';
              endif; ?>
              <span class="small"><?= e($comp['name']) ?></span>
            </div>
            <?php endforeach; endif; ?>
          </td>
          <td class="text-center">
            <span class="badge <?= $h['overall_status'] === 'confirmed' ? 'bg-success' : 'bg-danger' ?>">
              <?= $h['overall_status'] === 'confirmed' ? 'bestätigt' : 'abgelehnt' ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
