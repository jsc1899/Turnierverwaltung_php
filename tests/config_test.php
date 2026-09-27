<?php
// CLI-Tests für das Laden der .env und Pfadauflösung: php tests/config_test.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(__DIR__ . '/..');
require 'config.php';

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    echo ($ok ? "  ok   " : "  FAIL ") . $label . "\n";
    if (!$ok) $fails++;
}

echo "_env_files: .env über dem App-Ordner hat Vorrang\n";
$tmp = sys_get_temp_dir() . '/envtest_' . bin2hex(random_bytes(4));
mkdir($tmp . '/app', 0777, true);
check('keine Dateien → leer', _env_files($tmp . '/app') === []);
file_put_contents($tmp . '/app/.env', "ENVTEST_A=innen\nENVTEST_B=innen\n");
check('nur innen', _env_files($tmp . '/app') === [$tmp . '/app/.env']);
file_put_contents($tmp . '/.env', "# Kommentar\nENVTEST_A=aussen\n");
check('außen zuerst', _env_files($tmp . '/app') === [$tmp . '/.env', $tmp . '/app/.env']);
foreach (_env_files($tmp . '/app') as $f) _env_load_file($f);
check('außen gewinnt', getenv('ENVTEST_A') === 'aussen');
check('innen ergänzt', getenv('ENVTEST_B') === 'innen');
putenv('ENVTEST_C=echt');
file_put_contents($tmp . '/.env', "ENVTEST_C=datei\n");
_env_load_file($tmp . '/.env');
check('echte ENV gewinnt', getenv('ENVTEST_C') === 'echt');
@unlink($tmp . '/.env'); @unlink($tmp . '/app/.env'); @rmdir($tmp . '/app'); @rmdir($tmp);

echo "_env_path: relative Pfade bezogen auf den App-Ordner\n";
check('relativ',        _env_path('../turnier_gallery', '/srv/app') === '/srv/app/../turnier_gallery');
check('absolut Unix',   _env_path('/data/gal', '/srv/app') === '/data/gal');
check('absolut Win',    _env_path('C:\data\gal', 'C:\app') === 'C:\data\gal');
check('GALLERY_DIR endet mit /', str_ends_with(GALLERY_DIR, '/'));

echo "_env_inner_allowed: innere .env nur lokal\n";
check('CLI erlaubt',              _env_inner_allowed('cli', '') === true);
check('localhost erlaubt',        _env_inner_allowed('cli-server', 'localhost:8080') === true);
check('127.0.0.1 erlaubt',        _env_inner_allowed('fpm-fcgi', '127.0.0.1') === true);
check('Live-Host nicht erlaubt',  _env_inner_allowed('fpm-fcgi', 'turniere.union-saxen.at') === false);
check('Subdomain-Trick nicht',    _env_inner_allowed('fpm-fcgi', 'localhost.evil.at') === false);

echo "_secret_key_problem\n";
check('Default erkannt',          _secret_key_problem('change-me-in-production') !== null);
check('.env.example erkannt',     _secret_key_problem('hier-einen-langen-zufaelligen-string-eintragen') !== null);
check('zu kurz erkannt',          _secret_key_problem('kurz') !== null);
check('64 Hex ok',                _secret_key_problem(str_repeat('ab', 32)) === null);

echo $fails ?"\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
