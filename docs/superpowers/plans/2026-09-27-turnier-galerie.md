# Turnier-Galerie Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pro Turnier eine Galerie mit Fotos und Videos (Chunk-Upload durch Bearbeiter, öffentliche Anzeige als Reiter auf der Turnierseite).

**Architecture:** Neue Tabelle `gallery_item`, Dateien unter `uploads/gallery/{tid}/` (Direktzugriff gesperrt). Fachlogik in `lib/gallery.php` (Validierung, Zusammensetzen, GD-Bildverarbeitung, Range-Streaming), HTTP-Handler in `routes/gallery.php`, UI als Partial `templates/tournament/_gallery.php` im Reiter „Galerie“ von `tournament/show.php`.

**Tech Stack:** PHP 8.3, MariaDB, GD + EXIF + fileinfo, Bootstrap 5.3, Vanilla-JS (`fetch`, `Blob.slice`).

**Spec:** `docs/superpowers/specs/2026-09-27-turnier-galerie-design.md`

## Global Constraints

- Upload/Beschriften/Löschen nur mit `require_tournament_edit($tid)` (Admin oder zugeordneter Editor).
- Sichtbarkeit: `tournament.is_public = 1` oder `can_edit_tournament($tid)`, sonst 404.
- Fotos: `image/jpeg`, `image/png`, `image/webp`, `image/gif` — max. `GALLERY_MAX_IMAGE_MB` = 25.
- Videos: `video/mp4`, `video/webm`, `video/quicktime` (+ `video/x-m4v`) — max. `GALLERY_MAX_VIDEO_MB` = 500.
- Chunk-Größe `GALLERY_CHUNK_BYTES` = 1048576 (1 MB).
- Fotos: EXIF-Orientierung anwenden, lange Kante max. 2560 px, immer neu kodieren (GIF ausgenommen), Vorschaubild 400 px JPEG.
- Alle POSTs mit `csrf_verify()`. Alle SQL-Statements parametrisiert. Ausgabe mit `e()`.
- Schema: `CREATE TABLE IF NOT EXISTS` im `init_db()`-Block (neue Tabelle, daher kein ALTER).
- Deutsche UI-Texte; Reihenfolge der Medien: neueste zuerst.
- Lokal testen mit `$env:MAIL_HOST='127.0.0.1'; $env:MAIL_PORT='1'` (sonst echte Mails).

## Review Focus

1. **Großes Handyfoto (24 MP JPEG, Hochformat)** → darf nicht am `memory_limit` (128M) scheitern und muss aufrecht angezeigt werden — Test in Task 3 (Orientierung 6, `memory_limit` wird in `gallery_finalize_upload` angehoben).
2. **Nicht öffentliches Turnier, Gast ruft `/gallery/{gid}/media` oder `/uploads/gallery/...` direkt auf** → 404, kein Inhalt — Test in Task 1 und Task 4.
3. **Video-Seeking (Range-Request)** → `206` mit korrektem `Content-Range`, ungültiger Bereich → `416` — Tests in Task 2 (Parser) und Task 4 (HTTP).
4. **Umbenannte Datei (z.B. `.exe` → `.jpg`) oder leere Datei** → wird abgelehnt, keine Datei/DB-Zeile bleibt zurück — Test in Task 3.
5. **Abgebrochener Upload / Turnier gelöscht** → Temp-Teile werden aufgeräumt, Turnier-Löschung entfernt `uploads/gallery/{tid}/` — Tests in Task 3 und Task 5.

---

## File Structure

| Datei | Aktion | Verantwortung |
|---|---|---|
| `config.php` | Modify | Konstanten `GALLERY_*` |
| `db.php` | Modify | Tabelle `gallery_item` |
| `index.php` | Modify | `/uploads/gallery/` sperren, Routen |
| `router.php` | Modify | `/uploads/gallery/` nicht statisch ausliefern |
| `lib/gallery.php` | Create | Fachlogik (Validierung, Speicher, Bild, Streaming) |
| `routes/gallery.php` | Create | Handler `upload_chunk`, `caption`, `delete`, `media`, `thumb` |
| `helpers.php` | Modify | Audit: Chunk-Filter, Target, Bereichslabel |
| `routes/tournament.php` | Modify | `show()` lädt Medien, `delete()` räumt Dateien |
| `templates/tournament/show.php` | Modify | Reiter-Button + Partial einbinden |
| `templates/tournament/_gallery.php` | Create | Reiter-Inhalt, Lightbox, Upload-JS |
| `tests/gallery_test.php` | Create | CLI-Tests der Lib (`php tests/gallery_test.php`) |
| `tests/gallery_e2e.sh` | Create | HTTP-End-to-End-Test (curl, Git-Bash) |
| `CLAUDE.md` | Modify | Doku |

Vorbedingung für alle Tests: MariaDB läuft (`Start-Process "C:\Program Files\MariaDB 12.3\bin\mysqld.exe" -WindowStyle Hidden`); für Task 4/6 zusätzlich der PHP-Server (siehe CLAUDE.md, mit unerreichbarem `MAIL_HOST`).

---

### Task 1: Konfiguration, Schema, Speicherschutz

**Files:**
- Modify: `config.php` (nach `define('UPLOAD_DIR', …)`, Zeile ~52)
- Modify: `db.php` (im großen `CREATE TABLE`-Block, direkt nach `tournament_editor`, Zeile ~207)
- Modify: `index.php` (Anfang des `/uploads/`-Zweigs, Zeile ~57)
- Modify: `router.php`

**Interfaces:**
- Produces: Konstanten `GALLERY_MAX_IMAGE_MB` (int), `GALLERY_MAX_VIDEO_MB` (int), `GALLERY_CHUNK_BYTES` (int), `GALLERY_MAX_EDGE` (int), `GALLERY_THUMB_EDGE` (int); Tabelle `gallery_item`.

- [ ] **Step 1: Failing check** — Tabelle fehlt, Direktzugriff wird noch ausgeliefert:

```bash
"/c/Program Files/MariaDB 12.3/bin/mysql.exe" -u root turnierverwaltung -e "SHOW TABLES LIKE 'gallery_item'"
mkdir -p /c/Users/juerg/claude/Turnier/uploads/gallery/1 && cp /c/Users/juerg/claude/Turnier/static/cornhole.png /c/Users/juerg/claude/Turnier/uploads/gallery/1/probe.png
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/uploads/gallery/1/probe.png
```
Expected: leere Tabellenliste; HTTP `200` (noch ungeschützt).

- [ ] **Step 2: Konstanten in `config.php`** (nach `UPLOAD_DIR`):

```php
// Galerie (Fotos/Videos je Turnier) — Größen in MB, per ENV überschreibbar
define('GALLERY_MAX_IMAGE_MB', (int)(getenv('GALLERY_MAX_IMAGE_MB') ?: 25));
define('GALLERY_MAX_VIDEO_MB', (int)(getenv('GALLERY_MAX_VIDEO_MB') ?: 500));
define('GALLERY_CHUNK_BYTES',  1024 * 1024);   // 1 MB je Upload-Teil (unter üblichem upload_max_filesize)
define('GALLERY_MAX_EDGE',     2560);          // lange Kante gespeicherter Fotos (px)
define('GALLERY_THUMB_EDGE',   400);           // lange Kante der Vorschaubilder (px)
```

- [ ] **Step 3: Tabelle in `db.php`** (im `$pdo->exec("…")`-Block nach `tournament_editor`):

```sql
        CREATE TABLE IF NOT EXISTS gallery_item (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            tournament_id INT NOT NULL,
            type          VARCHAR(8)   NOT NULL,
            filename      VARCHAR(64)  NOT NULL,
            thumb         VARCHAR(64)  NULL DEFAULT NULL,
            mime          VARCHAR(64)  NOT NULL,
            original_name VARCHAR(255) NOT NULL DEFAULT '',
            caption       VARCHAR(255) NULL DEFAULT NULL,
            size          BIGINT       NOT NULL DEFAULT 0,
            uploaded_by   INT NULL DEFAULT NULL,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_gallery_tournament (tournament_id, created_at),
            FOREIGN KEY (tournament_id) REFERENCES tournament(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 4: `/uploads/gallery/` in `index.php` sperren** — als erste Zeile im Block `if (str_starts_with($uri, '/uploads/')) {`:

```php
    // Galerie-Dateien nur über /gallery/{id}/media (mit Sichtbarkeitsprüfung)
    if (str_starts_with($uri, '/uploads/gallery/') || $uri === '/uploads/gallery') {
        http_response_code(404); exit;
    }
```

- [ ] **Step 5: `router.php`** — vor `if ($path !== '/' && is_file($file))` einfügen:

```php
// Galerie-Dateien nie statisch ausliefern (Zugriffsprüfung in index.php)
if (str_starts_with(strtolower($path), '/uploads/gallery')) {
    require __DIR__ . '/index.php';
    return true;
}
```

- [ ] **Step 6: Verify** — Seite einmal aufrufen (legt Tabelle an), dann:

```bash
curl -s -o /dev/null http://localhost:8080/
"/c/Program Files/MariaDB 12.3/bin/mysql.exe" -u root turnierverwaltung -e "SHOW TABLES LIKE 'gallery_item'"
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/uploads/gallery/1/probe.png
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/UPLOADS/gallery/1/probe.png
rm -rf /c/Users/juerg/claude/Turnier/uploads/gallery/1
```
Expected: Tabelle `gallery_item` vorhanden; beide HTTP-Codes `404`. Bestehende Banner unter `/uploads/*.png` liefern weiterhin `200`.

- [ ] **Step 7: Commit**

```bash
git add config.php db.php index.php router.php
git commit -m "feat(galerie): Konfiguration, Tabelle gallery_item, Direktzugriff gesperrt"
```

---

### Task 2: Basis-Helfer in `lib/gallery.php` (Validierung, Range)

**Files:**
- Create: `lib/gallery.php`
- Create: `tests/gallery_test.php`

**Interfaces:**
- Consumes: Konstanten aus Task 1.
- Produces:
  - `gallery_ext_type(string $name): ?string` → `'image'|'video'|null` (nach Dateiendung)
  - `gallery_classify_mime(string $mime): ?array` → `['type'=>'image'|'video','ext'=>string,'mime'=>string]|null`
  - `gallery_max_bytes(string $type): int`
  - `gallery_validate_chunk_meta(string $upload_id, int $index, int $total, string $name, int $size): ?string` → Fehlermeldung oder `null`
  - `gallery_parse_range(string $header, int $size): array|false|null` → `[start,end]`, `false` = 416, `null` = ganze Datei
  - `gallery_json(array $data, int $code = 200): never`

- [ ] **Step 1: Failing test** `tests/gallery_test.php`:

```php
<?php
// CLI-Tests der Galerie-Lib: php tests/gallery_test.php  (MariaDB muss laufen)
declare(strict_types=1);
chdir(__DIR__ . '/..');
require 'config.php'; require 'db.php'; require 'helpers.php'; require 'auth.php';
require 'lib/gallery.php';

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    echo ($ok ? "  ok   " : "  FAIL ") . $label . "\n";
    if (!$ok) $fails++;
}

echo "gallery_ext_type\n";
check('jpg → image',  gallery_ext_type('Foto.JPG') === 'image');
check('mov → video',  gallery_ext_type('clip.mov') === 'video');
check('exe → null',   gallery_ext_type('x.exe') === null);
check('ohne Endung',  gallery_ext_type('README') === null);

echo "gallery_classify_mime\n";
check('jpeg',        gallery_classify_mime('image/jpeg') === ['type'=>'image','ext'=>'jpg','mime'=>'image/jpeg']);
check('quicktime',   gallery_classify_mime('video/quicktime') === ['type'=>'video','ext'=>'mov','mime'=>'video/quicktime']);
check('m4v → mp4',   gallery_classify_mime('video/x-m4v') === ['type'=>'video','ext'=>'mp4','mime'=>'video/mp4']);
check('php → null',  gallery_classify_mime('text/x-php') === null);

echo "gallery_validate_chunk_meta\n";
$id = str_repeat('a', 32); $mb = GALLERY_CHUNK_BYTES;
check('gültig',            gallery_validate_chunk_meta($id, 0, 3, 'a.mp4', 2 * $mb + 5) === null);
check('ID ungültig',       gallery_validate_chunk_meta('../x', 0, 1, 'a.jpg', 10) !== null);
check('Endung verboten',   gallery_validate_chunk_meta($id, 0, 1, 'a.php', 10) !== null);
check('leer',              gallery_validate_chunk_meta($id, 0, 1, 'a.jpg', 0) === 'Leere Datei.');
check('Foto zu groß',      gallery_validate_chunk_meta($id, 0, 26, 'a.jpg', 26 * 1024 * 1024) !== null);
check('falsche Teilzahl',  gallery_validate_chunk_meta($id, 0, 2, 'a.mp4', 10) !== null);
check('Index außerhalb',   gallery_validate_chunk_meta($id, 3, 3, 'a.mp4', 2 * $mb + 5) !== null);

echo "gallery_parse_range\n";
check('kein Header',  gallery_parse_range('', 1000) === null);
check('a-b',          gallery_parse_range('bytes=0-99', 1000) === [0, 99]);
check('a-',           gallery_parse_range('bytes=500-', 1000) === [500, 999]);
check('-n',           gallery_parse_range('bytes=-100', 1000) === [900, 999]);
check('Ende gekappt', gallery_parse_range('bytes=900-5000', 1000) === [900, 999]);
check('start>=size',  gallery_parse_range('bytes=1000-', 1000) === false);
check('start>end',    gallery_parse_range('bytes=50-10', 1000) === false);
check('multi → ganz', gallery_parse_range('bytes=0-1,5-9', 1000) === null);

echo $fails ? "\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
```

- [ ] **Step 2: Run** `php tests/gallery_test.php` — Expected: FAIL (`lib/gallery.php` fehlt).

- [ ] **Step 3: Implement** `lib/gallery.php`:

```php
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
```

- [ ] **Step 4: Run** `php tests/gallery_test.php` — Expected: `Alle Tests ok`.

- [ ] **Step 5: Commit**

```bash
git add lib/gallery.php tests/gallery_test.php
git commit -m "feat(galerie): Validierungs- und Range-Helfer mit Tests"
```

---

### Task 3: Speicherung, Bildverarbeitung, Zusammensetzen, Löschen

**Files:**
- Modify: `lib/gallery.php` (anhängen)
- Modify: `tests/gallery_test.php` (vor der Abschlusszeile `echo $fails ? …` anhängen)

**Interfaces:**
- Consumes: Task 2.
- Produces:
  - `gallery_root(): string` (mit `/` am Ende, legt Verzeichnis + `.htaccess` an)
  - `gallery_dir(int $tid): string` (mit `/`, wird angelegt)
  - `gallery_tmp_dir(int $tid, string $upload_id): string` (mit `/`, wird **nicht** angelegt)
  - `gallery_rrmdir(string $dir): void`
  - `gallery_cleanup_tmp(int $max_age = 86400): void`
  - `gallery_process_image(string $path, string $mime, string $thumb_path): void` (wirft `RuntimeException`)
  - `gallery_finalize_upload(int $tid, string $upload_id, int $total, string $name, ?int $uid): array` → `['ok'=>true,'done'=>false]` | `['ok'=>true,'done'=>true,'id'=>int]` | `['ok'=>false,'error'=>string]`
  - `gallery_items(int $tid): array` (Zeilen von `gallery_item`, neueste zuerst)
  - `gallery_delete_item(array $item): void`
  - `gallery_delete_tournament_files(int $tid): void`

- [ ] **Step 1: Failing tests** — an `tests/gallery_test.php` anhängen (vor `echo $fails ? …`):

```php
echo "Upload-Finalisierung (DB + Dateisystem)\n";
$tid = (int)db_insert("INSERT INTO tournament (name) VALUES ('Galerie-Test')");

// Hilfsfunktion: Datei in Teile zerlegen wie der Browser
function put_parts(int $tid, string $uid, string $data): int {
    $dir = gallery_tmp_dir($tid, $uid);
    @mkdir($dir, 0755, true);
    $parts = str_split($data, GALLERY_CHUNK_BYTES);
    foreach ($parts as $i => $p) file_put_contents($dir . $i . '.part', $p);
    return count($parts);
}

// 1) Hochformat-JPEG mit EXIF-Orientierung 6, größer als GALLERY_MAX_EDGE
$img = imagecreatetruecolor(4000, 3000);
imagefilledrectangle($img, 0, 0, 3999, 1499, imagecolorallocate($img, 255, 0, 0)); // obere Hälfte rot
ob_start(); imagejpeg($img, null, 90); $jpeg = ob_get_clean();
// APP1/EXIF-Segment mit Orientation=6 (Big Endian) direkt nach SOI einfügen
$tiff = "MM\x00\x2A\x00\x00\x00\x08" . "\x00\x01" . "\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00" . "\x00\x00\x00\x00";
$app1 = "Exif\x00\x00" . $tiff;
$jpeg = "\xFF\xD8\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
$uid1 = str_repeat('b', 32);
$n = put_parts($tid, $uid1, $jpeg);
$r = gallery_finalize_upload($tid, $uid1, $n, 'handy.jpg', null);
check('JPEG gespeichert', ($r['ok'] ?? false) && ($r['done'] ?? false) && !empty($r['id']));
$row = db_fetch("SELECT * FROM gallery_item WHERE id=?", [$r['id'] ?? 0]);
[$w, $h] = getimagesize(gallery_dir($tid) . $row['filename']);
check('gedreht (Hochformat) und verkleinert', $w === 1920 && $h === 2560);
check('Vorschaubild existiert', is_file(gallery_dir($tid) . $row['thumb']));
check('EXIF entfernt', empty(@exif_read_data(gallery_dir($tid) . $row['filename'])['Orientation']));
check('Temp-Verzeichnis entfernt', !is_dir(gallery_tmp_dir($tid, $uid1)));

// 2) Fehlende Teile → noch nicht fertig
$uid2 = str_repeat('c', 32);
$dir2 = gallery_tmp_dir($tid, $uid2); @mkdir($dir2, 0755, true);
file_put_contents($dir2 . '0.part', 'x');
$r2 = gallery_finalize_upload($tid, $uid2, 2, 'clip.mp4', null);
check('unvollständig → done=false', $r2 === ['ok' => true, 'done' => false]);

// 3) Getarnte Datei (.jpg mit PHP-Inhalt) → abgelehnt, nichts bleibt zurück
$uid3 = str_repeat('d', 32);
$n3 = put_parts($tid, $uid3, "<?php echo 'x';");
$before = count(glob(gallery_dir($tid) . '*'));
$r3 = gallery_finalize_upload($tid, $uid3, $n3, 'boese.jpg', null);
check('falscher Inhalt abgelehnt', ($r3['ok'] ?? true) === false);
check('keine Datei angelegt', count(glob(gallery_dir($tid) . '*')) === $before);
check('Temp nach Fehler entfernt', !is_dir(gallery_tmp_dir($tid, $uid3)));

// 4) Löschen eines Mediums
gallery_delete_item($row);
check('Datei gelöscht', !is_file(gallery_dir($tid) . $row['filename']));
check('DB-Zeile gelöscht', db_fetch("SELECT id FROM gallery_item WHERE id=?", [$row['id']]) === null);

// 5) Aufräumen alter Temp-Uploads
touch($dir2, time() - 90000);
gallery_cleanup_tmp();
check('alte Temp-Uploads entfernt', !is_dir($dir2));

// 6) .htaccess-Schutz vorhanden
check('.htaccess angelegt', str_contains((string)@file_get_contents(gallery_root() . '.htaccess'), 'Require all denied'));

// 7) Turnier-Verzeichnis löschen
gallery_delete_tournament_files($tid);
check('Turnierverzeichnis entfernt', !is_dir(UPLOAD_DIR . 'gallery/' . $tid));
db_execute("DELETE FROM tournament WHERE id=?", [$tid]);
```

- [ ] **Step 2: Run** `php tests/gallery_test.php` — Expected: FAIL (`gallery_tmp_dir` undefiniert).

- [ ] **Step 3: Implement** — an `lib/gallery.php` anhängen:

```php
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
```

Hinweis: `return` innerhalb von `try` führt den `finally`-Block aus → Temp-Verzeichnis und evtl. halb geschriebene Dateien werden auch bei Validierungsfehlern entfernt.

- [ ] **Step 4: Run** `php tests/gallery_test.php` — Expected: `Alle Tests ok`.

- [ ] **Step 5: Commit**

```bash
git add lib/gallery.php tests/gallery_test.php
git commit -m "feat(galerie): Upload-Abschluss, Bildverarbeitung, Löschen und Aufräumen"
```

---

### Task 4: HTTP-Handler, Routen, Streaming, Audit-Log

**Files:**
- Modify: `lib/gallery.php` (Streaming anhängen)
- Create: `routes/gallery.php`
- Modify: `index.php` (Routen-Tabelle, nach den Turnier-Routen Zeile ~109)
- Modify: `helpers.php` (`audit_log()` Zeile ~108, `_audit_resolve_target()` vor `case 'pdf':`, `audit_area_label()`)
- Create: `tests/gallery_e2e.sh`

**Interfaces:**
- Consumes: Task 2/3.
- Produces:
  - `gallery_stream(string $path, string $mime, bool $public): never`
  - Routen: `POST /tournament/{id}/gallery/chunk`, `POST /gallery/{gid}/caption`, `POST /gallery/{gid}/delete`, `GET /gallery/{gid}/media`, `GET /gallery/{gid}/thumb`
  - JSON-Antwort des Chunk-Endpunkts: siehe `gallery_finalize_upload()`; Fehler zusätzlich HTTP 400.

- [ ] **Step 1: Failing E2E-Test** `tests/gallery_e2e.sh`:

```bash
#!/usr/bin/env bash
# HTTP-Test der Galerie. Voraussetzung: MariaDB + PHP-Server auf localhost:8080,
# Dev-Benutzer dev-admin@local.test / devpass123. Aufruf: bash tests/gallery_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
W=$(mktemp -d); JAR="$W/jar"; FAILS=0
ok()  { echo "  ok   $1"; }
bad() { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
expect() { [ "$2" = "$3" ] && ok "$1" || bad "$1 (erwartet $3, bekommen $2)"; }

CSRF=$(curl -s -c "$JAR" $B/login | grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "email=dev-admin@local.test" \
  --data-urlencode "password=devpass123" --data-urlencode "csrf_token=$CSRF" $B/login

TID=$("$MYSQL" -u root turnierverwaltung -N -e "INSERT INTO tournament (name,is_public) VALUES ('E2E-Galerie',0); SELECT LAST_INSERT_ID();")
CSRF=$(curl -s -b "$JAR" $B/tournament/$TID | grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')

# Testvideo: 2,5 MB mit gültigem MP4-Header (ftyp) → 3 Teile
{ printf '\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom'; head -c 2621440 /dev/zero; } > "$W/clip.mp4"
SIZE=$(stat -c %s "$W/clip.mp4"); CH=1048576; TOTAL=$(( (SIZE + CH - 1) / CH ))
UID_=$(printf 'e%.0s' {1..32})
for ((i=0;i<TOTAL;i++)); do
  dd if="$W/clip.mp4" of="$W/part" bs=$CH skip=$i count=1 2>/dev/null
  R=$(curl -s -b "$JAR" -F csrf_token="$CSRF" -F upload_id=$UID_ -F index=$i -F total=$TOTAL \
      -F name=clip.mp4 -F size=$SIZE -F chunk=@"$W/part" $B/tournament/$TID/gallery/chunk)
done
echo "$R" | grep -q '"done":true' && ok "Video hochgeladen" || bad "Video-Upload: $R"
GID=$(echo "$R" | grep -oE '"id":[0-9]+' | grep -oE '[0-9]+')

expect "Admin: media 200"         "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" $B/gallery/$GID/media)" 200
expect "Range 206"                "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -H 'Range: bytes=0-99' $B/gallery/$GID/media)" 206
CR=$(curl -s -D - -o /dev/null -b "$JAR" -H 'Range: bytes=0-99' $B/gallery/$GID/media | grep -i '^content-range' | tr -d '\r')
expect "Content-Range"            "$CR" "Content-Range: bytes 0-99/$SIZE"
expect "Range ungültig 416"       "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -H "Range: bytes=$SIZE-" $B/gallery/$GID/media)" 416
expect "Gast, nicht öffentlich 404" "$(curl -s -o /dev/null -w '%{http_code}' $B/gallery/$GID/media)" 404
"$MYSQL" -u root turnierverwaltung -e "UPDATE tournament SET is_public=1 WHERE id=$TID"
expect "Gast, öffentlich 200"     "$(curl -s -o /dev/null -w '%{http_code}' $B/gallery/$GID/media)" 200
expect "Gast Upload verweigert"   "$(curl -s -o /dev/null -w '%{http_code}' -F csrf_token=x -F upload_id=$UID_ -F index=0 -F total=1 -F name=a.jpg -F size=1 -F chunk=@"$W/part" $B/tournament/$TID/gallery/chunk)" 302
R=$(curl -s -b "$JAR" -F csrf_token="$CSRF" -F upload_id=$UID_ -F index=0 -F total=1 -F name=a.exe -F size=10 -F chunk=@"$W/part" $B/tournament/$TID/gallery/chunk)
echo "$R" | grep -q 'Dateityp nicht erlaubt' && ok "Endung abgelehnt" || bad "Endung: $R"

curl -s -o /dev/null -b "$JAR" --data-urlencode "csrf_token=$CSRF" --data-urlencode "caption=Finale 2026" $B/gallery/$GID/caption
expect "Beschriftung gespeichert" "$("$MYSQL" -u root turnierverwaltung -N -e "SELECT caption FROM gallery_item WHERE id=$GID")" "Finale 2026"
# Nur der letzte Teil des Video-Uploads wird protokolliert (Target beginnt mit dem Dateinamen)
N=$("$MYSQL" -u root turnierverwaltung -N -e "SELECT COUNT(*) FROM audit_log WHERE action='gallery.upload_chunk' AND path LIKE '%/tournament/$TID/%' AND target LIKE 'clip.mp4%'")
expect "Audit: 1 Eintrag je Upload" "$N" 1

curl -s -o /dev/null -b "$JAR" --data-urlencode "csrf_token=$CSRF" $B/gallery/$GID/delete
expect "gelöscht → 404"           "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" $B/gallery/$GID/media)" 404

"$MYSQL" -u root turnierverwaltung -e "DELETE FROM tournament WHERE id=$TID"
rm -rf "$W"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
```

- [ ] **Step 2: Run** `bash tests/gallery_e2e.sh` — Expected: FAIL („Video-Upload“, Route fehlt → 404-HTML).

- [ ] **Step 3: Streaming** an `lib/gallery.php` anhängen:

```php
// ── Auslieferung ───────────────────────────────────────────────────────────────

// Datei mit Range-Unterstützung (Video-Spulen) blockweise ausliefern.
function gallery_stream(string $path, string $mime, bool $public): never {
    if (!is_file($path)) { http_response_code(404); exit; }
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $size  = (int)filesize($path);
    $range = gallery_parse_range((string)($_SERVER['HTTP_RANGE'] ?? ''), $size);

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Cache-Control: ' . ($public ? 'public, max-age=86400' : 'private, max-age=3600'));
    header('Content-Disposition: inline');
    if ($range === false) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    [$start, $end] = $range ?? [0, $size - 1];
    if ($range !== null) {
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: ' . ($end - $start + 1));

    while (ob_get_level()) ob_end_clean();
    @set_time_limit(0);
    $fp = fopen($path, 'rb');
    fseek($fp, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp) && !connection_aborted()) {
        $buf = fread($fp, min(1048576, $left));
        if ($buf === false || $buf === '') break;
        echo $buf;
        flush();
        $left -= strlen($buf);
    }
    fclose($fp);
    exit;
}
```

- [ ] **Step 4: Handler** `routes/gallery.php`:

```php
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
    gallery_stream(UPLOAD_DIR . 'gallery/' . (int)$it['tournament_id'] . '/' . $it['filename'], $it['mime'], $public);
}

function thumb(array $p): void {
    $it = _gallery_item_or_404((int)$p['gid']);
    $public = _gallery_require_view((int)$it['tournament_id']);
    if (!$it['thumb']) { http_response_code(404); exit; }
    gallery_stream(UPLOAD_DIR . 'gallery/' . (int)$it['tournament_id'] . '/' . $it['thumb'], 'image/jpeg', $public);
}
```

- [ ] **Step 5: Routen** in `index.php` nach `['POST', '/tournament/{id}/editors/{uid}/remove', …]`:

```php

    // Galerie
    ['POST',     '/tournament/{id}/gallery/chunk',  'gallery', 'upload_chunk'],
    ['POST',     '/gallery/{gid}/caption',           'gallery', 'caption'],
    ['POST',     '/gallery/{gid}/delete',            'gallery', 'delete'],
    ['GET',      '/gallery/{gid}/media',             'gallery', 'media'],
    ['GET',      '/gallery/{gid}/thumb',             'gallery', 'thumb'],
```

- [ ] **Step 6: Audit-Log** in `helpers.php`:

(a) In `audit_log()` direkt nach `if ($status === 'ok' && $method !== 'POST') return;`:

```php
    // Galerie-Chunk-Upload: nur den letzten Teil protokollieren (sonst ein Eintrag je MB)
    if ($status === 'ok' && ($GLOBALS['__audit_route'] ?? '') === 'gallery.upload_chunk'
        && (int)post('index', -1) !== (int)post('total', 0) - 1) return;
```

(b) In `_audit_resolve_target()` vor `case 'pdf':`:

```php
        case 'gallery':
            if ($action === 'upload_chunk') return mb_substr((string)post('name'), 0, 150) . ' → ' . _audit_tname($id);
            $g = db_fetch("SELECT original_name, tournament_id FROM gallery_item WHERE id=?", [$gid]);
            return $g ? $g['original_name'] . ' → ' . _audit_tname((int)$g['tournament_id']) : '';

```

(c) In `audit_area_label()` ergänzen: `'gallery'      => 'Galerie',`

- [ ] **Step 7: Run** `php -l routes/gallery.php && php tests/gallery_test.php && bash tests/gallery_e2e.sh` — Expected: `Alle Tests ok`, `Alle E2E-Tests ok`.

- [ ] **Step 8: Commit**

```bash
git add lib/gallery.php routes/gallery.php index.php helpers.php tests/gallery_e2e.sh
git commit -m "feat(galerie): Chunk-Upload, Auslieferung mit Range, Beschriftung/Löschen, Audit"
```

---

### Task 5: Turnier-Löschung entfernt Galerie-Dateien; Medien an `show()` übergeben

**Files:**
- Modify: `routes/tournament.php` — `delete()` (Zeile ~240), `show()` (`render(...)`-Aufruf Zeile ~168)

**Interfaces:**
- Consumes: `gallery_delete_tournament_files(int)`, `gallery_items(int)`.
- Produces: Template-Variable `$gallery` (array von `gallery_item`-Zeilen) in `tournament/show`.

- [ ] **Step 1: Failing check**

```bash
TID=$("/c/Program Files/MariaDB 12.3/bin/mysql.exe" -u root turnierverwaltung -N -e "INSERT INTO tournament (name) VALUES ('Del-Test'); SELECT LAST_INSERT_ID();")
mkdir -p /c/Users/juerg/claude/Turnier/uploads/gallery/$TID && touch /c/Users/juerg/claude/Turnier/uploads/gallery/$TID/x.jpg
# als Admin per curl einloggen (siehe tests/gallery_e2e.sh), CSRF von /tournament/$TID holen, dann:
curl -s -o /dev/null -b "$JAR" --data-urlencode "csrf_token=$CSRF" http://localhost:8080/tournament/$TID/delete
ls /c/Users/juerg/claude/Turnier/uploads/gallery/$TID
```
Expected vor der Änderung: Verzeichnis existiert noch (`x.jpg` gelistet).

- [ ] **Step 2: `delete()`** anpassen:

```php
function delete(array $p): void {
    require_tournament_edit((int)$p['id']);
    csrf_verify();
    db_execute("DELETE FROM tournament WHERE id = ?", [$p['id']]);
    require_once __DIR__ . '/../lib/gallery.php';
    gallery_delete_tournament_files((int)$p['id']);
    flash('info', 'Turnier gelöscht.');
    redirect('');
}
```

- [ ] **Step 3: `show()`** — vor `render('tournament/show', [`:

```php
    require_once __DIR__ . '/../lib/gallery.php';
    $gallery = gallery_items((int)$p['id']);
```
und im Render-Array ergänzen: `'gallery'           => $gallery,`

- [ ] **Step 4: Verify** — Step 1 wiederholen → `ls` meldet „No such file or directory“. `curl -s -b "$JAR" http://localhost:8080/tournament/1 | grep -c 'Fatal\|Warning'` → `0`.

- [ ] **Step 5: Commit**

```bash
git add routes/tournament.php
git commit -m "feat(galerie): Turnier-Löschung entfernt Galerie-Dateien, Medien für Turnierseite laden"
```

---

### Task 6: UI — Reiter „Galerie“, Lightbox, Upload

**Files:**
- Create: `templates/tournament/_gallery.php`
- Modify: `templates/tournament/show.php` — Reiter-Button nach dem `Bewerbe`-`<li>` (Zeile ~82), Partial vor `</div><!-- /tab-content -->` (Zeile ~478)

**Interfaces:**
- Consumes: `$gallery`, `$can_edit`, `$t` aus `show()`; Routen aus Task 4; Konstanten aus Task 1. Tab-Hash `#tab-gallery` wird von `_base.php` bereits ausgewertet (aktiviert Reiter, `redirect()` erhält `_tab`).

- [ ] **Step 1: Reiter-Button** in `show.php` direkt nach dem schließenden `</li>` des Bewerbe-Reiters:

```php
  <?php if ($gallery || $can_edit): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-gallery-btn"
            data-bs-toggle="tab" data-bs-target="#tab-gallery" type="button" role="tab">
      <i class="bi bi-images me-1"></i>Galerie
      <?php if ($gallery): ?>
      <span class="badge bg-secondary ms-1"><?= count($gallery) ?></span>
      <?php endif; ?>
    </button>
  </li>
  <?php endif; ?>
```

- [ ] **Step 2: Partial einbinden** in `show.php` direkt vor `</div><!-- /tab-content -->`:

```php
  <?php if ($gallery || $can_edit) require __DIR__ . '/_gallery.php'; ?>
```

- [ ] **Step 3: Partial** `templates/tournament/_gallery.php`:

```php
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
```

- [ ] **Step 4: Verify im Browser** (Claude-in-Chrome oder manuell), als `dev-admin@local.test`:
  1. `http://localhost:8080/tournament/{id}` → Reiter „Galerie“ sichtbar, leer mit Hinweistext.
  2. Ein JPG (Handy, Hochformat), ein PNG und ein MP4 (> 3 MB) gleichzeitig auswählen → Fortschrittsbalken, danach Reload mit aktivem Reiter „Galerie“ und Badge `3`.
  3. Kachel anklicken → Lightbox; Pfeiltasten/Buttons wechseln; Video spielt und lässt sich spulen; Schließen stoppt das Video.
  4. Beschriftung ändern → erscheint unter der Kachel und in der Lightbox.
  5. `.txt` umbenannt zu `.jpg` hochladen → rote Meldung „Dateityp nicht erlaubt …“.
  6. Abmelden: öffentliches Turnier → Reiter sichtbar ohne Upload/Bearbeiten; nicht öffentliches Turnier → Turnierseite gesperrt.
  7. Turnier ohne Medien als Gast → kein Reiter „Galerie“.
  8. Browser-Konsole ohne Fehler (auch keine CSP-Verletzung).

- [ ] **Step 5: Commit**

```bash
git add templates/tournament/_gallery.php templates/tournament/show.php
git commit -m "feat(galerie): Reiter Galerie mit Lightbox und Chunk-Upload"
```

---

### Task 7: Dokumentation

**Files:**
- Modify: `CLAUDE.md` — neuer Abschnitt nach „### Anwurf-Auslosung …“ bzw. vor „### PDF- & CSV-Exporte“; Tabellenzeile `gallery_item` in „Datenbankschema“; `gallery.php` in der Dateiliste unter „Route-Handler“.

- [ ] **Step 1: Abschnitt ergänzen**

```markdown
### Turnier-Galerie (`lib/gallery.php`, `routes/gallery.php`)

Fotos/Videos je Turnier (Tabelle `gallery_item`), Reiter **„Galerie“** auf der Turnierseite
(`templates/tournament/_gallery.php`, sichtbar wenn Medien vorhanden oder Bearbeiter).
- Upload/Beschriften/Löschen: `require_tournament_edit`. Sichtbarkeit wie das Turnier
  (`is_public` oder Bearbeiter), sonst 404.
- **Chunk-Upload**: JS sendet 1-MB-Teile (`GALLERY_CHUNK_BYTES`) an
  `POST /tournament/{id}/gallery/chunk`; `gallery_finalize_upload()` setzt zusammen, prüft `finfo`
  (Fotos JPG/PNG/WebP/GIF ≤ `GALLERY_MAX_IMAGE_MB`, Videos MP4/WebM/MOV ≤ `GALLERY_MAX_VIDEO_MB`),
  Fotos: EXIF-Orientierung, max. 2560 px, neu kodiert (Metadaten/GPS entfernt), Vorschau 400 px.
- Dateien unter `uploads/gallery/{tid}/` — **kein Direktzugriff** (`.htaccess` wird von
  `gallery_root()` angelegt; `index.php`/`router.php` liefern 404). Auslieferung über
  `GET /gallery/{gid}/media|thumb` mit Range-Support (`gallery_stream()`).
- Audit-Log: beim Chunk-Upload nur der letzte Teil (Target = Dateiname). Turnier-Löschung
  entfernt `uploads/gallery/{tid}/`.
- Tests: `php tests/gallery_test.php` (Lib, braucht MariaDB), `bash tests/gallery_e2e.sh` (HTTP).
```

Tabellenzeile:

```markdown
| `gallery_item` | Galerie-Medien je Turnier: `type` ('image'/'video'), `filename`/`thumb` (zufällige Namen in `uploads/gallery/{tid}/`), `mime`, `original_name`, `caption`, `size`, `uploaded_by` (Snapshot, kein FK) — FK CASCADE auf `tournament` |
```

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: Turnier-Galerie in CLAUDE.md"
```
