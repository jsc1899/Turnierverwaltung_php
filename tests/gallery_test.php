<?php
// CLI-Tests der Galerie-Lib: php tests/gallery_test.php  (MariaDB muss laufen)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // nie per Webserver ausführen
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
// Orientierung 6 = 90° im Uhrzeigersinn: die rote obere Hälfte muss rechts liegen
$saved = imagecreatefromjpeg(gallery_dir($tid) . $row['filename']);
$rgbR = imagecolorat($saved, 1800, 1280); $rgbL = imagecolorat($saved, 100, 1280);
check('Drehrichtung korrekt (rot rechts)', (($rgbR >> 16) & 255) > 200 && (($rgbL >> 16) & 255) < 60);
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

// 3b) Bild mit zu vielen Pixeln (Dekompressionsbombe) → saubere Fehlermeldung statt Fatal Error
ini_set('memory_limit', '1G');   // nur zum Erzeugen des Testbildes
$bomb = imagecreate(16000, 16000); imagecolorallocate($bomb, 255, 255, 255);
ob_start(); imagepng($bomb, null, 9); $png = ob_get_clean(); imagedestroy($bomb);
unset($bomb); ini_set('memory_limit', '128M');   // realistisches Host-Limit
$uid4 = str_repeat('a', 32);
$n4 = put_parts($tid, $uid4, $png);
$r4 = gallery_finalize_upload($tid, $uid4, $n4, 'riesig.png', null);
check('Riesenbild abgelehnt', ($r4['ok'] ?? true) === false && str_contains($r4['error'] ?? '', 'Megapixel'));

echo "gallery_memory_bytes / Limit nur anheben
";
check('128M',  gallery_memory_bytes('128M') === 134217728);
check('1G',    gallery_memory_bytes('1G') === 1073741824);
check('-1',    gallery_memory_bytes('-1') === PHP_INT_MAX);
$old = ini_get('memory_limit'); ini_set('memory_limit', '1G');
gallery_raise_memory_limit(512 * 1024 * 1024);
check('höheres Limit bleibt', ini_get('memory_limit') === '1G');
ini_set('memory_limit', $old);

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
check('Turnierverzeichnis entfernt', !is_dir(GALLERY_DIR . $tid));
db_execute("DELETE FROM tournament WHERE id=?", [$tid]);

echo $fails ? "\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
