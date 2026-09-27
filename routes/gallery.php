<?php
// Turnier-Galerie: Upload (Chunks), Beschriftung, Löschen, Auslieferung.
require_once __DIR__ . '/../lib/gallery.php';

function _gallery_item_or_404(int $gid): array {
    $it = db_fetch("SELECT * FROM gallery_item WHERE id=?", [$gid]);
    if (!$it) { http_response_code(404); exit; }
    return $it;
}

// Sichtbarkeit wie das Turnier: öffentlich oder bearbeitbar, sonst 404.
// Rückgabe: true = öffentlich (cachebar), false = nur für Bearbeiter.
function _gallery_require_view(int $tid): bool {
    $t = db_fetch("SELECT is_public FROM tournament WHERE id=?", [$tid]);
    if ($t && (int)$t['is_public'] === 1) return true;
    if ($t && can_edit_tournament($tid)) return false;
    http_response_code(404); exit;
}

function upload_chunk(array $p): void {
    $tid = (int)$p['id'];
    if (!db_fetch("SELECT id FROM tournament WHERE id=?", [$tid])) { http_response_code(404); exit; }
    require_tournament_edit($tid);
    csrf_verify();

    $upload_id = (string)post('upload_id');
    $index     = (int)post('index', -1);
    $total     = (int)post('total', 0);
    $name      = mb_substr(trim((string)post('name')), 0, 255);
    $size      = (int)post('size', 0);
    $err = gallery_validate_chunk_meta($upload_id, $index, $total, $name, $size);
    if ($err !== null) gallery_json(['ok' => false, 'error' => $err], 400);

    $f = $_FILES['chunk'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)$f['size'] > GALLERY_CHUNK_BYTES) {
        gallery_json(['ok' => false, 'error' => 'Upload-Teil fehlerhaft.'], 400);
    }
    if ($index === 0) gallery_cleanup_tmp();
    $tmp = gallery_tmp_dir($tid, $upload_id);
    if (!is_dir($tmp)) mkdir($tmp, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], $tmp . $index . '.part')) {
        gallery_json(['ok' => false, 'error' => 'Upload-Teil konnte nicht gespeichert werden.'], 500);
    }
    if ($index < $total - 1) gallery_json(['ok' => true, 'done' => false]);

    $res = gallery_finalize_upload($tid, $upload_id, $total, $name, current_user()['id'] ?? null);
    gallery_json($res, $res['ok'] ? 200 : 400);
}

function caption(array $p): void {
    $it = _gallery_item_or_404((int)$p['gid']);
    require_tournament_edit((int)$it['tournament_id']);
    csrf_verify();
    $cap = mb_substr(trim((string)post('caption')), 0, 255);
    db_execute("UPDATE gallery_item SET caption=? WHERE id=?", [$cap !== '' ? $cap : null, (int)$it['id']]);
    redirect('tournament/' . (int)$it['tournament_id'] . '#tab-gallery');
}

function delete(array $p): void {
    $it = _gallery_item_or_404((int)$p['gid']);
    require_tournament_edit((int)$it['tournament_id']);
    csrf_verify();
    gallery_delete_item($it);
    flash('info', 'Medium gelöscht.');
    redirect('tournament/' . (int)$it['tournament_id'] . '#tab-gallery');
}

function media(array $p): void {
    $it = _gallery_item_or_404((int)$p['gid']);
    $public = _gallery_require_view((int)$it['tournament_id']);
    gallery_stream(GALLERY_DIR . (int)$it['tournament_id'] . '/' . $it['filename'], $it['mime'], $public);
}

function thumb(array $p): void {
    $it = _gallery_item_or_404((int)$p['gid']);
    $public = _gallery_require_view((int)$it['tournament_id']);
    if (!$it['thumb']) { http_response_code(404); exit; }
    gallery_stream(GALLERY_DIR . (int)$it['tournament_id'] . '/' . $it['thumb'], 'image/jpeg', $public);
}
