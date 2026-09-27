<?php
// CLI-Test Excel-Import-Grenzen: php tests/import_test.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // nie per Webserver ausführen
chdir(__DIR__ . '/..');
require 'config.php'; require 'db.php'; require 'helpers.php'; require 'auth.php';
require 'routes/player.php';
ini_set('memory_limit', '128M');   // realistisches Host-Limit

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    echo ($ok ? "  ok   " : "  FAIL ") . $label . "\n";
    if (!$ok) $fails++;
}
function make_xlsx(string $sheetXml): string {
    $f = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx';
    $z = new ZipArchive(); $z->open($f, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $z->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $z->close();
    return $f;
}
$wrap = fn(string $rows) => '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $rows . '</sheetData></worksheet>';

echo "_xlsx_parse Grenzen\n";
$f = make_xlsx($wrap('<row r="1"><c r="A1" t="inlineStr"><is><t>Muster</t></is></c><c r="B1" t="inlineStr"><is><t>Max</t></is></c></row>'));
check('normale Datei', _xlsx_parse($f) === [['Muster', 'Max']]);

$f = make_xlsx($wrap('<row r="1"><c r="A1" t="inlineStr"><is><t>Muster</t></is></c><c r="ZZZZZZZ1"><v>1</v></c></row>'));
$r = _xlsx_parse($f);   // ohne Grenze: Fatal Error (Speicher)
check('riesiger Spaltenindex ignoriert', $r === [['Muster']]);

$f = make_xlsx($wrap('<row r="1"><c t="inlineStr"><is><t>ohne Bezug</t></is></c></row>'));
check('Zelle ohne r-Attribut kein Fehler', is_array(_xlsx_parse($f)));

$rows = ''; for ($i = 1; $i <= 6000; $i++) $rows .= '<row r="' . $i . '"><c r="A' . $i . '"><v>' . $i . '</v></c></row>';
$f = make_xlsx($wrap($rows));
check('max. 5000 Zeilen', count(_xlsx_parse($f)) === 5000);

$f = make_xlsx($wrap('<row r="1"><c r="A1"><v>1</v></c></row>' . str_repeat(' ', 21 * 1024 * 1024)));
check('entpackt > 20 MB abgelehnt', _xlsx_parse($f) === []);

echo $fails ? "\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
