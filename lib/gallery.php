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
