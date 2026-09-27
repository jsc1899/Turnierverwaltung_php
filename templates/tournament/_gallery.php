<?php
// Reiter „Galerie“ der Turnierseite (eingebunden aus tournament/show.php).
// Erwartet: $t, $gallery, $can_edit.
$g_data = array_map(fn($g) => [
    'type'    => $g['type'],
    'src'     => url('gallery/' . $g['id'] . '/media'),
    'caption' => (string)($g['caption'] ?? ''),
], $gallery);
?>
  <!-- ── Tab: Galerie ──────────────────────────────────────────────────────── -->
  <div class="tab-pane fade p-3" id="tab-gallery" role="tabpanel">
    <style>
      .gallery-tile { cursor: pointer; }
      .gallery-tile img, .gallery-tile video { object-fit: cover; }
      .gallery-tile:hover, .gallery-tile:focus { outline: 3px solid var(--bs-primary); }
      .gallery-play { pointer-events: none; text-shadow: 0 0 8px rgba(0,0,0,.6); }
      #gallery-upload.dragover { background: var(--bs-primary-bg-subtle); }
      #galleryStage img, #galleryStage video { max-width: 100%; max-height: 80vh; }
    </style>

    <?php if ($can_edit): ?>
    <div id="gallery-upload" class="border border-2 rounded p-3 mb-3 text-center" style="border-style:dashed!important"
         data-url="<?= e(url('tournament/' . $t['id'] . '/gallery/chunk')) ?>"
         data-csrf="<?= e(csrf_token()) ?>"
         data-chunk="<?= GALLERY_CHUNK_BYTES ?>"
         data-max-image="<?= GALLERY_MAX_IMAGE_MB ?>"
         data-max-video="<?= GALLERY_MAX_VIDEO_MB ?>">
      <i class="bi bi-cloud-arrow-up fs-3 text-secondary"></i>
      <div class="mb-2">Fotos und Videos hierher ziehen oder</div>
      <label class="btn btn-primary btn-sm mb-0">
        <i class="bi bi-plus-circle me-1"></i>Dateien auswählen
        <input type="file" id="gallery-files" multiple hidden
               accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,.mov,.m4v">
      </label>
      <div class="form-text">
        Fotos (JPG, PNG, WebP, GIF) bis <?= GALLERY_MAX_IMAGE_MB ?> MB ·
        Videos (MP4, WebM, MOV) bis <?= GALLERY_MAX_VIDEO_MB ?> MB
      </div>
      <ul id="gallery-progress" class="list-unstyled text-start mt-3 mb-0"></ul>
    </div>
    <?php endif; ?>

    <?php if (!$gallery): ?>
    <p class="text-muted mb-0">Noch keine Fotos oder Videos.</p>
    <?php else: ?>
    <div class="row g-2">
      <?php foreach ($gallery as $i => $g): ?>
      <div class="col-6 col-sm-4 col-md-3 col-xl-2">
        <div class="gallery-tile ratio ratio-1x1 rounded overflow-hidden bg-dark" role="button" tabindex="0"
             data-index="<?= $i ?>" aria-label="<?= e($g['caption'] ?: $g['original_name']) ?> anzeigen">
          <?php if ($g['type'] === 'image'): ?>
          <img src="<?= e(url('gallery/' . $g['id'] . '/thumb')) ?>" loading="lazy"
               alt="<?= e($g['caption'] ?: $g['original_name']) ?>">
          <?php else: ?>
          <video src="<?= e(url('gallery/' . $g['id'] . '/media')) ?>#t=0.1" preload="metadata" muted playsinline></video>
          <span class="gallery-play d-flex align-items-center justify-content-center text-white fs-1">
            <i class="bi bi-play-circle-fill"></i>
          </span>
          <?php endif; ?>
        </div>
        <?php if (($g['caption'] ?? '') !== ''): ?>
        <div class="small text-muted text-truncate mt-1" title="<?= e($g['caption']) ?>"><?= e($g['caption']) ?></div>
        <?php endif; ?>
        <?php if ($can_edit): ?>
        <div class="d-flex gap-1 mt-1">
          <button class="btn btn-outline-secondary btn-sm py-0" type="button" title="Beschriftung ändern"
                  data-bs-toggle="collapse" data-bs-target="#gcap-<?= (int)$g['id'] ?>">
            <i class="bi bi-pencil"></i>
          </button>
          <form method="post" action="<?= url('gallery/' . $g['id'] . '/delete') ?>"
                onsubmit="return confirm('Dieses Foto/Video wirklich löschen?')">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm py-0" title="Löschen"><i class="bi bi-trash"></i></button>
          </form>
        </div>
        <form method="post" action="<?= url('gallery/' . $g['id'] . '/caption') ?>"
              class="collapse mt-1" id="gcap-<?= (int)$g['id'] ?>">
          <?= csrf_field() ?>
          <div class="input-group input-group-sm">
            <input type="text" name="caption" maxlength="255" class="form-control"
                   value="<?= e($g['caption'] ?? '') ?>" placeholder="Beschriftung">
            <button class="btn btn-primary" title="Speichern"><i class="bi bi-check-lg"></i></button>
          </div>
        </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div><!-- /tab-gallery -->

  <div class="modal fade" id="galleryModal" tabindex="-1" aria-label="Galerie" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content bg-dark text-white border-0">
        <div class="modal-header border-0 py-2">
          <div class="small text-truncate me-2" id="galleryCaption"></div>
          <a id="galleryOriginal" class="btn btn-sm btn-outline-light ms-auto me-2" target="_blank" rel="noopener"
             title="Original öffnen"><i class="bi bi-box-arrow-up-right"></i></a>
          <button type="button" class="btn-close btn-close-white m-0" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
        <div class="modal-body p-0 text-center d-flex align-items-center justify-content-center"
             id="galleryStage" style="min-height:40vh"></div>
        <div class="modal-footer border-0 justify-content-between py-2" id="galleryNav">
          <button type="button" class="btn btn-outline-light btn-sm" id="galleryPrev"><i class="bi bi-chevron-left"></i> Zurück</button>
          <span class="small text-white-50" id="galleryCount"></span>
          <button type="button" class="btn btn-outline-light btn-sm" id="galleryNext">Weiter <i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
    </div>
  </div>

  <script type="application/json" id="gallery-data"><?= json_encode($g_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    // ── Lightbox ──
    var items = JSON.parse(document.getElementById('gallery-data').textContent || '[]');
    var modalEl = document.getElementById('galleryModal');
    if (items.length) {
      var modal = new bootstrap.Modal(modalEl);
      var stage = document.getElementById('galleryStage');
      var cur = 0;
      function show(i) {
        cur = (i + items.length) % items.length;
        var it = items[cur], el;
        stage.innerHTML = '';
        if (it.type === 'video') {
          el = document.createElement('video');
          el.controls = true; el.autoplay = true; el.playsInline = true;
        } else {
          el = document.createElement('img');
          el.alt = it.caption;
        }
        el.src = it.src;
        stage.appendChild(el);
        document.getElementById('galleryCaption').textContent = it.caption;
        document.getElementById('galleryOriginal').href = it.src;
        document.getElementById('galleryCount').textContent = (cur + 1) + ' / ' + items.length;
        document.getElementById('galleryNav').classList.toggle('d-none', items.length < 2);
      }
      document.querySelectorAll('.gallery-tile').forEach(function (tile) {
        function open() { show(parseInt(tile.dataset.index, 10)); modal.show(); }
        tile.addEventListener('click', open);
        tile.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
        });
      });
      document.getElementById('galleryPrev').addEventListener('click', function () { show(cur - 1); });
      document.getElementById('galleryNext').addEventListener('click', function () { show(cur + 1); });
      document.addEventListener('keydown', function (e) {
        if (!modalEl.classList.contains('show')) return;
        if (e.key === 'ArrowLeft') show(cur - 1);
        if (e.key === 'ArrowRight') show(cur + 1);
      });
      modalEl.addEventListener('hidden.bs.modal', function () { stage.innerHTML = ''; }); // Video stoppen
    }

    // ── Upload (nur Bearbeiter) ──
    var box = document.getElementById('gallery-upload');
    if (!box) return;
    var input = document.getElementById('gallery-files');
    var list = document.getElementById('gallery-progress');
    var CHUNK = parseInt(box.dataset.chunk, 10);
    var MB = 1024 * 1024;
    var busy = false;
    var IMG = ['jpg', 'jpeg', 'png', 'webp', 'gif'], VID = ['mp4', 'm4v', 'webm', 'mov'];

    function uploadId() {
      var a = new Uint8Array(16);
      crypto.getRandomValues(a);
      return Array.from(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }
    function precheck(file) {
      var ext = (file.name.split('.').pop() || '').toLowerCase();
      var max = IMG.indexOf(ext) !== -1 ? box.dataset.maxImage : (VID.indexOf(ext) !== -1 ? box.dataset.maxVideo : null);
      if (max === null) return 'Dateityp nicht erlaubt';
      if (file.size === 0) return 'Leere Datei';
      if (file.size > max * MB) return 'Zu groß (max. ' + max + ' MB)';
      return null;
    }
    function row(file) {
      var li = document.createElement('li');
      li.className = 'mb-2';
      li.innerHTML = '<div class="small d-flex justify-content-between"><span class="gname text-truncate me-2"></span>'
        + '<span class="gstatus text-muted">wartet…</span></div>'
        + '<div class="progress" style="height:6px"><div class="progress-bar" style="width:0%"></div></div>';
      li.querySelector('.gname').textContent = file.name;
      list.appendChild(li);
      return li;
    }
    async function postChunk(fd) {
      for (var attempt = 0; ; attempt++) {
        var res;
        try {
          res = await fetch(box.dataset.url, { method: 'POST', body: fd, credentials: 'same-origin' });
        } catch (e) {                                   // Netzwerkfehler → bis zu 3 Versuche
          if (attempt >= 2) throw new Error('Netzwerkfehler');
          await new Promise(function (r) { setTimeout(r, 1500); });
          continue;
        }
        var data = null;
        try { data = await res.json(); } catch (e) { /* keine JSON-Antwort */ }
        if (!data) throw new Error('Serverfehler (HTTP ' + res.status + ')');
        if (!data.ok) throw new Error(data.error || 'Upload fehlgeschlagen');
        return data;
      }
    }
    async function sendFile(file, li) {
      var bar = li.querySelector('.progress-bar'), st = li.querySelector('.gstatus');
      var total = Math.ceil(file.size / CHUNK), id = uploadId();
      for (var i = 0; i < total; i++) {
        var fd = new FormData();
        fd.append('csrf_token', box.dataset.csrf);
        fd.append('upload_id', id);
        fd.append('index', i);
        fd.append('total', total);
        fd.append('name', file.name);
        fd.append('size', file.size);
        fd.append('chunk', file.slice(i * CHUNK, (i + 1) * CHUNK), 'chunk');
        await postChunk(fd);
        var pct = Math.round((i + 1) / total * 100);
        bar.style.width = pct + '%';
        st.textContent = pct < 100 ? pct + ' %' : 'wird verarbeitet…';
      }
    }
    async function handle(files) {
      if (busy || !files.length) return;
      busy = true;
      var ok = 0, failed = 0;
      for (var file of Array.from(files)) {
        var li = row(file), st = li.querySelector('.gstatus');
        var err = precheck(file);
        if (!err) {
          try { await sendFile(file, li); } catch (e) { err = e.message; }
        }
        if (err) {
          st.textContent = err; st.className = 'gstatus text-danger';
          li.querySelector('.progress-bar').classList.add('bg-danger');
          failed++;
        } else {
          st.textContent = 'fertig'; st.className = 'gstatus text-success';
          ok++;
        }
      }
      busy = false;
      if (ok && !failed) {
        location.hash = '#tab-gallery';
        location.reload();
      } else if (ok) {
        var li = document.createElement('li');
        li.innerHTML = '<button type="button" class="btn btn-sm btn-outline-primary mt-1">Seite neu laden</button>';
        li.querySelector('button').addEventListener('click', function () { location.hash = '#tab-gallery'; location.reload(); });
        list.appendChild(li);
      }
    }
    input.addEventListener('change', function () { handle(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) {
      box.addEventListener(ev, function (e) { e.preventDefault(); box.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      box.addEventListener(ev, function (e) { e.preventDefault(); box.classList.remove('dragover'); });
    });
    box.addEventListener('drop', function (e) { handle(e.dataTransfer.files); });
    window.addEventListener('beforeunload', function (e) { if (busy) { e.preventDefault(); e.returnValue = ''; } });
  });
  </script>
