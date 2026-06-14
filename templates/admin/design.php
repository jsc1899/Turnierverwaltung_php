<?php
$themes = [
    'default'  => [
        'name'    => 'Standard',
        'desc'    => 'Bootstrap-Standard — schlicht, vertraut, hell.',
        'primary' => '#0d6efd',
        'swatches'=> ['#0d6efd', '#6c757d', '#ffffff'],
    ],
    'dunkel'   => [
        'name'    => 'Dunkel',
        'desc'    => 'Dunkler Modus — schont die Augen bei wenig Licht.',
        'primary' => '#212529',
        'swatches'=> ['#0d6efd', '#6c757d', '#343a40'],
    ],
    'elegant'  => [
        'name'    => 'Elegant',
        'desc'    => 'Waldgrün und Amber mit eleganten Serifenschriften.',
        'primary' => '#1b3c2d',
        'swatches'=> ['#1b3c2d', '#bf7c22', '#f0ece2'],
    ],
    'modern'   => [
        'name'    => 'Modern',
        'desc'    => 'Klares Indigo-Design mit modernem Schriftbild.',
        'primary' => '#4f46e5',
        'swatches'=> ['#4f46e5', '#a5b4fc', '#ede9fe'],
    ],
    'klassisch'=> [
        'name'    => 'Klassisch',
        'desc'    => 'Warme Erdfarben und traditionelle Serifenschriften.',
        'primary' => '#5c4a1e',
        'swatches'=> ['#5c4a1e', '#8b6914', '#f5f0e8'],
    ],
];

ob_start(); ?>
<h2 class="mb-1"><i class="bi bi-palette me-2"></i>Design</h2>
<p class="text-muted mb-4">Das gewählte Design gilt für alle Benutzer der App.</p>

<div class="row g-4">
  <?php foreach ($themes as $key => $t): ?>
  <div class="col-sm-6 col-xl-4">
    <div class="card h-100<?= $active === $key ? ' border-primary border-2 shadow' : '' ?>">
      <div style="height:72px;background:<?= e($t['primary']) ?>;border-radius:11px 11px 0 0;
                  display:flex;align-items:flex-end;padding:8px 14px;gap:8px;">
        <?php foreach ($t['swatches'] as $sw): ?>
        <span style="width:22px;height:22px;border-radius:50%;background:<?= e($sw) ?>;
                     border:2px solid rgba(255,255,255,.55);flex-shrink:0;"></span>
        <?php endforeach; ?>
      </div>
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <h5 class="card-title mb-0"><?= e($t['name']) ?></h5>
          <?php if ($active === $key): ?>
          <span class="badge bg-primary">Aktiv</span>
          <?php endif; ?>
        </div>
        <p class="card-text text-muted small flex-grow-1"><?= e($t['desc']) ?></p>
        <?php if ($active !== $key): ?>
        <form method="post" action="<?= url('admin/design') ?>" class="mt-2">
          <?= csrf_field() ?>
          <input type="hidden" name="theme" value="<?= e($key) ?>">
          <button type="submit" class="btn btn-outline-primary btn-sm w-100">Aktivieren</button>
        </form>
        <?php else: ?>
        <div class="mt-2">
          <button class="btn btn-primary btn-sm w-100" disabled>Aktuell aktiv</button>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
