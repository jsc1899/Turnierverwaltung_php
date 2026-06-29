<?php
/**
 * Turnier-Monitor – mehrere Bewerbe nebeneinander, je Bewerb eine Spalte.
 * Jede Spalte ist ein eingebettetes <iframe> der Bewerbs-Monitoransicht (embed-Modus),
 * das Gruppen + Spielplan untereinander darstellt und eigenständig scrollt/aktualisiert.
 *
 * Erwartete Variablen: $t, $comps, $mon_show_schedule, $mon_scroll_speed, $mon_scroll_mode, $mon_block_pause.
 */
$sport_icons  = ['tischtennis'=>'🏓','tennis'=>'🎾','fussball'=>'⚽','cornhole'=>'🫘'];
$sport_labels = ['tischtennis'=>'Tischtennis','tennis'=>'Tennis','fussball'=>'Fußball','cornhole'=>'Cornhole'];
$active_theme = get_setting('theme', 'default');

// Einstellungen als Query-Parameter an die eingebetteten Bewerbs-Monitore durchreichen.
$embed_params = http_build_query([
    'embed' => '1',
    'sched' => $mon_show_schedule ? '1' : '0',
    'speed' => $mon_scroll_speed,
    'mode'  => $mon_scroll_mode,
    'pause' => $mon_block_pause,
]);
?><!doctype html>
<html lang="de"<?= $active_theme === 'dunkel' ? ' data-bs-theme="dark"' : '' ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($t['name']) ?> — Monitor</title>
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
  <style>
    html, body { height:100%; margin:0; }
    html { font-size: 18px; }
    body { background:var(--bs-secondary-bg); color:var(--bs-body-color); display:flex; flex-direction:column; overflow:hidden; }
    .tmon-head { flex:0 0 auto; display:flex; align-items:center; flex-wrap:wrap; gap:1rem;
                 border-bottom:3px solid var(--bs-border-color); padding:.7rem 1.2rem; }
    .tmon-head h1 { font-size:2rem; font-weight:800; margin:0; display:flex; align-items:center; gap:.6rem; }
    .tmon-head .sport-ic { font-size:2.3rem; line-height:1; }
    .tmon-clock { margin-left:auto; font-size:1.15rem; color:var(--bs-secondary-color); white-space:nowrap; }
    .tmon-cols { flex:1 1 auto; display:flex; min-height:0; gap:0; }
    .tmon-col { flex:1 1 0; min-width:0; display:flex; flex-direction:column; border-left:1px solid var(--bs-border-color); }
    .tmon-col:first-child { border-left:0; }
    .tmon-col iframe { flex:1 1 auto; width:100%; height:100%; border:0; display:block; }
    .tmon-empty { flex:1 1 auto; display:flex; align-items:center; justify-content:center; color:var(--bs-secondary-color); font-size:1.3rem; }
  </style>
</head>
<body>
  <div class="tmon-head">
    <h1>
      <?php if (!empty($t['sport']) && isset($sport_labels[$t['sport']])): ?>
        <?php if ($t['sport'] === 'cornhole'): ?>
        <img src="<?= url('static/cornhole_icon.svg') ?>" height="46" style="vertical-align:middle" alt="Cornhole">
        <?php else: ?>
        <span class="sport-ic" title="<?= e($sport_labels[$t['sport']]) ?>"><?= $sport_icons[$t['sport']] ?></span>
        <?php endif; ?>
      <?php endif; ?>
      <span><?= e($t['name']) ?></span>
    </h1>
    <span class="tmon-clock"><i class="bi bi-clock"></i> Stand: <span id="tmon-clock-time"><?= date('H:i') ?></span> Uhr</span>
  </div>

  <?php if (empty($comps)): ?>
  <div class="tmon-empty">Keine Bewerbe für den Monitor ausgewählt.</div>
  <?php else: ?>
  <div class="tmon-cols">
    <?php foreach ($comps as $cc): ?>
    <div class="tmon-col">
      <iframe src="<?= url('competition/' . (int)$cc['id'] . '/monitor') ?>?<?= e($embed_params) ?>"
              title="<?= e($cc['name']) ?>" loading="eager"></iframe>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<script>
// Kopf-Uhr live halten (die iframes aktualisieren sich eigenständig).
(function() {
  var el = document.getElementById('tmon-clock-time');
  function tick() {
    if (!el) return;
    var d = new Date();
    el.textContent = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }
  setInterval(tick, 30000);
})();
</script>
</body>
</html>
