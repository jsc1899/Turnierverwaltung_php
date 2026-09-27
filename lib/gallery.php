<?php
// Turnier-Galerie: Validierung, Speicherung, Bildverarbeitung, Auslieferung.

// Endung → Medientyp (Vorprüfung vor dem Upload; maßgeblich ist später finfo)
function gallery_ext_type(string $name): ?string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return match ($ext) {
        'jpg', 'jpeg', 'png', 'webp', 'gif' => 'image',
        'mp4', 'm4v', 'webm', 'mov'         => 'video',
        default                             => null,
    };
}

// Realer MIME-Typ (finfo) → Typ, gespeicherte Endung und ausgelieferter MIME
function gallery_classify_mime(string $mime): ?array {
    return match ($mime) {
        'image/jpeg'      => ['type' => 'image', 'ext' => 'jpg',  'mime' => 'image/jpeg'],
        'image/png'       => ['type' => 'image', 'ext' => 'png',  'mime' => 'image/png'],
        'image/webp'      => ['type' => 'image', 'ext' => 'webp', 'mime' => 'image/webp'],
        'image/gif'       => ['type' => 'image', 'ext' => 'gif',  'mime' => 'image/gif'],
        'video/mp4'       => ['type' => 'video', 'ext' => 'mp4',  'mime' => 'video/mp4'],
        'video/x-m4v'     => ['type' => 'video', 'ext' => 'mp4',  'mime' => 'video/mp4'],
        'video/webm'      => ['type' => 'video', 'ext' => 'webm', 'mime' => 'video/webm'],
        'video/quicktime' => ['type' => 'video', 'ext' => 'mov',  'mime' => 'video/quicktime'],
        default           => null,
    };
}

function gallery_max_bytes(string $type): int {
    return ($type === 'video' ? GALLERY_MAX_VIDEO_MB : GALLERY_MAX_IMAGE_MB) * 1024 * 1024;
}

const GALLERY_TYPES_HINT = 'Dateityp nicht erlaubt (nur JPG, PNG, WebP, GIF, MP4, WebM, MOV).';

// Prüft die Metadaten eines Upload-Teils. Liefert eine deutsche Fehlermeldung oder null.
function gallery_validate_chunk_meta(string $upload_id, int $index, int $total, string $name, int $size): ?string {
    if (!preg_match('/^[a-f0-9]{32}$/', $upload_id)) return 'Ungültige Upload-ID.';
    $type = gallery_ext_type($name);
    if ($type === null) return GALLERY_TYPES_HINT;
    if ($size <= 0) return 'Leere Datei.';
    if ($size > gallery_max_bytes($type)) {
        $mb = $type === 'video' ? GALLERY_MAX_VIDEO_MB : GALLERY_MAX_IMAGE_MB;
        return 'Datei zu groß (max. ' . $mb . ' MB).';
    }
    if ($total !== (int)ceil($size / GALLERY_CHUNK_BYTES)) return 'Ungültige Teilanzahl.';
    if ($index < 0 || $index >= $total) return 'Ungültiger Teil-Index.';
    return null;
}

// HTTP-Range-Header (nur Einzelbereich). null = ganze Datei, false = nicht erfüllbar (416).
function gallery_parse_range(string $header, int $size): array|false|null {
    $header = trim($header);
    if ($header === '' || !preg_match('/^bytes=(\d*)-(\d*)$/', $header, $m)) return null;
    if ($m[1] === '' && $m[2] === '') return false;
    if ($m[1] === '') {
        $n = (int)$m[2];
        if ($n <= 0) return false;
        return [max(0, $size - $n), $size - 1];
    }
    $start = (int)$m[1];
    $end   = $m[2] === '' ? $size - 1 : min((int)$m[2], $size - 1);
    if ($start >= $size || $start > $end) return false;
    return [$start, $end];
}

function gallery_json(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
// ── Speicherorte ───────────────────────────────────────────────────────────────

// Wurzelverzeichnis der Galerie; sperrt Direktzugriff (uploads/* ist nicht versioniert,
// daher legt der Code die .htaccess selbst an).
function gallery_root(): string {
    $root = UPLOAD_DIR . 'gallery/';
    if (!is_dir($root)) mkdir($root, 0755, true);
    $ht = $root . '.htaccess';
    if (!is_file($ht)) file_put_contents($ht, "Require all denied\n");
    return $root;
}

function gallery_dir(int $tid): string {
    $dir = gallery_root() . $tid . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function gallery_tmp_dir(int $tid, string $upload_id): string {
    return gallery_root() . '_tmp/' . $tid . '_' . $upload_id . '/';
}

function gallery_rrmdir(string $dir): void {
    $dir = rtrim($dir, '/\\');
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? gallery_rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}

// Abgebrochene Uploads (Temp-Verzeichnisse älter als $max_age Sekunden) entfernen
function gallery_cleanup_tmp(int $max_age = 86400): void {
    $base = gallery_root() . '_tmp/';
    if (!is_dir($base)) return;
    foreach (scandir($base) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $base . $f;
        if (is_dir($p) && filemtime($p) < time() - $max_age) gallery_rrmdir($p);
    }
}

// ── Bildverarbeitung (GD) ──────────────────────────────────────────────────────

function gallery_apply_orientation(\GdImage $img, int $o): \GdImage {
    switch ($o) {
        case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
        case 3: $img = imagerotate($img, 180, 0); break;
        case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
        case 5: $img = imagerotate($img, -90, 0); imageflip($img, IMG_FLIP_HORIZONTAL); break;
        case 6: $img = imagerotate($img, -90, 0); break;
        case 7: $img = imagerotate($img, 90, 0); imageflip($img, IMG_FLIP_HORIZONTAL); break;
        case 8: $img = imagerotate($img, 90, 0); break;
    }
    return $img;
}

// Auf lange Kante $max skalieren (nie vergrößern). $flatten = auf weißen Grund (für JPEG-Vorschau).
function gallery_resize(\GdImage $src, int $max, bool $flatten): \GdImage {
    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($flatten) {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    } else {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

// Foto normalisieren (Orientierung, max. Größe, neu kodieren → Metadaten weg) und Vorschau erzeugen.
function gallery_process_image(string $path, string $mime, string $thumb_path): void {
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png'  => @imagecreatefrompng($path),
        'image/webp' => @imagecreatefromwebp($path),
        'image/gif'  => @imagecreatefromgif($path),
        default      => false,
    };
    if (!$src) throw new \RuntimeException('Bild konnte nicht gelesen werden.');
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $src  = gallery_apply_orientation($src, (int)($exif['Orientation'] ?? 1));
    }
    // GIF unverändert lassen (Animation), alle anderen neu kodieren
    if ($mime !== 'image/gif') {
        $out = gallery_resize($src, GALLERY_MAX_EDGE, false);
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($out, $path, 85),
            'image/png'  => imagepng($out, $path, 6),
            'image/webp' => imagewebp($out, $path, 85),
        };
        if (!$ok) throw new \RuntimeException('Bild konnte nicht gespeichert werden.');
    }
    if (!imagejpeg(gallery_resize($src, GALLERY_THUMB_EDGE, true), $thumb_path, 80)) {
        throw new \RuntimeException('Vorschaubild konnte nicht erstellt werden.');
    }
}

// ── Upload abschließen ─────────────────────────────────────────────────────────

// Setzt die Teile zusammen, sobald alle vorhanden sind, prüft Typ/Größe, verarbeitet
// Fotos und legt den DB-Eintrag an. Das Temp-Verzeichnis wird in jedem Fall entfernt,
// sobald ein Abschluss versucht wurde.
function gallery_finalize_upload(int $tid, string $upload_id, int $total, string $name, ?int $uid): array {
    $tmp = gallery_tmp_dir($tid, $upload_id);
    for ($i = 0; $i < $total; $i++) {
        if (!is_file($tmp . $i . '.part')) return ['ok' => true, 'done' => false];
    }
    @set_time_limit(300);
    $files = [];
    try {
        $assembled = $tmp . 'assembled';
        $out = fopen($assembled, 'wb');
        for ($i = 0; $i < $total; $i++) {
            $in = fopen($tmp . $i . '.part', 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        $size = (int)filesize($assembled);
        if ($size <= 0) return ['ok' => false, 'error' => 'Leere Datei.'];
        $kind = gallery_classify_mime((string)(new \finfo(FILEINFO_MIME_TYPE))->file($assembled));
        if ($kind === null) return ['ok' => false, 'error' => GALLERY_TYPES_HINT];
        if ($size > gallery_max_bytes($kind['type'])) {
            $mb = $kind['type'] === 'video' ? GALLERY_MAX_VIDEO_MB : GALLERY_MAX_IMAGE_MB;
            return ['ok' => false, 'error' => 'Datei zu groß (max. ' . $mb . ' MB).'];
        }

        $dir  = gallery_dir($tid);
        $base = bin2hex(random_bytes(16));
        $filename = $base . '.' . $kind['ext'];
        $thumb = null;
        if ($kind['type'] === 'image') {
            ini_set('memory_limit', '512M');   // große Handyfotos (24 MP ≈ 100 MB in GD)
            $thumb = $base . '_t.jpg';
            $files[] = $dir . $thumb;
            gallery_process_image($assembled, $kind['mime'], $dir . $thumb);
        }
        $files[] = $dir . $filename;
        if (!rename($assembled, $dir . $filename)) throw new \RuntimeException('Datei konnte nicht gespeichert werden.');

        $id = (int)db_insert(
            "INSERT INTO gallery_item (tournament_id, type, filename, thumb, mime, original_name, size, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?)",
            [$tid, $kind['type'], $filename, $thumb, $kind['mime'], mb_substr($name, 0, 255),
             (int)filesize($dir . $filename), $uid]
        );
        $files = [];   // erfolgreich → nichts aufräumen
        return ['ok' => true, 'done' => true, 'id' => $id];
    } catch (\RuntimeException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (\Throwable $e) {
        error_log('Galerie-Upload fehlgeschlagen: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Upload fehlgeschlagen.'];
    } finally {
        foreach ($files as $f) @unlink($f);
        gallery_rrmdir($tmp);
    }
}

// ── Lesen / Löschen ────────────────────────────────────────────────────────────

function gallery_items(int $tid): array {
    return db_fetchall("SELECT * FROM gallery_item WHERE tournament_id=? ORDER BY created_at DESC, id DESC", [$tid]);
}

function gallery_delete_item(array $item): void {
    $dir = UPLOAD_DIR . 'gallery/' . (int)$item['tournament_id'] . '/';
    foreach ([$item['filename'], $item['thumb']] as $f) {
        if ($f && preg_match('/^[a-f0-9]{32}(_t)?\.[a-z0-9]+$/', $f)) @unlink($dir . $f);
    }
    db_execute("DELETE FROM gallery_item WHERE id=?", [(int)$item['id']]);
}

function gallery_delete_tournament_files(int $tid): void {
    if ($tid <= 0) return;
    gallery_rrmdir(UPLOAD_DIR . 'gallery/' . $tid);
}
