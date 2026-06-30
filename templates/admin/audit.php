<?php
$role_labels = ['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Betrachter', '' => 'Gast'];
ob_start(); ?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h2 class="mb-0"><i class="bi bi-journal-text me-2"></i>Aktivitätsprotokoll</h2>
  <span class="badge bg-secondary"><?= (int)$total ?> Einträge</span>
  <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
    <form method="get" action="<?= url('admin/audit') ?>" class="d-flex gap-1">
      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm"
             style="min-width:200px" placeholder="Suche: Benutzer, Aktion, Objekt …">
      <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
      <?php if ($q !== ''): ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('admin/audit') ?><?= $status !== '' ? '?status=' . urlencode($status) : '' ?>" title="Suche zurücksetzen"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
    </form>
    <?php $qs = $q !== '' ? '&q=' . urlencode($q) : ''; ?>
    <div class="btn-group btn-group-sm">
      <a class="btn btn-outline-secondary<?= $status === ''       ? ' active' : '' ?>" href="<?= url('admin/audit') ?><?= $q !== '' ? '?q=' . urlencode($q) : '' ?>">Alle</a>
      <a class="btn btn-outline-secondary<?= $status === 'ok'     ? ' active' : '' ?>" href="<?= url('admin/audit') ?>?status=ok<?= $qs ?>">Aktionen</a>
      <a class="btn btn-outline-secondary<?= $status === 'denied' ? ' active' : '' ?>" href="<?= url('admin/audit') ?>?status=denied<?= $qs ?>">Verweigert</a>
    </div>
    <?php if ($total > 0): ?>
    <form method="post" action="<?= url('admin/audit/clear') ?>" data-confirm="Gesamtes Protokoll unwiderruflich löschen?">
      <?= csrf_field() ?>
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Protokoll leeren</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<p class="text-muted small">
  Protokolliert werden alle ändernden Aktionen (Funktionen, die Gästen nicht offenstehen) sowie
  verweigerte Zugriffsversuche. Reine Ansichten und Exporte werden nicht erfasst.
</p>

<?php if (!$rows): ?>
<div class="text-muted">Keine Protokolleinträge.</div>
<?php else: ?>
<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Zeitpunkt</th><th>Benutzer</th><th>Rolle</th>
        <th>Bereich / Aktion</th><th>Objekt</th><th>Pfad</th><th>Status</th><th>IP</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $parts  = explode('.', $r['action'], 2);
        $area   = audit_area_label($parts[0] ?? '');
        $act    = str_replace('_', ' ', $parts[1] ?? ($parts[0] ?? ''));
      ?>
      <tr<?= $r['status'] === 'denied' ? ' class="table-danger"' : '' ?>>
        <td class="text-nowrap small"><?= e($r['created_at']) ?></td>
        <td class="small"><?= e($r['username'] ?: 'Gast') ?></td>
        <td class="small"><?= e($role_labels[$r['role']] ?? $r['role']) ?></td>
        <td class="small"><span class="fw-semibold"><?= e($area) ?></span>
          <span class="text-muted">· <?= e($act) ?></span></td>
        <td class="small"><?= $r['target'] !== '' ? e($r['target']) : '<span class="text-muted">–</span>' ?></td>
        <td class="small text-muted text-break"><code><?= e($r['method']) ?></code> <?= e($r['path']) ?></td>
        <td>
          <?php if ($r['status'] === 'denied'): ?>
          <span class="badge bg-danger">verweigert</span>
          <?php else: ?>
          <span class="badge bg-success-subtle text-success border border-success-subtle">ausgeführt</span>
          <?php endif; ?>
        </td>
        <td class="small text-muted"><?= e($r['ip']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<nav>
  <ul class="pagination pagination-sm">
    <?php
      $pq = ($status !== '' ? '&status=' . urlencode($status) : '')
          . ($q !== ''      ? '&q='      . urlencode($q)      : '');
      for ($i = 1; $i <= $pages; $i++): ?>
    <li class="page-item<?= $i === $page ? ' active' : '' ?>">
      <a class="page-link" href="<?= url('admin/audit') ?>?page=<?= $i ?><?= $pq ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
