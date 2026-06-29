<?php $active_theme = get_setting('theme', 'default'); ?>
<!doctype html>
<html lang="de"<?= $active_theme === 'dunkel' ? ' data-bs-theme="dark"' : '' ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title><?= e($page_title ?? 'Turnierverwaltung') ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?php if ($active_theme === 'elegant'): ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <?php elseif ($active_theme === 'modern'): ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <?php elseif ($active_theme === 'klassisch'): ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Lato:wght@400;600&display=swap" rel="stylesheet">
  <?php elseif ($active_theme === 'sport'): ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= url('static/style.css') ?>">
  <link rel="stylesheet" href="<?= url('static/themes/' . $active_theme . '.css') ?>">
  <link rel="icon" type="image/x-icon" href="<?= url('static/favicon.ico') ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 sticky-top">
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
        <?php if ($u): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= e($u['username']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted small"><?= e($u['email']) ?></span></li>
            <?php if (is_admin()): ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= url('admin/users') ?>"><i class="bi bi-shield-lock me-1"></i>Benutzer</a></li>
            <li><a class="dropdown-item" href="<?= url('admin/design') ?>"><i class="bi bi-palette me-1"></i>Design</a></li>
            <li><a class="dropdown-item" href="<?= url('admin/impressum') ?>"><i class="bi bi-file-text me-1"></i>Impressum</a></li>
            <?php $ver = get_git_version(); if ($ver !== ''): ?>
            <li><hr class="dropdown-divider"></li>
            <li><span class="dropdown-item-text text-muted small text-nowrap"><i class="bi bi-clock me-1"></i>Version: <?= e($ver) ?></span></li>
            <?php endif; ?>
            <?php endif; ?>
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

<?php $impressum = get_setting('impressum', ''); ?>
<?php if (trim($impressum) !== ''): ?>
<footer class="container text-center text-muted small py-3 border-top mt-4">
  <?= nl2br(e($impressum)) ?>
</footer>
<?php endif; ?>

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
      if (e.target.closest('.no-hash')) return;   // z.B. Gruppen-Tabs nicht in die URL schreiben
      var t = e.target.getAttribute('data-bs-target');
      if (t) history.replaceState(null, '', t);
    });
  });
})();
// Scroll-Position über „echte" Reloads (Formular-Submits, die neu laden) bewahren, damit
// die Seite nach dem Speichern nicht nach oben springt. js-ajax-Formulare laden ohnehin
// nicht neu und werden übersprungen.
(function() {
  document.addEventListener('submit', function(e) {
    var f = e.target;
    // js-ajax & inline-Ergebnis-Formulare laden nicht neu → kein Scroll-Merken nötig
    if (!f || !f.classList || f.classList.contains('js-ajax') || f.classList.contains('js-inline-clear') || f.classList.contains('js-bracket-clear')) return;
    if (e.defaultPrevented) return;   // z.B. data-confirm abgelehnt
    try {
      sessionStorage.setItem('scrollRestore', JSON.stringify({
        p: location.pathname, y: window.scrollY || window.pageYOffset || 0, t: Date.now()
      }));
    } catch (_) {}
  });
  function restore() {
    var raw; try { raw = sessionStorage.getItem('scrollRestore'); sessionStorage.removeItem('scrollRestore'); } catch (_) { return; }
    if (!raw) return;
    var d; try { d = JSON.parse(raw); } catch (_) { return; }
    if (!d || d.p !== location.pathname || (Date.now() - d.t) > 15000 || !d.y) return;
    window.scrollTo(0, d.y);
  }
  window.addEventListener('load', function() { setTimeout(restore, 0); });
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
  // Idempotent: bindet Sortier-Header innerhalb von root (Default: ganze Seite). Nach einem
  // AJAX-Refresh erneut aufrufbar (neue <th> ohne data-sort-bound werden frisch gebunden).
  window.initSortable = function(root) {
    (root || document).querySelectorAll('table[data-sortable] thead th:not(.no-sort)').forEach(function(th) {
      if (th.dataset.sortBound) return;
      th.dataset.sortBound = '1';
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
  };
  document.addEventListener('DOMContentLoaded', function() { window.initSortable(document); });
})();

// ── Generische Inline-AJAX-Schicht ───────────────────────────────────────────
// Formulare mit class="js-ajax" werden ohne Reload gespeichert: POST per fetch,
// dem Redirect wird gefolgt, die gelieferte Seite geparst und die in
// data-refresh="#id, #id2" genannten Container im Live-DOM ersetzt. Flash-Meldungen
// werden oben in <main> eingeblendet. Optional: data-modal-close (Modal schließen).
(function() {
  function showFlashes(doc) {
    var main = document.querySelector('main');
    var src  = doc.querySelector('main');
    if (!main || !src) return [];
    main.querySelectorAll(':scope > .alert.js-ajax-flash').forEach(function(a) { a.remove(); });
    var alerts = Array.prototype.slice.call(src.querySelectorAll(':scope > .alert'));
    alerts.reverse().forEach(function(a) {
      a.classList.add('js-ajax-flash');
      main.insertBefore(a, main.firstChild);
      if (!a.classList.contains('alert-danger')) {
        setTimeout(function() {
          a.classList.remove('show');
          setTimeout(function() { a.remove(); }, 200);
        }, 3000);
      }
    });
    return alerts;
  }

  function refresh(doc, sel) {
    sel.split(',').forEach(function(s) {
      s = s.trim(); if (!s) return;
      var id    = s.charAt(0) === '#' ? s.slice(1) : s;
      var fresh = doc.getElementById(id);
      var live  = document.getElementById(id);
      if (!fresh || !live) return;
      live.innerHTML = fresh.innerHTML;
      window.initSortable(live);
      // Zähler im zugehörigen Tab-Button aktualisieren (ohne aktiven Zustand zu ändern).
      var liveBtn  = document.querySelector('[data-bs-target="#' + id + '"]');
      var freshBtn = doc.querySelector('[data-bs-target="#' + id + '"]');
      if (liveBtn && freshBtn) liveBtn.innerHTML = freshBtn.innerHTML;
      document.dispatchEvent(new CustomEvent('content:refreshed', { detail: { container: live } }));
    });
  }

  document.addEventListener('submit', function(ev) {
    var form = ev.target.closest('form.js-ajax');
    if (!form) return;
    if (ev.defaultPrevented) return;          // data-confirm wurde abgelehnt
    ev.preventDefault();
    var btn = form.querySelector('[type="submit"], button:not([type])');
    if (btn) btn.disabled = true;
    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function(r) { return r.text(); })
      .then(function(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        if (form.dataset.refresh) refresh(doc, form.dataset.refresh);
        var alerts = showFlashes(doc);
        var hasErr = alerts.some(function(a) { return a.classList.contains('alert-danger'); });
        if (!hasErr && form.dataset.modalClose) {
          var m = form.closest('.modal');
          if (m && window.bootstrap) { var inst = bootstrap.Modal.getInstance(m); if (inst) inst.hide(); }
        }
        // Nur explizit markierte Formulare leeren (z.B. „Neu"-Formulare). Edit-Formulare,
        // deren Felder per JS befüllt werden (z.B. Profil-Popup), dürfen NICHT zurückgesetzt
        // werden — sonst verschwinden die Werte trotz erfolgreichem Speichern.
        if (!hasErr && form.dataset.ajaxReset !== undefined) { try { form.reset(); } catch (e) {} }
        if (btn) btn.disabled = false;
      })
      .catch(function() { alert('Speichern fehlgeschlagen.'); if (btn) btn.disabled = false; });
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
