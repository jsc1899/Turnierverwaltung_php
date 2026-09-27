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
