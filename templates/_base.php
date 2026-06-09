<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title><?= e($page_title ?? 'Turnierverwaltung') ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('static/style.css') ?>">
  <link rel="icon" type="image/x-icon" href="<?= url('static/favicon.ico') ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url() ?>">
      <img src="<?= url('static/logo_unionsaxen.jpg') ?>"
           alt="Sport Union Saxen"
           height="56"
           style="border-radius:50%;border:2px solid #f0a800;">
      Turnierverwaltung
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= url() ?>"><i class="bi bi-trophy me-1"></i>Turniere</a></li>
        <?php if (can_edit()): ?>
        <li class="nav-item"><a class="nav-link" href="<?= url('players') ?>"><i class="bi bi-people me-1"></i>Spielerregister</a></li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= url('nennung/link') ?>">
            <i class="bi bi-person-gear me-1"></i>Nennungen
          </a>
        </li>
      </ul>
      <ul class="navbar-nav">
        <?php $u = current_user(); ?>
        <li class="nav-item"><a class="nav-link" href="<?= url('hilfe') ?>"><i class="bi bi-question-circle me-1"></i>Hilfe</a></li>
        <?php if (is_admin()): ?>
        <li class="nav-item"><a class="nav-link" href="<?= url('admin/users') ?>"><i class="bi bi-shield-lock me-1"></i>Benutzer</a></li>
        <?php endif; ?>
        <?php if ($u): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= e($u['username']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted small"><?= e($u['email']) ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= url('logout') ?>"><i class="bi bi-box-arrow-right me-1"></i>Abmelden</a></li>
          </ul>
        </li>
        <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="<?= url('login') ?>"><i class="bi bi-box-arrow-in-right me-1"></i>Anmelden</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('register') ?>"><i class="bi bi-person-plus me-1"></i>Registrieren</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="container pb-5">
  <?php foreach ($flashes as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
    <?= isset($f['html']) && $f['html'] ? $f['message'] : e($f['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endforeach; ?>

  <?= $content ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// URL-Hash <-> Bootstrap-Tabs (alle Seiten)
(function() {
  // Bei jedem Form-Submit den aktiven Tab als _tab-Parameter mitschicken.
  // redirect() in helpers.php liest ihn aus und hängt ihn als Fragment ans Redirect-Ziel.
  document.addEventListener('submit', function(e) {
    var msg = e.target.dataset.confirm;
    if (msg && !confirm(msg)) { e.preventDefault(); return; }
    var hash = location.hash;
    if (!hash || !hash.startsWith('#tab-')) return;
    var form = e.target;
    if (form.querySelector('input[name="_tab"]')) return;
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = '_tab'; inp.value = hash;
    form.appendChild(inp);
  });

  // Beim Laden: Tab aus Hash aktivieren; bei Tab-Klick: Hash in URL schreiben.
  document.addEventListener('DOMContentLoaded', function() {
    var hash = location.hash;
    if (hash) {
      var btns = document.querySelectorAll('[data-bs-toggle="tab"]');
      for (var i = 0; i < btns.length; i++) {
        if (btns[i].getAttribute('data-bs-target') === hash && !btns[i].closest('.modal')) {
          bootstrap.Tab.getOrCreateInstance(btns[i]).show();
          break;
        }
      }
    }
    document.addEventListener('shown.bs.tab', function(e) {
      if (e.target.closest('.modal')) return;
      var t = e.target.getAttribute('data-bs-target');
      if (t) history.replaceState(null, '', t);
    });
  });
})();
(function() {
  function cellVal(row, idx) {
    var c = row.cells[idx];
    return c ? (c.dataset.sort || c.textContent).trim() : '';
  }
  function parseDate(s) {
    var m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) return +new Date(+m[1], +m[2]-1, +m[3]);
    m = s.match(/^(\d{2})\.(\d{2})\.(\d{4})/);
    if (m) return +new Date(+m[3], +m[2]-1, +m[1]);
    return null;
  }
  function sortTable(table, idx, asc) {
    var tbody = table.querySelector('tbody');
    var rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort(function(a, b) {
      var ca = cellVal(a, idx), cb = cellVal(b, idx);
      var da = parseDate(ca), db = parseDate(cb);
      if (da !== null && db !== null) return asc ? da - db : db - da;
      var na = parseFloat(ca.replace(',', '.')), nb = parseFloat(cb.replace(',', '.'));
      if (!isNaN(na) && !isNaN(nb)) return asc ? na - nb : nb - na;
      return asc ? ca.localeCompare(cb, 'de') : cb.localeCompare(ca, 'de');
    });
    rows.forEach(function(r) { tbody.appendChild(r); });
  }
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('table[data-sortable] thead th:not(.no-sort)').forEach(function(th) {
      var table = th.closest('table');
      var idx   = Array.from(th.parentElement.children).indexOf(th);
      var icon  = document.createElement('span');
      icon.className = 'ms-1 text-muted'; icon.style.fontSize = '.7em';
      th.appendChild(icon);
      th.style.cursor = 'pointer'; th.style.userSelect = 'none';
      var asc = true;
      th.addEventListener('click', function() {
        table.querySelectorAll('thead th span.ms-1').forEach(function(s) { s.textContent = ''; });
        sortTable(table, idx, asc);
        icon.textContent = asc ? '▲' : '▼';
        asc = !asc;
      });
    });
  });
})();
(function() {
  document.addEventListener('input', function(e) {
    if (!e.target.classList.contains('table-filter')) return;
    var q = e.target.value.toLowerCase().trim();
    var table = document.getElementById(e.target.dataset.target);
    if (!table) return;
    table.querySelectorAll('tbody tr').forEach(function(row) {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
})();
</script>
<?php if (!empty($extra_js)): ?>
<?= $extra_js ?>
<?php endif; ?>
</body>
</html>
