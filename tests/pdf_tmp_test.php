<?php
// CLI-Test PDF-Temp-Dateien: php tests/pdf_tmp_test.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // nie per Webserver ausführen
chdir(__DIR__ . '/..');
require 'config.php'; require 'db.php'; require 'helpers.php';
require 'vendor/autoload.php'; require 'lib/pdf.php';

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    echo ($ok ? "  ok   " : "  FAIL ") . $label . "\n";
    if (!$ok) $fails++;
}

echo "PDF-Temp-Dateien\n";
$dir = pdf_private_tmp_dir();
check('privater Temp-Ordner existiert', is_dir($dir));
check('app-spezifisch (nicht das allgemeine mpdf_tmp)', basename($dir) !== 'mpdf_tmp');
$a = pdf_qr_tempfile('<svg>1</svg>');
$b = pdf_qr_tempfile('<svg>2</svg>');
check('QR-Datei im privaten Ordner', str_starts_with($a, $dir));
check('Name zufällig (nicht vorhersagbar)', $a !== $b && !preg_match('/qr_(comp_)?aushang_\d+\.svg$/', $a));
check('Inhalt geschrieben', file_get_contents($a) === '<svg>1</svg>');
pdf_tmp_cleanup();
check('Aufräumen entfernt QR-Dateien', !is_file($a) && !is_file($b));

echo $fails ? "\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
